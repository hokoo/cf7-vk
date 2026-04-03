<?php

namespace iTRON\cf7Vk\Controllers\RestApi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use iTRON\cf7Vk\Bot;
use iTRON\cf7Vk\Chat;
use iTRON\cf7Vk\Exceptions\TransportNotConfigured;
use iTRON\cf7Vk\Exceptions\VkApiException;
use iTRON\wpConnections\Exceptions\ConnectionNotFound;
use iTRON\wpConnections\Exceptions\ConnectionWrongData;
use iTRON\wpConnections\Exceptions\MissingParameters;
use iTRON\wpConnections\Exceptions\RelationNotFound;
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

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/fetch_updates',
			[
				'args' => [
					'id' => [
						'description' => 'Unique identifier for the VK bot.',
						'type' => 'integer',
					],
				],
				[
					'methods' => WP_REST_Server::READABLE,
					'callback' => [ $this, 'fetch_updates' ],
					'permission_callback' => [ $this, 'get_item_permissions_check' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/chats/(?P<chat_id>[\d]+)/activate',
			[
				'args' => [
					'id' => [
						'description' => 'Unique identifier for the VK bot.',
						'type' => 'integer',
					],
					'chat_id' => [
						'description' => 'Unique identifier for the VK chat.',
						'type' => 'integer',
					],
				],
				[
					'methods' => WP_REST_Server::EDITABLE,
					'callback' => [ $this, 'activate_chat' ],
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

	public function fetch_updates( $request ) {
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
			return rest_ensure_response( $bot->fetchUpdates() );
		} catch ( TransportNotConfigured $exception ) {
			return new WP_Error(
				'rest_vk_bot_config_invalid',
				$exception->getMessage(),
				[ 'status' => 400 ]
			);
		} catch ( VkApiException $exception ) {
			return new WP_Error(
				'rest_vk_fetch_updates_failed',
				$exception->getMessage(),
				[
					'status' => 502,
					'vk_error_code' => $exception->getCode(),
				]
			);
		}
	}

	public function activate_chat( $request ) {
		try {
			$bot = new Bot( (int) $request['id'] );
			$chat = new Chat( (int) $request['chat_id'] );
		} catch ( wppaCreatePostException | wppaLoadPostException $exception ) {
			return new WP_Error(
				'rest_post_invalid_id',
				$exception->getMessage(),
				[ 'status' => 404 ]
			);
		}

		try {
			return rest_ensure_response( $bot->activateChat( $chat ) );
		} catch ( ConnectionNotFound $exception ) {
			return new WP_Error(
				'rest_vk_bot_chat_not_found',
				__( 'VK chat is not linked to this bot.', 'cf7-vk' ),
				[ 'status' => 404 ]
			);
		} catch ( ConnectionWrongData | MissingParameters | RelationNotFound $exception ) {
			return new WP_Error(
				'rest_vk_bot_chat_activate_failed',
				$exception->getMessage(),
				[ 'status' => 500 ]
			);
		}
	}

	public function prepare_item_for_response( $post, $request ): WP_REST_Response {
		$response = parent::prepare_item_for_response( $post, $request );
		$base = sprintf( '%s/%s', $this->namespace, $this->rest_base );

		$response->add_link(
			'ping',
			rest_url( trailingslashit( $base ) . $post->ID . '/ping' )
		);

		$response->add_link(
			'fetch_updates',
			rest_url( trailingslashit( $base ) . $post->ID . '/fetch_updates' )
		);

		$response->add_link(
			'settings',
			rest_url( trailingslashit( $base ) . $post->ID )
		);

		return $response;
	}
}
