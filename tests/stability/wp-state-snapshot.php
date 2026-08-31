<?php
/**
 * Prints a JSON state snapshot for E1 lifecycle smoke tests.
 *
 * Intended to be executed with WP-CLI:
 * wp eval-file /e1-tests/wp-state-snapshot.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

global $wpdb;

if ( ! function_exists( 'get_plugin_data' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$plugin_candidates = [
	'message-bridge-for-contact-form-7-and-vk/cf7-vk.php',
	'cf7-vk/cf7-vk.php',
];
$plugin_file = '';
$plugin_basename = '';

foreach ( $plugin_candidates as $candidate ) {
	if ( is_plugin_active( $candidate ) ) {
		$plugin_basename = $candidate;
		$plugin_file = WP_PLUGIN_DIR . '/' . $candidate;
		break;
	}
}

if ( '' === $plugin_file ) {
	foreach ( $plugin_candidates as $candidate ) {
		$file = WP_PLUGIN_DIR . '/' . $candidate;
		if ( file_exists( $file ) ) {
			$plugin_basename = $candidate;
			$plugin_file = $file;
			break;
		}
	}
}

$plugin_data = file_exists( $plugin_file ) ? get_plugin_data( $plugin_file, false, false ) : [];
$plugin = [
	'basename'    => $plugin_basename,
	'file_exists' => '' !== $plugin_file && file_exists( $plugin_file ),
	'active'      => '' !== $plugin_basename && is_plugin_active( $plugin_basename ),
	'version'     => $plugin_data['Version'] ?? null,
	'name'        => $plugin_data['Name'] ?? null,
	'text_domain' => $plugin_data['TextDomain'] ?? null,
];
$cron_array = _get_cron_array();
$cron_hooks = [
	'cf7vk_cleanup',
	'cf7vk_migrations',
];
$cron = [];

foreach ( $cron_hooks as $hook ) {
	$cron[ $hook ] = [
		'total'      => 0,
		'recurring'  => 0,
		'single'     => 0,
		'events'     => [],
		'duplicates' => [],
	];
}

foreach ( $cron_array as $timestamp => $hooks ) {
	foreach ( $cron_hooks as $hook ) {
		if ( empty( $hooks[ $hook ] ) || ! is_array( $hooks[ $hook ] ) ) {
			continue;
		}

		foreach ( $hooks[ $hook ] as $event_key => $event ) {
			$schedule = $event['schedule'] ?? false;
			$args = $event['args'] ?? [];
			$signature = $hook . '|' . (string) $schedule . '|' . md5( wp_json_encode( $args ) );

			$cron[ $hook ]['total']++;
			if ( $schedule ) {
				$cron[ $hook ]['recurring']++;
			} else {
				$cron[ $hook ]['single']++;
			}

			if ( isset( $cron[ $hook ]['seen'][ $signature ] ) ) {
				$cron[ $hook ]['duplicates'][] = $signature;
			}
			$cron[ $hook ]['seen'][ $signature ] = true;

			$cron[ $hook ]['events'][] = [
				'timestamp' => (int) $timestamp,
				'gmt'       => gmdate( 'c', (int) $timestamp ),
				'schedule'  => $schedule ?: null,
				'args'      => $args,
				'event_key' => (string) $event_key,
			];
		}
	}
}

foreach ( $cron_hooks as $hook ) {
	unset( $cron[ $hook ]['seen'] );
}

$option_prefixes = [
	'cf7vk_',
	'wpcf7_vk_',
	'vk_notifications_',
];
$options = [];
$fixture_expectations = get_option( 'cf7vk_e1_fixture_expectations', [] );

if ( ! is_array( $fixture_expectations ) ) {
	$fixture_expectations = [];
}

foreach ( $option_prefixes as $prefix ) {
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$names = $wpdb->get_col(
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s ORDER BY option_name",
			$wpdb->esc_like( $prefix ) . '%'
		)
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	$options[ $prefix ] = [
		'count' => is_array( $names ) ? count( $names ) : 0,
		'names' => array_values( array_map( 'strval', is_array( $names ) ? $names : [] ) ),
	];
}

$post_types = [
	'cf7vk_bot',
	'cf7vk_chat',
	'cf7vk_channel',
	'wpcf7_contact_form',
];
$post_counts = [];

foreach ( $post_types as $post_type ) {
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$post_counts[ $post_type ] = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s",
			$post_type
		)
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}

$table_names = [
	'post_connections_cf7_vk'      => $wpdb->prefix . 'post_connections_cf7_vk',
	'post_connections_meta_cf7_vk' => $wpdb->prefix . 'post_connections_meta_cf7_vk',
	'cf7vk_log'                    => $wpdb->prefix . 'cf7vk_log',
];
$tables = [];

foreach ( $table_names as $key => $table_name ) {
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$exists = $wpdb->get_var(
		$wpdb->prepare(
			'SHOW TABLES LIKE %s',
			$table_name
		)
	);
	$count = $exists ? (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table_name}`" ) : null;
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	$tables[ $key ] = [
		'name'   => $table_name,
		'exists' => (bool) $exists,
		'count'  => $count,
	];
}

$relations = [
	'exists'               => false,
	'total'                => 0,
	'meta_total'           => 0,
	'by_relation'          => [],
	'duplicate_signatures' => [],
];
$connection_table = $table_names['post_connections_cf7_vk'];
$connection_meta_table = $table_names['post_connections_meta_cf7_vk'];

if ( ! empty( $tables['post_connections_cf7_vk']['exists'] ) ) {
	$relations['exists'] = true;

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$relations['total'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$connection_table}`" );
	$relation_counts = $wpdb->get_results( "SELECT relation, COUNT(*) AS total FROM `{$connection_table}` GROUP BY relation ORDER BY relation" );
	$duplicates = $wpdb->get_results( "SELECT relation, `from`, `to`, COUNT(*) AS total FROM `{$connection_table}` GROUP BY relation, `from`, `to` HAVING total > 1 ORDER BY relation, `from`, `to`" );
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

	foreach ( is_array( $relation_counts ) ? $relation_counts : [] as $row ) {
		$relations['by_relation'][ (string) $row->relation ] = (int) $row->total;
	}

	foreach ( is_array( $duplicates ) ? $duplicates : [] as $row ) {
		$relations['duplicate_signatures'][] = [
			'relation' => (string) $row->relation,
			'from'     => (int) $row->from,
			'to'       => (int) $row->to,
			'total'    => (int) $row->total,
		];
	}
}

if ( ! empty( $tables['post_connections_meta_cf7_vk']['exists'] ) ) {
	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	$relations['meta_total'] = (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$connection_meta_table}`" );
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}

$migration_state = get_option( 'cf7vk_migration_state', [] );
$migration_state = is_array( $migration_state ) ? $migration_state : [];
$migration_steps = [];

foreach ( (array) ( $migration_state['steps'] ?? [] ) as $step => $step_state ) {
	$migration_steps[ (string) $step ] = is_array( $step_state )
		? (string) ( $step_state['status'] ?? '' )
		: '';
}

$migration_errors = is_array( $migration_state['errors'] ?? null ) ? $migration_state['errors'] : [];
$last_migration_error = end( $migration_errors );

if ( ! is_array( $last_migration_error ) ) {
	$last_migration_error = [];
}

$migration = [
	'version_option' => get_option( 'cf7vk_version', null ),
	'state_exists'   => ! empty( $migration_state ),
	'schema'         => (int) ( $migration_state['schema'] ?? 0 ),
	'status'         => (string) ( $migration_state['status'] ?? '' ),
	'reason'         => (string) ( $migration_state['reason'] ?? '' ),
	'source_version' => (string) ( $migration_state['source_version'] ?? '' ),
	'target_version' => (string) ( $migration_state['target_version'] ?? '' ),
	'attempts'       => (int) ( $migration_state['attempts'] ?? 0 ),
	'current_step'   => (string) ( $migration_state['current_step'] ?? '' ),
	'step_statuses'  => $migration_steps,
	'error_count'    => count( $migration_errors ),
	'last_error'     => [
		'step' => (string) ( $last_migration_error['step'] ?? '' ),
		'code' => (string) ( $last_migration_error['code'] ?? '' ),
		'type' => (string) ( $last_migration_error['type'] ?? '' ),
	],
	'lock_exists'    => false !== get_option( 'cf7vk_migration_lock', false ),
];
$fingerprint_source = [
	'plugin'      => [
		'basename'    => $plugin['basename'],
		'active'      => $plugin['active'],
		'version'     => $plugin['version'],
		'text_domain' => $plugin['text_domain'],
	],
	'cron'        => [
		'cf7vk_cleanup'    => [
			'total'      => $cron['cf7vk_cleanup']['total'],
			'recurring'  => $cron['cf7vk_cleanup']['recurring'],
			'single'     => $cron['cf7vk_cleanup']['single'],
			'duplicates' => $cron['cf7vk_cleanup']['duplicates'],
		],
		'cf7vk_migrations' => [
			'total'      => $cron['cf7vk_migrations']['total'],
			'recurring'  => $cron['cf7vk_migrations']['recurring'],
			'single'     => $cron['cf7vk_migrations']['single'],
			'duplicates' => $cron['cf7vk_migrations']['duplicates'],
		],
	],
	'options'     => $options,
	'fixture'     => $fixture_expectations,
	'post_counts' => $post_counts,
	'tables'      => array_map(
		static fn( array $table ): array => [
			'exists' => $table['exists'],
			'count'  => $table['count'],
		],
		$tables
	),
	'relations'   => $relations,
	'migration'   => $migration,
];

echo wp_json_encode(
	[
		'captured_at_gmt' => gmdate( 'c' ),
		'wordpress'       => [
			'version' => get_bloginfo( 'version' ),
			'url'     => home_url(),
		],
		'php_version'     => PHP_VERSION,
		'plugin'          => $plugin,
		'active_plugins'  => array_values( array_map( 'strval', (array) get_option( 'active_plugins', [] ) ) ),
		'cron'            => $cron,
		'options'         => $options,
		'fixture_expectations' => $fixture_expectations,
		'post_counts'     => $post_counts,
		'tables'          => $tables,
		'relations'       => $relations,
		'migration'       => $migration,
		'fingerprints'    => [
			'lifecycle' => hash( 'sha256', wp_json_encode( $fingerprint_source ) ),
		],
	],
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
);
