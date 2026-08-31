<?php

namespace iTRON\cf7Vk\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use iTRON\cf7Vk\Logger;
use iTRON\cf7Vk\Settings;
use iTRON\cf7Vk\Util;

class Migration {
	public const MIGRATION_HOOK = 'cf7vk_migrations';
	public const MIGRATION_STEPS_HOOK = 'cf7vk_migration_steps';
	public const MIGRATION_STEP_HOOK = 'cf7vk_migration';
	public const VERSION_OPTION = 'cf7vk_version';
	public const STATE_OPTION = 'cf7vk_migration_state';
	public const LOCK_OPTION = 'cf7vk_migration_lock';
	public const STATE_SCHEMA = 1;
	public const LOCK_TTL = 300;
	public const STATUS_SCHEDULED = 'scheduled';
	public const STATUS_RUNNING = 'running';
	public const STATUS_FAILED = 'failed';
	public const STATUS_COMPLETED = 'completed';

	private const LEGACY_REPAIR_VERSION = '0.0';
	private const STABLE_PLUGIN_BASENAME = 'message-bridge-for-contact-form-7-and-vk/cf7-vk.php';
	private const LEGACY_PLUGIN_BASENAME = 'cf7-vk/cf7-vk.php';

	private static Migration $instance;
	private ?string $lockToken = null;

	protected function __construct() {}
	protected function __clone() {}

	public function __wakeup() {
		wp_trigger_error(
			__METHOD__,
			'Deserializing of iTRON\cf7Vk\Controllers\Migration instance is prohibited.',
			E_USER_NOTICE
		);
	}

	public static function getInstance(): Migration {
		if ( empty( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public static function init(): void {
		add_action( 'upgrader_process_complete', [ self::getInstance(), 'verifyUpgrading' ], 10, 2 );
		add_action( 'init', [ self::getInstance(), 'ensureMigrationScheduled' ], 25 );
		add_action( 'admin_post_cf7vk_retry_migration', [ self::getInstance(), 'handleAdminRetry' ] );
		add_action( self::MIGRATION_HOOK, [ self::getInstance(), 'migrate' ], 10, 3 );
	}

	public function verifyUpgrading( $upgrader, array $hook_extra ): void {
		if (
			'update' !== ( $hook_extra['action'] ?? '' ) ||
			'plugin' !== ( $hook_extra['type'] ?? '' )
		) {
			return;
		}

		$plugins = [];
		if ( ! empty( $hook_extra['plugins'] ) && is_array( $hook_extra['plugins'] ) ) {
			$plugins = $hook_extra['plugins'];
		}

		if ( ! empty( $hook_extra['plugin'] ) ) {
			$plugins[] = $hook_extra['plugin'];
		}

		if ( empty( array_intersect( $plugins, self::getSupportedPluginBasenames() ) ) ) {
			return;
		}

		$this->scheduleMigration( 'plugin_update', self::resolveSourceVersion(), CF7VK_VERSION, 30 );
	}

	public function ensureMigrationScheduled(): void {
		$state = self::getMigrationState();

		if ( self::isCompletedState( $state ) ) {
			return;
		}

		if ( ! self::needsMigrationRepair( $state ) ) {
			return;
		}

		if ( self::hasActiveMigrationLock() ) {
			return;
		}

		self::clearStaleMigrationLock();
		$this->scheduleMigration( 'self_heal', self::resolveSourceVersion(), CF7VK_VERSION, 0 );
	}

	public function migrate( $reason = null, $source_version = null, $target_version = null ): void {
		$context = self::normalizeMigrationContext( func_get_args() );

		if ( self::isCompletedState( self::getMigrationState() ) ) {
			return;
		}

		if ( ! $this->acquireMigrationLock() ) {
			$this->scheduleMigration(
				'lock_retry',
				$context['source_version'],
				$context['target_version'],
				self::LOCK_TTL
			);
			return;
		}

		try {
			self::markRunStarted( $context );
			$this->loadMigrations();

			do_action(
				self::MIGRATION_STEPS_HOOK,
				$context['source_version'],
				$context['target_version'],
				$context
			);

			$state = self::getMigrationState();
			if ( self::STATUS_FAILED === ( $state['status'] ?? '' ) ) {
				return;
			}

			update_option( self::VERSION_OPTION, $context['target_version'], false );
			self::markRunCompleted( $context );
		} catch ( \Throwable $e ) {
			self::markStepFailed( '__runner__', $context, $e );
			self::logMigrationError( '__runner__', $context, $e );
		} finally {
			$this->releaseMigrationLock();
		}
	}

	public static function getMigrationState(): array {
		$state = get_option( self::STATE_OPTION, [] );

		if ( empty( $state ) ) {
			return [];
		}

		return is_array( $state ) ? self::normalizeMigrationState( $state ) : [];
	}

	public static function getAdminRecoveryState(): array {
		$state = self::getMigrationState();
		$status = $state['status'] ?? '';
		$scheduled = self::hasScheduledMigrationEvent();
		$running = self::STATUS_RUNNING === $status || self::hasActiveMigrationLock();
		$completed = self::isCompletedState( $state );
		$repairable = ! $completed && self::needsMigrationRepair( $state );
		$can_retry = $repairable && ! $scheduled && ! $running;

		return [
			'show_action_button' => $repairable,
			'can_retry'          => $can_retry,
			'is_scheduled'       => $scheduled,
			'is_running'         => $running,
			'is_failed'          => self::STATUS_FAILED === $status,
			'is_completed'       => $completed,
			'status'             => $status ?: ( $repairable ? self::STATUS_SCHEDULED : '' ),
			'attempts'           => (int) ( $state['attempts'] ?? 0 ),
			'current_step'       => (string) ( $state['current_step'] ?? '' ),
			'last_error'         => self::lastMigrationError( $state ),
			'state'              => $state,
		];
	}

	public function scheduleAdminRetry(): bool {
		$state = self::getMigrationState();

		if ( self::isCompletedState( $state ) || ! self::needsMigrationRepair( $state ) ) {
			return false;
		}

		if (
			self::STATUS_RUNNING === ( $state['status'] ?? '' ) ||
			self::hasActiveMigrationLock() ||
			self::hasScheduledMigrationEvent()
		) {
			return false;
		}

		self::clearStaleMigrationLock();

		return $this->scheduleMigration(
			'admin_retry',
			self::normalizeVersionValue( $state['source_version'] ?? self::resolveSourceVersion() ),
			self::normalizeVersionValue( $state['target_version'] ?? CF7VK_VERSION, CF7VK_VERSION ),
			0
		);
	}

	public function handleAdminRetry(): void {
		if ( ! current_user_can( Settings::getCaps() ) ) {
			wp_die(
				esc_html__( 'You are not allowed to retry CF7 VK migrations.', 'message-bridge-for-contact-form-7-and-vk' ),
				'',
				[ 'response' => 403 ]
			);
		}

		check_admin_referer( 'cf7vk_retry_migration' );

		$scheduled = $this->scheduleAdminRetry();
		$redirect = wp_get_referer();

		if ( ! $redirect ) {
			$redirect = admin_url( 'admin.php?page=' . Settings::PAGE_SLUG );
		}

		wp_safe_redirect(
			add_query_arg(
				[
					'cf7vk_migration_retry' => $scheduled ? 'scheduled' : 'skipped',
				],
				$redirect
			)
		);
	}

	public static function registerMigration( string $migration_version, callable $migration_function ): void {
		add_action(
			self::MIGRATION_STEPS_HOOK,
			static function ( $old_version, $new_version, $context ) use ( $migration_version, $migration_function ): void {
				if (
					! version_compare( (string) $old_version, $migration_version, '<' ) ||
					! version_compare( self::stripPrerelease( (string) $new_version ), $migration_version, '>=' )
				) {
					return;
				}

				if ( self::STATUS_FAILED === ( self::getMigrationState()['status'] ?? '' ) ) {
					return;
				}

				if (
					self::isStepCompleted( $migration_version ) ||
					(
						! self::hasLegacyMigrationEvidence() &&
						! empty( get_option( 'cf7vk_migration_' . $migration_version ) )
					)
				) {
					self::logMigrationAlreadyDone( $migration_version, (string) $old_version, (string) $new_version );
					return;
				}

				$context = is_array( $context )
					? $context
					: self::normalizeMigrationContext( [ $context, $old_version, $new_version ] );

				do_action( self::MIGRATION_STEP_HOOK, $migration_version, $old_version, $new_version );
				self::markStepStarted( $migration_version, $context );

				try {
					call_user_func( $migration_function, $old_version, $new_version, $context );
				} catch ( \Throwable $e ) {
					self::markStepFailed( $migration_version, $context, $e );
					self::logMigrationError( $migration_version, $context, $e );
					return;
				}

				self::markStepCompleted( $migration_version, $context );
				update_option(
					'cf7vk_migration_' . $migration_version,
					[
						'old_version' => $old_version,
						'new_version' => $new_version,
					],
					false
				);
			},
			Util::versionToInt( $migration_version ),
			3
		);
	}

	private function loadMigrations(): void {
		foreach ( glob( Settings::pluginDir() . '/inc/migrations/*.php' ) as $file ) {
			require_once $file;
		}
	}

	private function scheduleMigration( string $reason, string $source_version, string $target_version, int $delay ): bool {
		$context = [
			'reason'         => $reason,
			'source_version' => $source_version,
			'target_version' => $target_version,
		];
		$state = self::prepareStateForContext( $context );

		if ( self::isCompletedState( $state ) ) {
			return false;
		}

		if ( self::hasScheduledMigrationEvent() ) {
			$state['status'] = self::STATUS_SCHEDULED;
			$state['updated_at'] = time();
			self::saveMigrationState( $state );
			return true;
		}

		$state['status'] = self::STATUS_SCHEDULED;
		$state['updated_at'] = time();
		self::saveMigrationState( $state );

		$result = wp_schedule_single_event(
			time() + max( 0, $delay ),
			self::MIGRATION_HOOK,
			array_values( $context )
		);

		if ( false === $result || is_wp_error( $result ) ) {
			self::markScheduleFailed( $context );
			return false;
		}

		return true;
	}

	private static function hasScheduledMigrationEvent(): bool {
		if ( function_exists( '_get_cron_array' ) ) {
			foreach ( _get_cron_array() as $hooks ) {
				if ( ! empty( $hooks[ self::MIGRATION_HOOK ] ) ) {
					return true;
				}
			}

			return false;
		}

		return function_exists( 'wp_next_scheduled' ) && false !== wp_next_scheduled( self::MIGRATION_HOOK );
	}

	private static function needsMigrationRepair( array $state ): bool {
		if ( ! empty( $state ) && ! self::isCompletedState( $state ) ) {
			return true;
		}

		return self::hasLegacyMigrationEvidence() || self::hasModernMigrationEvidence();
	}

	private static function hasModernMigrationEvidence(): bool {
		$version = get_option( self::VERSION_OPTION, false );

		return is_scalar( $version ) && '' !== trim( (string) $version );
	}

	private static function hasLegacyMigrationEvidence(): bool {
		foreach ( [ 'wpcf7_vk_tkn', 'wpcf7_vk_chats', 'wpcf7_vk_last_update_id' ] as $option_name ) {
			if ( false !== get_option( $option_name, false ) ) {
				return true;
			}
		}

		if ( self::hasUsableLegacyTokenConstant() ) {
			return true;
		}

		return ! empty( self::getOptionNamesByPrefix( 'vk_notifications_' ) );
	}

	private static function hasUsableLegacyTokenConstant(): bool {
		foreach ( [ 'WPFC7VK_ACCESS_TOKEN', 'WPCF7VK_ACCESS_TOKEN', 'CF7VK_ACCESS_TOKEN' ] as $constant_name ) {
			if ( ! defined( $constant_name ) ) {
				continue;
			}

			$token = constant( $constant_name );
			if ( is_scalar( $token ) && '' !== trim( (string) $token ) ) {
				return true;
			}
		}

		return false;
	}

	private static function resolveSourceVersion(): string {
		if ( self::hasLegacyMigrationEvidence() ) {
			return self::LEGACY_REPAIR_VERSION;
		}

		$version = get_option( self::VERSION_OPTION, self::LEGACY_REPAIR_VERSION );
		if ( ! is_scalar( $version ) || '' === trim( (string) $version ) ) {
			return self::LEGACY_REPAIR_VERSION;
		}

		return trim( (string) $version );
	}

	private static function normalizeMigrationContext( array $args ): array {
		if ( isset( $args[0] ) && is_string( $args[0] ) && isset( $args[1], $args[2] ) ) {
			return [
				'reason'         => sanitize_key( $args[0] ) ?: 'cron',
				'source_version' => self::normalizeVersionValue( $args[1] ),
				'target_version' => self::normalizeVersionValue( $args[2], CF7VK_VERSION ),
			];
		}

		if ( isset( $args[0] ) && is_array( $args[0] ) && isset( $args[0]['reason'] ) ) {
			return [
				'reason'         => sanitize_key( (string) $args[0]['reason'] ) ?: 'cron',
				'source_version' => self::normalizeVersionValue( $args[0]['source_version'] ?? self::LEGACY_REPAIR_VERSION ),
				'target_version' => self::normalizeVersionValue( $args[0]['target_version'] ?? CF7VK_VERSION, CF7VK_VERSION ),
			];
		}

		$legacy_source_version = self::LEGACY_REPAIR_VERSION;
		if ( ! self::hasLegacyMigrationEvidence() ) {
			$legacy_source_version = $args[1] ?? self::legacyPreVersionFromArgument( $args[0] ?? null );
		}

		return [
			'reason'         => 'legacy_cron',
			'source_version' => self::normalizeVersionValue( $legacy_source_version ),
			'target_version' => self::normalizeVersionValue( $args[2] ?? CF7VK_VERSION, CF7VK_VERSION ),
		];
	}

	private static function legacyPreVersionFromArgument( $argument ): string {
		if ( is_array( $argument ) && isset( $argument['preVersion'] ) ) {
			return self::normalizeVersionValue( $argument['preVersion'] );
		}

		return self::LEGACY_REPAIR_VERSION;
	}

	private static function normalizeVersionValue( $value, string $fallback = self::LEGACY_REPAIR_VERSION ): string {
		if ( ! is_scalar( $value ) ) {
			return $fallback;
		}

		$value = trim( (string) $value );
		return '' === $value ? $fallback : $value;
	}

	private static function prepareStateForContext( array $context ): array {
		$state = self::getMigrationState();
		$now = time();

		if ( empty( $state ) || self::STATUS_COMPLETED === ( $state['status'] ?? '' ) ) {
			$state = [
				'schema'         => self::STATE_SCHEMA,
				'status'         => self::STATUS_SCHEDULED,
				'reason'         => $context['reason'],
				'source_version' => $context['source_version'],
				'target_version' => $context['target_version'],
				'attempts'       => 0,
				'current_step'   => '',
				'steps'          => [],
				'errors'         => [],
				'lock'           => null,
				'created_at'     => $now,
				'updated_at'     => $now,
			];
		}

		$state['reason'] = $context['reason'];
		$state['source_version'] = $context['source_version'];
		$state['target_version'] = $context['target_version'];
		$state['schema'] = self::STATE_SCHEMA;
		$state['updated_at'] = $now;

		return self::normalizeMigrationState( $state );
	}

	private static function normalizeMigrationState( array $state ): array {
		$state['schema'] = (int) ( $state['schema'] ?? self::STATE_SCHEMA );
		$state['status'] = (string) ( $state['status'] ?? self::STATUS_SCHEDULED );
		$state['reason'] = (string) ( $state['reason'] ?? '' );
		$state['source_version'] = (string) ( $state['source_version'] ?? self::LEGACY_REPAIR_VERSION );
		$state['target_version'] = (string) ( $state['target_version'] ?? CF7VK_VERSION );
		$state['attempts'] = (int) ( $state['attempts'] ?? 0 );
		$state['current_step'] = (string) ( $state['current_step'] ?? '' );
		$state['steps'] = isset( $state['steps'] ) && is_array( $state['steps'] ) ? $state['steps'] : [];
		$state['errors'] = isset( $state['errors'] ) && is_array( $state['errors'] ) ? $state['errors'] : [];
		$state['lock'] = isset( $state['lock'] ) && is_array( $state['lock'] ) ? $state['lock'] : null;
		$state['created_at'] = (int) ( $state['created_at'] ?? time() );
		$state['updated_at'] = (int) ( $state['updated_at'] ?? time() );

		return $state;
	}

	private static function lastMigrationError( array $state ): array {
		$errors = isset( $state['errors'] ) && is_array( $state['errors'] ) ? $state['errors'] : [];
		$error = end( $errors );

		if ( ! is_array( $error ) ) {
			return [];
		}

		return [
			'step'    => (string) ( $error['step'] ?? '' ),
			'message' => (string) ( $error['message'] ?? '' ),
			'code'    => (string) ( $error['code'] ?? '' ),
			'type'    => (string) ( $error['type'] ?? '' ),
			'time'    => (int) ( $error['time'] ?? 0 ),
		];
	}

	private static function saveMigrationState( array $state ): void {
		update_option( self::STATE_OPTION, self::normalizeMigrationState( $state ), false );
	}

	private static function isCompletedState( array $state ): bool {
		return self::STATUS_COMPLETED === ( $state['status'] ?? '' )
			&& CF7VK_VERSION === ( $state['target_version'] ?? '' );
	}

	private static function markRunStarted( array $context ): void {
		$state = self::prepareStateForContext( $context );
		$state['status'] = self::STATUS_RUNNING;
		$state['attempts'] = (int) $state['attempts'] + 1;
		$state['started_at'] = $state['started_at'] ?? time();
		$state['updated_at'] = time();
		self::saveMigrationState( $state );
	}

	private static function markRunCompleted( array $context ): void {
		$state = self::prepareStateForContext( $context );
		$state['status'] = self::STATUS_COMPLETED;
		$state['current_step'] = '';
		$state['completed_at'] = time();
		$state['updated_at'] = time();
		$state['lock'] = null;
		self::saveMigrationState( $state );
	}

	private static function markScheduleFailed( array $context ): void {
		$state = self::prepareStateForContext( $context );
		$state['status'] = self::STATUS_FAILED;
		$state['errors'][] = [
			'step'    => '__schedule__',
			'message' => 'Migration could not be scheduled.',
			'code'    => 'schedule_failed',
			'type'    => 'schedule',
			'time'    => time(),
		];
		$state['updated_at'] = time();
		self::saveMigrationState( $state );
	}

	private static function markStepStarted( string $step, array $context ): void {
		$state = self::prepareStateForContext( $context );
		$previous = isset( $state['steps'][ $step ] ) && is_array( $state['steps'][ $step ] )
			? $state['steps'][ $step ]
			: [];

		$state['status'] = self::STATUS_RUNNING;
		$state['current_step'] = $step;
		$state['steps'][ $step ] = array_merge(
			$previous,
			[
				'status'         => self::STATUS_RUNNING,
				'attempts'       => (int) ( $previous['attempts'] ?? 0 ) + 1,
				'source_version' => $context['source_version'],
				'target_version' => $context['target_version'],
				'started_at'     => $previous['started_at'] ?? time(),
				'updated_at'     => time(),
			]
		);
		$state['updated_at'] = time();
		self::saveMigrationState( $state );
	}

	private static function markStepCompleted( string $step, array $context ): void {
		$state = self::prepareStateForContext( $context );
		$previous = isset( $state['steps'][ $step ] ) && is_array( $state['steps'][ $step ] )
			? $state['steps'][ $step ]
			: [];

		$state['steps'][ $step ] = array_merge(
			$previous,
			[
				'status'         => self::STATUS_COMPLETED,
				'source_version' => $context['source_version'],
				'target_version' => $context['target_version'],
				'completed_at'   => time(),
				'updated_at'     => time(),
			]
		);
		$state['current_step'] = '';
		$state['updated_at'] = time();
		self::saveMigrationState( $state );
	}

	private static function markStepFailed( string $step, array $context, \Throwable $e ): void {
		$state = self::prepareStateForContext( $context );
		$previous = isset( $state['steps'][ $step ] ) && is_array( $state['steps'][ $step ] )
			? $state['steps'][ $step ]
			: [];
		$error = [
			'step'    => $step,
			'message' => 'Migration step failed.',
			'code'    => (string) $e->getCode(),
			'type'    => self::getPublicErrorType( $e ),
			'time'    => time(),
		];

		$state['status'] = self::STATUS_FAILED;
		$state['current_step'] = $step;
		$state['steps'][ $step ] = array_merge(
			$previous,
			[
				'status'       => self::STATUS_FAILED,
				'last_error'   => $error,
				'completed_at' => null,
				'updated_at'   => time(),
			]
		);
		$state['errors'][] = $error;
		$state['updated_at'] = time();
		self::saveMigrationState( $state );
	}

	private static function isStepCompleted( string $step ): bool {
		$state = self::getMigrationState();

		return self::STATUS_COMPLETED === ( $state['steps'][ $step ]['status'] ?? '' );
	}

	private function acquireMigrationLock(): bool {
		self::clearStaleMigrationLock();

		$now = time();
		$token = sha1( uniqid( 'cf7vk_migration_', true ) );

		if ( ! add_option( self::LOCK_OPTION, [ 'token' => $token, 'locked_at' => $now ], '', false ) ) {
			return false;
		}

		$this->lockToken = $token;

		$state = self::getMigrationState();
		$state['lock'] = [ 'token' => $token, 'locked_at' => $now ];
		$state['updated_at'] = $now;
		self::saveMigrationState( $state );

		return true;
	}

	private function releaseMigrationLock(): void {
		$lock = get_option( self::LOCK_OPTION, [] );

		if (
			null === $this->lockToken ||
			! is_array( $lock ) ||
			! isset( $lock['token'] ) ||
			! hash_equals( $this->lockToken, (string) $lock['token'] )
		) {
			$this->lockToken = null;
			return;
		}

		delete_option( self::LOCK_OPTION );
		$this->lockToken = null;

		$state = self::getMigrationState();
		if ( empty( $state ) ) {
			return;
		}

		$state['lock'] = null;
		$state['updated_at'] = time();
		self::saveMigrationState( $state );
	}

	private static function hasActiveMigrationLock(): bool {
		$lock = get_option( self::LOCK_OPTION, [] );

		if ( ! is_array( $lock ) || empty( $lock['locked_at'] ) ) {
			return false;
		}

		return ( time() - (int) $lock['locked_at'] ) < self::LOCK_TTL;
	}

	private static function clearStaleMigrationLock(): void {
		$lock = get_option( self::LOCK_OPTION, [] );

		if ( ! is_array( $lock ) || empty( $lock['locked_at'] ) ) {
			return;
		}

		if ( ( time() - (int) $lock['locked_at'] ) < self::LOCK_TTL ) {
			return;
		}

		delete_option( self::LOCK_OPTION );

		$state = self::getMigrationState();
		if ( empty( $state ) ) {
			return;
		}

		$state['lock'] = null;
		$state['status'] = self::STATUS_FAILED === ( $state['status'] ?? '' )
			? self::STATUS_FAILED
			: self::STATUS_SCHEDULED;
		$state['updated_at'] = time();
		self::saveMigrationState( $state );
	}

	private static function logMigrationError( string $migration_version, array $context, \Throwable $e ): void {
		try {
			( new Logger() )->write(
				[
					'migration_v' => $migration_version,
					'old_v'       => $context['source_version'],
					'new_v'       => $context['target_version'],
					'error_type'  => get_class( $e ),
					'error_code'  => (string) $e->getCode(),
					'error'       => $e->getMessage(),
				],
				'Migration error',
				Logger::LEVEL_CRITICAL
			);
		} catch ( \Throwable $logger_error ) {
			return;
		}
	}

	private static function logMigrationAlreadyDone( string $migration_version, string $old_version, string $new_version ): void {
		try {
			( new Logger() )->write(
				[
					'migration_v' => $migration_version,
					'old_v'       => $old_version,
					'new_v'       => $new_version,
				],
				'Migration already done',
				Logger::LEVEL_ATTENTION
			);
		} catch ( \Throwable $logger_error ) {
			return;
		}
	}

	private static function getPublicErrorType( \Throwable $e ): string {
		$class = get_class( $e );
		$position = strrpos( $class, '\\' );

		return false === $position ? $class : substr( $class, $position + 1 );
	}

	private static function getSupportedPluginBasenames(): array {
		$basenames = [
			self::STABLE_PLUGIN_BASENAME,
			self::LEGACY_PLUGIN_BASENAME,
		];

		if ( defined( 'CF7VK_PLUGIN_NAME' ) ) {
			$basenames[] = CF7VK_PLUGIN_NAME;
		}

		return array_values( array_unique( array_filter( $basenames ) ) );
	}

	private static function getOptionNamesByPrefix( string $prefix ): array {
		global $wpdb;

		if ( ! $wpdb instanceof \wpdb ) {
			return [];
		}

		$result = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT option_name FROM ' . $wpdb->options . ' WHERE option_name LIKE %s',
				$wpdb->esc_like( $prefix ) . '%'
			)
		);

		return is_array( $result ) ? array_values( array_map( 'strval', $result ) ) : [];
	}

	public static function stripPrerelease( string $version ): string {
		return (string) preg_replace( '/[-+].*/', '', $version );
	}
}
