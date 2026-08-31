<?php
/*
 * Plugin Name: Message Bridge for Contact Form 7 and VK
 * Description: Sends Contact Form 7 submissions to VK dialogs through configurable message bridge channels.
 * Author: Hokku
 * Version: 1.0.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: message-bridge-for-contact-form-7-and-vk
 * Domain Path: /languages
 * Requires PHP: 8.1
 * Requires Plugins: contact-form-7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use iTRON\cf7Vk\Client;
use iTRON\cf7Vk\Controllers\CPT;
use iTRON\cf7Vk\Controllers\Migration;
use iTRON\cf7Vk\Maintenance;
use iTRON\cf7Vk\Settings;

$cf7vk_plugin_basename = plugin_basename( __FILE__ );

if ( ! defined( 'CF7VK_PLUGIN_NAME' ) ) {
	define( 'CF7VK_PLUGIN_NAME', $cf7vk_plugin_basename );
}

if ( ! defined( 'CF7VK_VERSION' ) ) {
	define( 'CF7VK_VERSION', '1.0.0' );
}

if ( ! defined( 'CF7VK_FILE' ) ) {
	define( 'CF7VK_FILE', __FILE__ );
}

require __DIR__ . '/vendor/autoload.php';

add_action( 'init', [ Client::getInstance(), 'init' ], 15 );
CPT::get_instance()->init();
Settings::init();
Migration::init();
Maintenance::init();

register_activation_hook( __FILE__, [ Maintenance::class, 'activate' ] );
register_deactivation_hook( __FILE__, [ Maintenance::class, 'deactivate' ] );
register_uninstall_hook( __FILE__, [ Maintenance::class, 'uninstall' ] );

$cf7vk_distribution_bootstrap = __DIR__ . '/lib/Distribution/GitHubReleaseChannel.php';

if ( is_readable( $cf7vk_distribution_bootstrap ) ) {
	require_once $cf7vk_distribution_bootstrap;
	\iTRON\cf7Vk\Distribution\GitHubReleaseChannel::init();
}

add_action( 'in_plugin_update_message-' . $cf7vk_plugin_basename, 'cf7vk_plugin_update_message', 10, 2 );

if ( ! function_exists( 'cf7vk_plugin_update_message' ) ) {
	function cf7vk_plugin_update_message( $data, $response ): void {
		if ( ! isset( $data['upgrade_notice'] ) ) {
			return;
		}

		printf(
			'<div class="update-message">%s</div>',
			wp_kses_post( wpautop( $data['upgrade_notice'] ) )
		);
	}
}
