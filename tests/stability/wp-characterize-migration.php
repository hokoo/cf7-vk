<?php
/**
 * Runs migration characterization checks in an ephemeral WordPress site.
 *
 * Intended to be executed with WP-CLI:
 * wp eval-file /e1-tests/wp-characterize-migration.php 0.1.4 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

global $wpdb;

$source_version = is_scalar( $args[0] ?? null ) ? trim( (string) $args[0] ) : '0.0';
$target_version = is_scalar( $args[1] ?? null ) ? trim( (string) $args[1] ) : '';

if ( '' === $source_version ) {
	$source_version = '0.0';
}

if ( '' === $target_version ) {
	$target_version = defined( 'CF7VK_VERSION' ) ? CF7VK_VERSION : '1.0.0';
}

function cf7vk_e2_public_migration_state(): array {
	$state = get_option( 'cf7vk_migration_state', [] );
	$state = is_array( $state ) ? $state : [];
	$steps = [];

	foreach ( (array) ( $state['steps'] ?? [] ) as $step => $step_state ) {
		$steps[ (string) $step ] = is_array( $step_state )
			? [
				'status'   => (string) ( $step_state['status'] ?? '' ),
				'attempts' => (int) ( $step_state['attempts'] ?? 0 ),
			]
			: [
				'status'   => '',
				'attempts' => 0,
			];
	}

	$errors = is_array( $state['errors'] ?? null ) ? $state['errors'] : [];
	$last_error = end( $errors );

	if ( ! is_array( $last_error ) ) {
		$last_error = [];
	}

	return [
		'version_option' => get_option( 'cf7vk_version', null ),
		'state_exists'   => ! empty( $state ),
		'schema'         => (int) ( $state['schema'] ?? 0 ),
		'status'         => (string) ( $state['status'] ?? '' ),
		'reason'         => (string) ( $state['reason'] ?? '' ),
		'source_version' => (string) ( $state['source_version'] ?? '' ),
		'target_version' => (string) ( $state['target_version'] ?? '' ),
		'attempts'       => (int) ( $state['attempts'] ?? 0 ),
		'current_step'   => (string) ( $state['current_step'] ?? '' ),
		'steps'          => $steps,
		'error_count'    => count( $errors ),
		'last_error'     => [
			'step' => (string) ( $last_error['step'] ?? '' ),
			'code' => (string) ( $last_error['code'] ?? '' ),
			'type' => (string) ( $last_error['type'] ?? '' ),
		],
		'lock_exists'    => false !== get_option( 'cf7vk_migration_lock', false ),
	];
}

function cf7vk_e2_option_names( string $prefix ): array {
	global $wpdb;

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$names = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name",
			$wpdb->esc_like( $prefix ) . '%'
		)
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	return array_values( array_map( 'strval', is_array( $names ) ? $names : [] ) );
}

function cf7vk_e2_table_exists( string $table ): bool {
	global $wpdb;

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	return (bool) $exists;
}

function cf7vk_e2_table_count( string $table ): ?int {
	global $wpdb;

	if ( ! cf7vk_e2_table_exists( $table ) ) {
		return null;
	}

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" );
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}

function cf7vk_e2_post_count( string $post_type ): int {
	global $wpdb;

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
			$post_type
		)
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}

function cf7vk_e2_connection_summary(): array {
	global $wpdb;

	$table = $wpdb->prefix . 'post_connections_cf7_vk';
	$meta_table = $wpdb->prefix . 'post_connections_meta_cf7_vk';
	$summary = [
		'exists'               => cf7vk_e2_table_exists( $table ),
		'total'                => 0,
		'meta_total'           => cf7vk_e2_table_count( $meta_table ),
		'by_relation'          => [],
		'duplicate_signatures' => [],
	];

	if ( ! $summary['exists'] ) {
		$summary['meta_total'] = $summary['meta_total'] ?? 0;
		return $summary;
	}

	$summary['total'] = cf7vk_e2_table_count( $table ) ?? 0;

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$relation_counts = $wpdb->get_results( "SELECT relation, COUNT(*) AS total FROM `{$table}` GROUP BY relation ORDER BY relation" );
	$duplicates = $wpdb->get_results( "SELECT relation, `from`, `to`, COUNT(*) AS total FROM `{$table}` GROUP BY relation, `from`, `to` HAVING total > 1 ORDER BY relation, `from`, `to`" );
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	foreach ( is_array( $relation_counts ) ? $relation_counts : [] as $row ) {
		$summary['by_relation'][ (string) $row->relation ] = (int) $row->total;
	}

	foreach ( is_array( $duplicates ) ? $duplicates : [] as $row ) {
		$summary['duplicate_signatures'][] = [
			'relation' => (string) $row->relation,
			'from'     => (int) $row->from,
			'to'       => (int) $row->to,
			'total'    => (int) $row->total,
		];
	}

	return $summary;
}

function cf7vk_e2_cron_hook_summary( string $hook ): array {
	$summary = [
		'total'      => 0,
		'recurring'  => 0,
		'single'     => 0,
		'duplicates' => [],
	];
	$seen = [];

	foreach ( _get_cron_array() as $hooks ) {
		if ( empty( $hooks[ $hook ] ) || ! is_array( $hooks[ $hook ] ) ) {
			continue;
		}

		foreach ( $hooks[ $hook ] as $event ) {
			$schedule = $event['schedule'] ?? false;
			$args = $event['args'] ?? [];
			$signature = $hook . '|' . (string) $schedule . '|' . md5( wp_json_encode( $args ) );

			$summary['total']++;
			if ( $schedule ) {
				$summary['recurring']++;
			} else {
				$summary['single']++;
			}

			if ( isset( $seen[ $signature ] ) ) {
				$summary['duplicates'][] = $signature;
			}
			$seen[ $signature ] = true;
		}
	}

	return $summary;
}

function cf7vk_e2_fingerprint_source(): array {
	global $wpdb;

	return [
		'version'     => get_option( 'cf7vk_version', null ),
		'options'     => [
			'cf7vk_'           => cf7vk_e2_option_names( 'cf7vk_' ),
			'wpcf7_vk_'        => cf7vk_e2_option_names( 'wpcf7_vk_' ),
			'vk_notifications_' => cf7vk_e2_option_names( 'vk_notifications_' ),
		],
		'post_counts' => [
			'cf7vk_bot'         => cf7vk_e2_post_count( 'cf7vk_bot' ),
			'cf7vk_chat'        => cf7vk_e2_post_count( 'cf7vk_chat' ),
			'cf7vk_channel'     => cf7vk_e2_post_count( 'cf7vk_channel' ),
			'wpcf7_contact_form' => cf7vk_e2_post_count( 'wpcf7_contact_form' ),
		],
		'tables'      => [
			'post_connections_cf7_vk'      => cf7vk_e2_table_count( $wpdb->prefix . 'post_connections_cf7_vk' ),
			'post_connections_meta_cf7_vk' => cf7vk_e2_table_count( $wpdb->prefix . 'post_connections_meta_cf7_vk' ),
			'cf7vk_log'                    => cf7vk_e2_table_count( $wpdb->prefix . 'cf7vk_log' ),
		],
		'relations'   => cf7vk_e2_connection_summary(),
		'migration'   => cf7vk_e2_public_migration_state(),
		'cron'        => [
			'cf7vk_cleanup'    => cf7vk_e2_cron_hook_summary( 'cf7vk_cleanup' ),
			'cf7vk_migrations' => cf7vk_e2_cron_hook_summary( 'cf7vk_migrations' ),
		],
	];
}

function cf7vk_e2_fingerprint(): array {
	$source = cf7vk_e2_fingerprint_source();

	return [
		'sha256' => hash( 'sha256', wp_json_encode( $source ) ),
		'source' => $source,
	];
}

function cf7vk_e2_fixture_expectations(): array {
	$expectations = get_option( 'cf7vk_e1_fixture_expectations', [] );

	return is_array( $expectations ) ? $expectations : [];
}

function cf7vk_e2_assert_fixture_counts( array $fingerprint, array $expectations, string $label, array &$errors ): void {
	if ( empty( $expectations ) ) {
		return;
	}

	$source = $fingerprint['source'] ?? [];
	$post_counts = is_array( $source['post_counts'] ?? null ) ? $source['post_counts'] : [];
	$relations = is_array( $source['relations'] ?? null ) ? $source['relations'] : [];
	$relation_counts = is_array( $expectations['relation_counts'] ?? null ) ? $expectations['relation_counts'] : [];

	$expected_posts = [
		'cf7vk_bot'         => (int) ( $expectations['bot_count'] ?? 0 ),
		'cf7vk_chat'        => (int) ( $expectations['chat_count'] ?? 0 ),
		'cf7vk_channel'     => (int) ( $expectations['channel_count'] ?? 0 ),
		'wpcf7_contact_form' => (int) ( $expectations['form_count'] ?? 0 ) + 1,
	];

	foreach ( $expected_posts as $post_type => $expected ) {
		if ( $expected !== (int) ( $post_counts[ $post_type ] ?? -1 ) ) {
			$errors[] = "{$label}: post count mismatch for {$post_type}.";
		}
	}

	$expected_relation_total = (int) ( $expectations['relation_count_total'] ?? 0 ) + (int) ( $expectations['damaged_relation_count'] ?? 0 );
	if ( $expected_relation_total !== (int) ( $relations['total'] ?? -1 ) ) {
		$errors[] = "{$label}: relation total mismatch.";
	}

	foreach ( $relation_counts as $relation => $expected ) {
		$expected_count = (int) $expected;

		if ( (int) ( $relations['by_relation'][ $relation ] ?? 0 ) < $expected_count ) {
			$errors[] = "{$label}: relation count for {$relation} is below expected fixture count.";
		}
	}

	if ( ! empty( $relations['duplicate_signatures'] ) ) {
		$errors[] = "{$label}: duplicate relation signatures exist.";
	}
}

function cf7vk_e2_error( array $result ): void {
	if ( class_exists( 'WP_CLI' ) ) {
		WP_CLI::error( wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	}

	fwrite( STDERR, wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL );
	exit( 1 );
}

$migration_class = 'iTRON\\cf7Vk\\Controllers\\Migration';

if ( ! class_exists( $migration_class ) ) {
	cf7vk_e2_error(
		[
			'ok'    => false,
			'error' => 'Migration class is not loaded.',
			'class' => $migration_class,
		]
	);
}

$before = [
	'migration'   => cf7vk_e2_public_migration_state(),
	'fingerprint' => cf7vk_e2_fingerprint(),
];
$expectations = cf7vk_e2_fixture_expectations();
$repair_before = null;
$repair_after = null;

$migration_class::getInstance()->migrate( 'characterization', $source_version, $target_version );
$after_first = [
	'migration'   => cf7vk_e2_public_migration_state(),
	'fingerprint' => cf7vk_e2_fingerprint(),
];

$migration_class::getInstance()->migrate( 'characterization', $source_version, $target_version );
$after_second = [
	'migration'   => cf7vk_e2_public_migration_state(),
	'fingerprint' => cf7vk_e2_fingerprint(),
];

$errors = [];

if ( 'completed' !== $after_first['migration']['status'] ) {
	$errors[] = 'Migration did not complete on first characterization run.';
}

if ( $target_version !== (string) $after_first['migration']['version_option'] ) {
	$errors[] = 'Version option was not advanced to target version after successful migration.';
}

if ( $after_first['migration']['error_count'] > 0 ) {
	$errors[] = 'Migration state contains errors after successful characterization run.';
}

if ( $after_first['fingerprint']['sha256'] !== $after_second['fingerprint']['sha256'] ) {
	$errors[] = 'Second migration run changed the characterized state fingerprint.';
}

if ( $after_second['migration']['attempts'] !== $after_first['migration']['attempts'] ) {
	$errors[] = 'Second migration run was not idempotent; attempts changed.';
}

cf7vk_e2_assert_fixture_counts( $after_first['fingerprint'], $expectations, 'after_first', $errors );
cf7vk_e2_assert_fixture_counts( $after_second['fingerprint'], $expectations, 'after_second', $errors );

$maintenance_class = 'iTRON\\cf7Vk\\Maintenance';

if ( class_exists( $maintenance_class ) ) {
	$repair_before = $maintenance_class::buildRepairPlan();
	$expected_repair_connections = (int) ( $expectations['expect_repair_connections'] ?? 0 );

	if ( $expected_repair_connections > 0 ) {
		if ( (int) ( $repair_before['planned']['delete_connections'] ?? 0 ) < $expected_repair_connections ) {
			$errors[] = 'Repair dry-run did not detect expected damaged connections.';
		}

		$repair_after = $maintenance_class::runRepair( $maintenance_class::REPAIR_MODE_APPLY );

		if ( (int) ( $repair_after['applied']['deleted_connections'] ?? 0 ) < $expected_repair_connections ) {
			$errors[] = 'Repair apply did not delete expected damaged connections.';
		}
	} elseif ( ! empty( $repair_before['planned']['delete_connections'] ) ) {
		$errors[] = 'Repair dry-run planned unexpected connection deletions for a healthy fixture.';
	}
}

$result = [
	'ok'             => empty( $errors ),
	'source_version' => $source_version,
	'target_version' => $target_version,
	'fixture_expectations' => $expectations,
	'repair_before'  => $repair_before,
	'repair_after'   => $repair_after,
	'before'         => $before,
	'after_first'    => $after_first,
	'after_second'   => $after_second,
	'errors'         => $errors,
];

if ( ! empty( $errors ) ) {
	cf7vk_e2_error( $result );
}

echo wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
