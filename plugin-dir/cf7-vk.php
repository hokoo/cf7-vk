<?php
/*
 * Plugin Name: VK Notifications for Contact Form 7
* Description: Routes Contact Form 7 submissions through configurable VK delivery channels.
* Author: Hokku
* Version: 0.1.0
* License: GPL v2 or later
* License URI: https://www.gnu.org/licenses/gpl-2.0.html
* Text Domain: vk-notifications-for-contact-form-7
* Domain Path: /languages
* Requires Plugins: contact-form-7
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use iTRON\cf7Vk\Client;
use iTRON\cf7Vk\Controllers\CPT;
use iTRON\cf7Vk\Controllers\Migration;
use iTRON\cf7Vk\Settings;

define( 'CF7VK_PLUGIN_NAME', plugin_basename( __FILE__ ) );

const CF7VK_VERSION = '0.1.0';
const CF7VK_FILE = __FILE__;

require __DIR__ . '/vendor/autoload.php';

add_action( 'init', [ Client::getInstance(), 'init' ], 15 );
CPT::get_instance()->init();
Settings::init();
Migration::init();

$cf7vk_distribution_bootstrap = __DIR__ . '/lib/Distribution/GitHubReleaseChannel.php';

if ( is_readable( $cf7vk_distribution_bootstrap ) ) {
	require_once $cf7vk_distribution_bootstrap;
	\iTRON\cf7Vk\Distribution\GitHubReleaseChannel::init();
}

add_action( 'in_plugin_update_message-' . CF7VK_PLUGIN_NAME, 'cf7vk_plugin_update_message', 10, 2 );

function cf7vk_plugin_update_message( $data, $response ): void {
	if ( ! isset( $data['upgrade_notice'] ) ) {
		return;
	}

	printf(
		'<div class="update-message">%s</div>',
		wp_kses_post( wpautop( $data['upgrade_notice'] ) )
	);
}
