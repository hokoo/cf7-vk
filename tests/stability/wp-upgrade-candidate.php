<?php
/**
 * Upgrades an active baseline plugin through WordPress' single-plugin updater path.
 *
 * Intended to be executed with WP-CLI:
 * wp eval-file /e1-tests/wp-upgrade-candidate.php /artifacts/candidate.zip baseline/plugin.php candidate/plugin.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$package = $args[0] ?? '';
$plugin = $args[1] ?? 'message-bridge-for-contact-form-7-and-vk/cf7-vk.php';
$candidate_plugin = $args[2] ?? 'message-bridge-for-contact-form-7-and-vk/cf7-vk.php';

if ( ! is_string( $package ) || ! file_exists( $package ) ) {
	WP_CLI::error( 'Candidate package does not exist: ' . (string) $package );
}

if ( ! is_string( $plugin ) || '' === trim( $plugin ) ) {
	WP_CLI::error( 'Baseline plugin basename is empty.' );
}

if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

if ( ! is_plugin_active( $plugin ) ) {
	WP_CLI::error( 'The baseline plugin must be active before upgrade: ' . $plugin );
}

$updates = get_site_transient( 'update_plugins' );

if ( ! is_object( $updates ) ) {
	$updates = new stdClass();
}

if ( ! isset( $updates->response ) || ! is_array( $updates->response ) ) {
	$updates->response = [];
}

$updates->response[ $plugin ] = (object) [
	'id'          => 'w.org/plugins/message-bridge-for-contact-form-7-and-vk',
	'slug'        => 'message-bridge-for-contact-form-7-and-vk',
	'plugin'      => $plugin,
	'new_version' => 'stability-candidate',
	'package'     => $package,
];

set_site_transient( 'update_plugins', $updates );

$upgrader = new Plugin_Upgrader( new Automatic_Upgrader_Skin() );
$result = $upgrader->upgrade(
	$plugin,
	[
		'clear_update_cache' => false,
	]
);

if ( is_wp_error( $result ) ) {
	WP_CLI::error( $result );
}

if ( true !== $result ) {
	WP_CLI::error( 'WordPress single-plugin upgrade did not complete.' );
}

wp_clean_plugins_cache( true );

if ( ! is_plugin_active( $candidate_plugin ) ) {
	$activation = activate_plugin( $candidate_plugin );

	if ( is_wp_error( $activation ) ) {
		WP_CLI::error( $activation );
	}
}

echo wp_json_encode(
	[
		'baseline_plugin'  => $plugin,
		'candidate_plugin' => $candidate_plugin,
		'package'          => $package,
		'active'           => is_plugin_active( $candidate_plugin ),
		'result'           => true,
	],
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
);
