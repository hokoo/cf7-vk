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
	public const VERSION_OPTION = 'cf7vk_version';

	private static Migration $instance;

	protected function __construct() {}
	protected function __clone() {}

	public function __wakeup() {
		trigger_error(
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
		add_action( self::MIGRATION_HOOK, [ self::getInstance(), 'migrate' ], 10, 2 );
	}

	public function verifyUpgrading( $upgrader, array $hook_extra ): void {
		if (
			'update' !== ( $hook_extra['action'] ?? '' ) ||
			'plugin' !== ( $hook_extra['type'] ?? '' )
		) {
			return;
		}

		if (
			empty( $hook_extra['plugins'] ) ||
			! is_array( $hook_extra['plugins'] ) ||
			! in_array( CF7VK_PLUGIN_NAME, $hook_extra['plugins'], true )
		) {
			return;
		}

		wp_schedule_single_event(
			time() + 30,
			self::MIGRATION_HOOK,
			[
				$upgrader,
				CF7VK_VERSION,
			]
		);
	}

	public function migrate( $upgrader, $pre_version ): void {
		$this->loadMigrations();
		update_option( self::VERSION_OPTION, CF7VK_VERSION );

		do_action( 'cf7vk_migrations', $pre_version, CF7VK_VERSION, $upgrader );
	}

	public static function registerMigration( string $migration_version, callable $migration_function ): void {
		add_action(
			'cf7vk_migrations',
			static function ( $old_version, $new_version, $upgrader ) use ( $migration_version, $migration_function ) {
				if (
					version_compare( (string) $old_version, $migration_version, '<' ) &&
					version_compare( self::stripPrerelease( (string) $new_version ), $migration_version, '>=' )
				) {
					do_action( 'cf7vk_migration', $migration_version, $old_version, $new_version );

					try {
						call_user_func( $migration_function, $old_version, $new_version, $upgrader );
					} catch ( \Exception|\Error $e ) {
						( new Logger() )->write(
							[
								'migration_v' => $migration_version,
								'old_v' => $old_version,
								'new_v' => $new_version,
								'error' => $e->getMessage(),
							],
							'Migration error',
							Logger::LEVEL_CRITICAL
						);
					}

					update_option(
						'cf7vk_migration_' . $migration_version,
						compact( 'old_version', 'new_version' ),
						false
					);
				}
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

	public static function stripPrerelease( string $version ): string {
		return (string) preg_replace( '/[-+].*/', '', $version );
	}
}
