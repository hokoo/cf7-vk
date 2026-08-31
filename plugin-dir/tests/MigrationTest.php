<?php

declare( strict_types=1 );

use iTRON\cf7Vk\Controllers\Migration;

final class MigrationTest extends Cf7vk_TestCase {
	public function testVerifyUpgradingSchedulesMigrationForSinglePluginUpdate(): void {
		update_option( Migration::VERSION_OPTION, '0.1.2', false );

		Migration::getInstance()->verifyUpgrading(
			new stdClass(),
			[
				'action' => 'update',
				'type'   => 'plugin',
				'plugin' => CF7VK_PLUGIN_NAME,
			]
		);

		$events = $this->cronEvents( Migration::MIGRATION_HOOK );

		$this->assertSame( 1, count( $events ) );
		$this->assertSame( false, $events[0]['schedule'] );
		$this->assertSame( [ 'plugin_update', '0.1.2', CF7VK_VERSION ], $events[0]['args'] );
		$this->assertFalse( $this->containsObject( $events[0]['args'] ) );

		$state = Migration::getMigrationState();
		$this->assertSame( Migration::STATUS_SCHEDULED, $state['status'] );
		$this->assertSame( 'plugin_update', $state['reason'] );
		$this->assertSame( '0.1.2', $state['source_version'] );
		$this->assertSame( CF7VK_VERSION, $state['target_version'] );
	}

	public function testVerifyUpgradingSchedulesMigrationForBulkPluginUpdate(): void {
		update_option( Migration::VERSION_OPTION, '0.1.3', false );

		Migration::getInstance()->verifyUpgrading(
			new stdClass(),
			[
				'action'  => 'update',
				'type'    => 'plugin',
				'plugins' => [
					'akismet/akismet.php',
					CF7VK_PLUGIN_NAME,
				],
			]
		);

		$events = $this->cronEvents( Migration::MIGRATION_HOOK );

		$this->assertSame( 1, count( $events ) );
		$this->assertSame( [ 'plugin_update', '0.1.3', CF7VK_VERSION ], $events[0]['args'] );
		$this->assertFalse( $this->containsObject( $events[0]['args'] ) );
	}

	public function testVerifyUpgradingAcceptsLegacyPluginBasenameDuringSlugMigration(): void {
		Migration::getInstance()->verifyUpgrading(
			new stdClass(),
			[
				'action' => 'update',
				'type'   => 'plugin',
				'plugin' => 'cf7-vk/cf7-vk.php',
			]
		);

		$this->assertSame( 1, count( $this->cronEvents( Migration::MIGRATION_HOOK ) ) );
	}

	public function testVerifyUpgradingDoesNotDuplicateMigrationForSameUpdateRequest(): void {
		$hook_extra = [
			'action' => 'update',
			'type'   => 'plugin',
			'plugin' => CF7VK_PLUGIN_NAME,
		];

		Migration::getInstance()->verifyUpgrading( new stdClass(), $hook_extra );
		Migration::getInstance()->verifyUpgrading( new stdClass(), $hook_extra );

		$this->assertSame( 1, count( $this->cronEvents( Migration::MIGRATION_HOOK ) ) );
	}

	public function testVerifyUpgradingIgnoresUnrelatedUpdates(): void {
		Migration::getInstance()->verifyUpgrading(
			new stdClass(),
			[
				'action' => 'update',
				'type'   => 'plugin',
				'plugin' => 'akismet/akismet.php',
			]
		);
		Migration::getInstance()->verifyUpgrading(
			new stdClass(),
			[
				'action' => 'install',
				'type'   => 'plugin',
				'plugin' => CF7VK_PLUGIN_NAME,
			]
		);
		Migration::getInstance()->verifyUpgrading(
			new stdClass(),
			[
				'action' => 'update',
				'type'   => 'theme',
				'plugin' => CF7VK_PLUGIN_NAME,
			]
		);

		$this->assertSame( [], $this->cronEvents( Migration::MIGRATION_HOOK ) );
	}

	public function testEnsureMigrationScheduledBackfillsModernState(): void {
		update_option( Migration::VERSION_OPTION, '0.1.3', false );

		Migration::getInstance()->ensureMigrationScheduled();

		$events = $this->cronEvents( Migration::MIGRATION_HOOK );
		$this->assertSame( 1, count( $events ) );
		$this->assertSame( [ 'self_heal', '0.1.3', CF7VK_VERSION ], $events[0]['args'] );
	}

	public function testEnsureMigrationScheduledBackfillsLegacyOptionState(): void {
		update_option( 'wpcf7_vk_tkn', 'vk1.redacted-test-token', false );
		update_option( Migration::VERSION_OPTION, CF7VK_VERSION, false );

		Migration::getInstance()->ensureMigrationScheduled();

		$events = $this->cronEvents( Migration::MIGRATION_HOOK );
		$this->assertSame( 1, count( $events ) );
		$this->assertSame( [ 'self_heal', '0.0', CF7VK_VERSION ], $events[0]['args'] );
	}

	public function testEnsureMigrationScheduledDoesNotScheduleWithoutEvidence(): void {
		Migration::getInstance()->ensureMigrationScheduled();

		$this->assertSame( [], $this->cronEvents( Migration::MIGRATION_HOOK ) );
	}

	public function testEnsureMigrationScheduledDoesNotDuplicateExistingMigrationEvent(): void {
		update_option( Migration::VERSION_OPTION, '0.1.3', false );
		wp_schedule_single_event( time(), Migration::MIGRATION_HOOK, [ 'self_heal', '0.1.3', CF7VK_VERSION ] );

		Migration::getInstance()->ensureMigrationScheduled();

		$this->assertSame( 1, count( $this->cronEvents( Migration::MIGRATION_HOOK ) ) );
	}

	public function testEnsureMigrationScheduledSkipsDurableCompletedMigration(): void {
		update_option( Migration::VERSION_OPTION, CF7VK_VERSION, false );
		update_option(
			Migration::STATE_OPTION,
			[
				'schema'         => Migration::STATE_SCHEMA,
				'status'         => Migration::STATUS_COMPLETED,
				'reason'         => 'self_heal',
				'source_version' => '0.1.3',
				'target_version' => CF7VK_VERSION,
			],
			false
		);

		Migration::getInstance()->ensureMigrationScheduled();

		$this->assertSame( [], $this->cronEvents( Migration::MIGRATION_HOOK ) );
	}

	public function testLegacyObjectCronContextUsesLegacyVersionOnlyWhenLegacyEvidenceExists(): void {
		$method = new ReflectionMethod( Migration::class, 'normalizeMigrationContext' );
		$method->setAccessible( true );

		$modern = $method->invoke( null, [ new stdClass(), '0.1.3' ] );
		$this->assertSame( 'legacy_cron', $modern['reason'] );
		$this->assertSame( '0.1.3', $modern['source_version'] );
		$this->assertSame( CF7VK_VERSION, $modern['target_version'] );

		update_option( 'wpcf7_vk_last_update_id', 100, false );
		$legacy = $method->invoke( null, [ new stdClass(), CF7VK_VERSION ] );
		$this->assertSame( '0.0', $legacy['source_version'] );
	}

	public function testStaleMigrationLockIsRecoveredAndRepairIsScheduled(): void {
		update_option( Migration::VERSION_OPTION, '0.1.3', false );
		add_option(
			Migration::LOCK_OPTION,
			[
				'token'     => 'stale',
				'locked_at' => time() - Migration::LOCK_TTL - 1,
			],
			'',
			false
		);
		update_option(
			Migration::STATE_OPTION,
			[
				'schema'         => Migration::STATE_SCHEMA,
				'status'         => Migration::STATUS_RUNNING,
				'reason'         => 'self_heal',
				'source_version' => '0.1.3',
				'target_version' => CF7VK_VERSION,
				'attempts'       => 1,
				'current_step'   => '0.1.4',
				'steps'          => [],
				'errors'         => [],
				'lock'           => [
					'token'     => 'stale',
					'locked_at' => time() - Migration::LOCK_TTL - 1,
				],
			],
			false
		);

		Migration::getInstance()->ensureMigrationScheduled();

		$this->assertSame( false, get_option( Migration::LOCK_OPTION ) );
		$this->assertSame( 1, count( $this->cronEvents( Migration::MIGRATION_HOOK ) ) );
		$this->assertSame( Migration::STATUS_SCHEDULED, Migration::getMigrationState()['status'] );
	}

	public function testActiveMigrationLockDefersSelfHealScheduling(): void {
		update_option( Migration::VERSION_OPTION, '0.1.3', false );
		add_option(
			Migration::LOCK_OPTION,
			[
				'token'     => 'active',
				'locked_at' => time(),
			],
			'',
			false
		);

		Migration::getInstance()->ensureMigrationScheduled();

		$this->assertSame( [], $this->cronEvents( Migration::MIGRATION_HOOK ) );
	}

	public function testRunnerDoesNotReleaseLockOwnedByAnotherProcess(): void {
		$migration = Migration::getInstance();
		$acquire = new ReflectionMethod( Migration::class, 'acquireMigrationLock' );
		$acquire->setAccessible( true );
		$release = new ReflectionMethod( Migration::class, 'releaseMigrationLock' );
		$release->setAccessible( true );

		$this->assertTrue( $acquire->invoke( $migration ) );
		update_option(
			Migration::LOCK_OPTION,
			[
				'token'     => 'replacement-owner',
				'locked_at' => time(),
			],
			false
		);

		$release->invoke( $migration );

		$this->assertSame( 'replacement-owner', get_option( Migration::LOCK_OPTION )['token'] );
	}

	public function testScheduleFailureIsRecordedWithoutAdvancingVersion(): void {
		update_option( Migration::VERSION_OPTION, '0.1.3', false );
		add_filter( 'pre_schedule_event', static fn() => false );

		Migration::getInstance()->ensureMigrationScheduled();

		$state = Migration::getMigrationState();
		$this->assertSame( Migration::STATUS_FAILED, $state['status'] );
		$this->assertSame( 'Migration could not be scheduled.', $state['errors'][0]['message'] );
		$this->assertSame( '0.1.3', get_option( Migration::VERSION_OPTION ) );
	}

	public function testFailedMigrationStepDoesNotCompleteAndRetrySkipsCompletedSteps(): void {
		$first_runs = 0;
		$second_runs = 0;
		$second_should_fail = true;

		Migration::registerMigration(
			'0.1.1',
			static function () use ( &$first_runs ): void {
				$first_runs++;
			}
		);

		Migration::registerMigration(
			'0.1.2',
			static function () use ( &$second_runs, &$second_should_fail ): void {
				$second_runs++;
				if ( $second_should_fail ) {
					$second_should_fail = false;
					throw new RuntimeException( 'Synthetic migration failure with vk1.secret-token.' );
				}
			}
		);

		Migration::getInstance()->migrate( 'manual', '0.1.0', CF7VK_VERSION );

		$state = Migration::getMigrationState();
		$this->assertSame( Migration::STATUS_FAILED, $state['status'] );
		$this->assertSame( 1, $first_runs );
		$this->assertSame( 1, $second_runs );
		$this->assertSame( Migration::STATUS_COMPLETED, $state['steps']['0.1.1']['status'] );
		$this->assertSame( Migration::STATUS_FAILED, $state['steps']['0.1.2']['status'] );
		$this->assertSame( false, get_option( 'cf7vk_migration_0.1.2' ) );
		$this->assertSame( false, get_option( Migration::VERSION_OPTION ) );

		Migration::getInstance()->migrate( 'manual', '0.1.0', CF7VK_VERSION );

		$state = Migration::getMigrationState();
		$this->assertSame( Migration::STATUS_COMPLETED, $state['status'] );
		$this->assertSame( 1, $first_runs );
		$this->assertSame( 2, $second_runs );
		$this->assertSame( CF7VK_VERSION, get_option( Migration::VERSION_OPTION ) );

		Migration::getInstance()->migrate( 'manual', '0.1.0', CF7VK_VERSION );

		$this->assertSame( 1, $first_runs );
		$this->assertSame( 2, $second_runs );
	}

	public function testAdminRetrySchedulesOnlyRepairableIdleState(): void {
		update_option(
			Migration::STATE_OPTION,
			[
				'schema'         => Migration::STATE_SCHEMA,
				'status'         => Migration::STATUS_FAILED,
				'reason'         => 'self_heal',
				'source_version' => '0.1.3',
				'target_version' => CF7VK_VERSION,
				'attempts'       => 1,
				'current_step'   => '0.1.4',
				'steps'          => [],
				'errors'         => [],
			],
			false
		);

		$this->assertTrue( Migration::getInstance()->scheduleAdminRetry() );
		$this->assertSame( [ 'admin_retry', '0.1.3', CF7VK_VERSION ], $this->cronEvents( Migration::MIGRATION_HOOK )[0]['args'] );
		$this->assertFalse( Migration::getInstance()->scheduleAdminRetry() );
	}

	public function testAdminRetryRequestRejectsMissingCapability(): void {
		$this->seedRetryableMigrationState();
		$GLOBALS['current_user_can'] = false;
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'cf7vk_retry_migration' );

		try {
			Migration::getInstance()->handleAdminRetry();
			$this->fail( 'Expected missing capability request to die.' );
		} catch ( Cf7vk_WpDie $e ) {
			$this->assertSame( 403, $GLOBALS['wp_die_args']['response'] ?? null );
		}

		$this->assertSame( [], $this->cronEvents( Migration::MIGRATION_HOOK ) );
		$this->assertSame( null, $GLOBALS['checked_admin_referer'] );
	}

	public function testAdminRetryRequestRejectsInvalidNonce(): void {
		$this->seedRetryableMigrationState();
		$_REQUEST['_wpnonce'] = 'invalid';

		try {
			Migration::getInstance()->handleAdminRetry();
			$this->fail( 'Expected invalid nonce request to die.' );
		} catch ( Cf7vk_WpDie $e ) {
			$this->assertSame( 403, $GLOBALS['wp_die_args']['response'] ?? null );
		}

		$this->assertSame( [], $this->cronEvents( Migration::MIGRATION_HOOK ) );
		$this->assertSame( null, $GLOBALS['checked_admin_referer'] );
	}

	public function testAdminRetryRequestSchedulesAndRedirects(): void {
		$this->seedRetryableMigrationState();
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'cf7vk_retry_migration' );
		$GLOBALS['wp_referer'] = 'https://example.test/wp-admin/admin.php?page=wpcf7_vk';

		Migration::getInstance()->handleAdminRetry();

		$this->assertSame( 'cf7vk_retry_migration', $GLOBALS['checked_admin_referer'] );
		$this->assertSame( [ 'admin_retry', '0.1.3', CF7VK_VERSION ], $this->cronEvents( Migration::MIGRATION_HOOK )[0]['args'] );
		$this->assertStringContainsString( 'cf7vk_migration_retry=scheduled', $GLOBALS['wp_safe_redirect_location'] );
	}

	public function testAdminRecoveryStateDoesNotExposeRawExceptionMessage(): void {
		Migration::registerMigration(
			'0.1.1',
			static function (): void {
				throw new RuntimeException( 'raw vk1.secret-token must not be exposed' );
			}
		);

		Migration::getInstance()->migrate( 'manual', '0.1.0', CF7VK_VERSION );
		$recovery = Migration::getAdminRecoveryState();

		$this->assertTrue( $recovery['show_action_button'] );
		$this->assertTrue( $recovery['can_retry'] );
		$this->assertSame( Migration::STATUS_FAILED, $recovery['status'] );
		$this->assertSame( 'Migration step failed.', $recovery['last_error']['message'] );
		$this->assertStringNotContainsString( 'secret-token', wp_json_encode( $recovery ) );
	}

	private function seedRetryableMigrationState(): void {
		update_option(
			Migration::STATE_OPTION,
			[
				'schema'         => Migration::STATE_SCHEMA,
				'status'         => Migration::STATUS_FAILED,
				'reason'         => 'self_heal',
				'source_version' => '0.1.3',
				'target_version' => CF7VK_VERSION,
				'attempts'       => 1,
				'current_step'   => '0.1.4',
				'steps'          => [],
				'errors'         => [],
			],
			false
		);
	}

	private function containsObject( array $value ): bool {
		foreach ( $value as $item ) {
			if ( is_object( $item ) ) {
				return true;
			}

			if ( is_array( $item ) && $this->containsObject( $item ) ) {
				return true;
			}
		}

		return false;
	}
}
