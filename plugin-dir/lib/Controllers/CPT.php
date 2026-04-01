<?php

namespace iTRON\cf7Vk\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use iTRON\cf7Vk\Client;
use iTRON\cf7Vk\Controllers\RestApi\BotController;
use iTRON\cf7Vk\Controllers\RestApi\ChannelController;
use iTRON\cf7Vk\Controllers\RestApi\ChatController;
use WPCF7_ContactForm;

class CPT {
	private static ?self $instance = null;

	public array $cf7_orig_capabilities = [];
	public string $cf7_orig_capability_type = '';

	private function __construct() {}

	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function init(): void {
		add_filter( 'register_post_type_args', [ $this, 'obtain_orig_capabilities' ], 10, 2 );
		add_action( 'init', [ $this, 'register' ], 20 );
	}

	public function register(): void {
		register_post_type(
			Client::CPT_BOT,
			[
				'labels' => [ 'name' => __( 'VK Bots', 'cf7-vk' ) ],
				'public' => false,
				'show_in_menu' => false,
				'publicly_queryable' => false,
				'show_in_rest' => true,
				'capabilities' => $this->cf7_orig_capabilities,
				'capability_type' => $this->cf7_orig_capability_type,
				'rest_controller_class' => BotController::class,
				'supports' => [ 'title' ],
			]
		);

		register_post_type(
			Client::CPT_CHAT,
			[
				'labels' => [ 'name' => __( 'VK Chats', 'cf7-vk' ) ],
				'public' => false,
				'show_in_menu' => false,
				'publicly_queryable' => false,
				'show_in_rest' => true,
				'capabilities' => $this->cf7_orig_capabilities,
				'capability_type' => $this->cf7_orig_capability_type,
				'rest_controller_class' => ChatController::class,
				'supports' => [ 'title' ],
			]
		);

		register_post_type(
			Client::CPT_CHANNEL,
			[
				'labels' => [ 'name' => __( 'VK Channels', 'cf7-vk' ) ],
				'public' => false,
				'show_in_menu' => false,
				'publicly_queryable' => false,
				'show_in_rest' => true,
				'capabilities' => $this->cf7_orig_capabilities,
				'capability_type' => $this->cf7_orig_capability_type,
				'rest_controller_class' => ChannelController::class,
				'supports' => [ 'title' ],
			]
		);
	}

	public function obtain_orig_capabilities( array $args, string $post_type ): array {
		if ( WPCF7_ContactForm::post_type === $post_type ) {
			$this->cf7_orig_capabilities = (array) ( $args['capabilities'] ?? [] );
			$this->cf7_orig_capability_type = (string) ( $args['capability_type'] ?? '' );
		}

		return $args;
	}
}
