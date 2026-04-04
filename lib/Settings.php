<?php

namespace iTRON\cf7Vk;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use iTRON\cf7Vk\Controllers\CPT;

class Settings {
	public const PAGE_SLUG = 'wpcf7_vk';

	public static function init(): void {
		add_action(
			'admin_menu',
			static function () {
				add_submenu_page(
					'wpcf7',
					__( 'CF7 VK', 'cf7-vk' ),
					__( 'CF7 VK', 'cf7-vk' ),
					self::getCaps(),
					self::PAGE_SLUG,
					[ self::class, 'renderPage' ]
				);
			}
		);
		add_action( 'current_screen', [ self::class, 'initScreen' ], 999 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'admin_enqueue_scripts' ] );
	}

	public static function getCaps(): string {
		return CPT::get_instance()->cf7_orig_capabilities['edit_posts'] ?? 'manage_options';
	}

	public static function renderPage(): void {
		echo '<div id="cf7-vk-container"><div class="wrap">';
		echo wp_kses_post( self::getSettingsContent() );
		echo '</div></div>';
	}

	public static function initScreen(): void {
		$screen = get_current_screen();

		if ( ! $screen || false === strpos( $screen->id, self::PAGE_SLUG ) ) {
			return;
		}

		do_action( 'cf7vk_settings_screen' );
	}

	public static function admin_enqueue_scripts(): void {
		if ( ! did_action( 'cf7vk_settings_screen' ) ) {
			return;
		}

		$main_css = self::pluginDir() . '/react/build/static/css/main.css';
		$main_js = self::pluginDir() . '/react/build/static/js/main.js';

		if ( file_exists( $main_css ) ) {
			wp_enqueue_style(
				'cf7-vk-admin-styles',
				self::pluginUrl() . '/react/build/static/css/main.css',
				null,
				CF7VK_VERSION
			);
		}

		if ( file_exists( $main_js ) ) {
			wp_enqueue_script(
				'cf7-vk-admin',
				self::pluginUrl() . '/react/build/static/js/main.js',
				[ 'wp-i18n' ],
				CF7VK_VERSION,
				true
			);
			wp_set_script_translations( 'cf7-vk-admin', 'cf7-vk' );
			wp_localize_script( 'cf7-vk-admin', 'cf7VkData', self::getScriptData() );
		}
	}

	public static function pluginUrl(): string {
		return untrailingslashit( plugins_url( '/', CF7VK_FILE ) );
	}

	public static function pluginDir(): string {
		return untrailingslashit( plugin_dir_path( CF7VK_FILE ) );
	}

	private static function getSettingsContent(): string {
		$file = self::pluginDir() . '/react/build/settings-content.html';

		if ( file_exists( $file ) ) {
			$content = file_get_contents( $file );

			if ( false !== $content ) {
				return $content;
			}
		}

		return '<div id="settings-content"></div>';
	}

	private static function getScriptData(): array {
		return [
			'routes' => [
				'relations' => [
					'bot2channel' => get_rest_url( null, 'wp-connections/v1/client/' . Client::WPCONNECTIONS_CLIENT . '/relation/' . Client::BOT2CHANNEL . '/' ),
					'chat2channel' => get_rest_url( null, 'wp-connections/v1/client/' . Client::WPCONNECTIONS_CLIENT . '/relation/' . Client::CHAT2CHANNEL . '/' ),
					'form2channel' => get_rest_url( null, 'wp-connections/v1/client/' . Client::WPCONNECTIONS_CLIENT . '/relation/' . Client::FORM2CHANNEL . '/' ),
					'bot2chat' => get_rest_url( null, 'wp-connections/v1/client/' . Client::WPCONNECTIONS_CLIENT . '/relation/' . Client::BOT2CHAT . '/' ),
				],
				'client' => get_rest_url( null, 'wp-connections/v1/client/' . Client::WPCONNECTIONS_CLIENT ),
				'channels' => get_rest_url( null, 'wp/v2/' . Client::CPT_CHANNEL . '/' ),
				'bots' => get_rest_url( null, 'wp/v2/' . Client::CPT_BOT . '/' ),
				'chats' => get_rest_url( null, 'wp/v2/' . Client::CPT_CHAT . '/' ),
				'forms' => get_rest_url( null, 'contact-form-7/v1/contact-forms/' ),
			],
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'phrases' => [
				'emptySecret' => Bot::getEmptySecret(),
			],
		];
	}
}
