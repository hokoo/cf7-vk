<?php

namespace iTRON\cf7Vk\Controllers\RestApi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use iTRON\cf7Vk\Bot;
use iTRON\cf7Vk\Exceptions\TransportNotConfigured;
use iTRON\cf7Vk\Exceptions\VkApiException;
use iTRON\wpPostAble\Exceptions\wppaCreatePostException;
use iTRON\wpPostAble\Exceptions\wppaLoadPostException;
use WP_Error;
use WP_REST_Server;
use WP_REST_Response;

class BotController extends Controller {
	public function register_routes(): void {
		parent::register_routes();

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/ping',
			[
				'args' => [
					'id' => [
						'description' => 'Unique identifier for the VK bot.',
						'type' => 'integer',
					],
				],
				[
					'methods' => WP_REST_Server::READABLE,
					'callback' => [ $this, 'ping' ],
					'permission_callback' => [ $this, 'get_item_permissions_check' ],
				],
			]
		);
	}

	public function ping( $request ) {
		try {
			$bot = new Bot( (int) $request['id'] );
		} catch ( wppaCreatePostException | wppaLoadPostException $exception ) {
			return new WP_Error(
				'rest_post_invalid_id',
				$exception->getMessage(),
				[ 'status' => 404 ]
			);
		}

		try {
			$details = $bot->verifyConnection();
		} catch ( TransportNotConfigured $exception ) {
			return new WP_Error(
				'rest_vk_bot_config_invalid',
				$exception->getMessage(),
				[ 'status' => 400 ]
			);
		} catch ( VkApiException $exception ) {
			return new WP_Error(
				'rest_vk_ping_failed',
				$exception->getMessage(),
				[
					'status' => 502,
					'vk_error_code' => $exception->getCode(),
				]
			);
		}

		return rest_ensure_response(
			[
				'online' => true,
				'botName' => $bot->getTitle(),
				'communityName' => $details['communityName'] ?? '',
				'groupId' => $details['groupId'] ?? '',
				'longPollReady' => (bool) ( $details['longPollReady'] ?? false ),
				'longPollError' => $details['longPollError'] ?? '',
				'longPollServer' => $bot->getLongPollServer(),
				'longPollTs' => $bot->getLongPollTs(),
				'lastSyncAt' => $bot->getLastSyncAt(),
			]
		);
	}

	public function prepare_item_for_response( $post, $request ): WP_REST_Response {
		$response = parent::prepare_item_for_response( $post, $request );
		$base = sprintf( '%s/%s', $this->namespace, $this->rest_base );

		$response->add_link(
			'ping',
			rest_url( trailingslashit( $base ) . $post->ID . '/ping' )
		);

		$response->add_link(
			'settings',
			rest_url( trailingslashit( $base ) . $post->ID )
		);

		return $response;
	}
}
