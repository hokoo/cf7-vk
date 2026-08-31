<?php

namespace iTRON\cf7Vk\Controllers\RestApi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use iTRON\cf7Vk\Bot;
use iTRON\cf7Vk\Chat;
use iTRON\cf7Vk\Client;
use iTRON\cf7Vk\Exceptions\TransportNotConfigured;
use iTRON\cf7Vk\Exceptions\VkApiException;
use iTRON\wpConnections\Exceptions\ConnectionNotFound;
use iTRON\wpConnections\Exceptions\ConnectionWrongData;
use iTRON\wpConnections\Exceptions\MissingParameters;
use iTRON\wpConnections\Exceptions\RelationNotFound;
use iTRON\wpPostAble\Exceptions\wppaCreatePostException;
use iTRON\wpPostAble\Exceptions\wppaLoadPostException;
use iTRON\wpPostAble\Exceptions\wppaSavePostException;
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
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => [ $this, 'ping' ],
					'permission_callback' => [ $this, 'manage_bot_permissions_check' ],
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
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => [ $this, 'fetch_updates' ],
					'permission_callback' => [ $this, 'manage_bot_permissions_check' ],
				],
			]
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<id>[\d]+)/credentials',
			[
				'args' => [
					'id' => [
						'description' => 'Unique identifier for the VK bot.',
						'type' => 'integer',
					],
					'groupId' => [
						'description' => 'Candidate VK community ID.',
						'type' => 'string',
					],
					'accessToken' => [
						'description' => 'Candidate VK community access token.',
						'type' => 'string',
					],
					'apiVersion' => [
						'description' => 'Candidate VK API version.',
						'type' => 'string',
					],
				],
				[
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => [ $this, 'update_credentials' ],
					'permission_callback' => [ $this, 'manage_bot_permissions_check' ],
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
					'methods' => WP_REST_Server::CREATABLE,
					'callback' => [ $this, 'activate_chat' ],
					'permission_callback' => [ $this, 'activate_chat_permissions_check' ],
				],
			]
		);
	}

	public function manage_bot_permissions_check( $request ) {
		return $this->update_item_permissions_check( $request );
	}

	public function activate_chat_permissions_check( $request ) {
		$permissions = $this->update_item_permissions_check( $request );

		if ( true !== $permissions ) {
			return $permissions;
		}

		$chat = get_post( (int) $request['chat_id'] );

		if ( ! $chat || Client::CPT_CHAT !== $chat->post_type ) {
			return new WP_Error(
				'rest_post_invalid_id',
				__( 'Invalid post ID.', 'message-bridge-for-contact-form-7-and-vk' ),
				[ 'status' => 404 ]
			);
		}

		if ( ! current_user_can( 'edit_post', $chat->ID ) ) {
			return new WP_Error(
				'rest_cannot_edit',
				__( 'Sorry, you are not allowed to edit this post.', 'message-bridge-for-contact-form-7-and-vk' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		return true;
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

	public function update_credentials( $request ) {
		try {
			$bot = new Bot( (int) $request['id'] );
		} catch ( wppaCreatePostException | wppaLoadPostException $exception ) {
			return new WP_Error(
				'rest_post_invalid_id',
				$exception->getMessage(),
				[ 'status' => 404 ]
			);
		}

		$submitted_access_token = (string) $this->requestParam( $request, 'accessToken', '' );
		$submitted_group_id = (string) $this->requestParam( $request, 'groupId', $bot->getGroupId() );

		if (
			$bot->isAccessTokenDefined() &&
			'' !== trim( $submitted_access_token ) &&
			! Bot::isMaskedSecretValue( $submitted_access_token )
		) {
			return new WP_Error(
				'rest_vk_bot_access_token_const',
				__( 'VK access token is defined by PHP constant.', 'message-bridge-for-contact-form-7-and-vk' ),
				[ 'status' => 409 ]
			);
		}

		if (
			$bot->isGroupIdDefined() &&
			'' !== trim( $submitted_group_id ) &&
			ltrim( trim( $submitted_group_id ), '-' ) !== ltrim( trim( $bot->getGroupId() ), '-' )
		) {
			return new WP_Error(
				'rest_vk_bot_group_id_const',
				__( 'VK group ID is defined by PHP constant.', 'message-bridge-for-contact-form-7-and-vk' ),
				[ 'status' => 409 ]
			);
		}

		$access_token = $bot->isAccessTokenDefined()
			? (string) $bot->getAccessToken()
			: ( Bot::isMaskedSecretValue( $submitted_access_token ) ? (string) $bot->getAccessToken() : $submitted_access_token );
		$group_id = $bot->isGroupIdDefined() ? $bot->getGroupId() : $submitted_group_id;
		$api_version = (string) $this->requestParam( $request, 'apiVersion', $bot->getApiVersion() );
		$auth_command = $this->hasRequestParam( $request, 'authCommand' )
			? (string) $this->requestParam( $request, 'authCommand', $bot->getAuthCommand() )
			: null;

		try {
			$result = $bot->updateCredentials( $group_id, $access_token, $api_version, $auth_command );
		} catch ( TransportNotConfigured $exception ) {
			return new WP_Error(
				'rest_vk_bot_credentials_invalid',
				$exception->getMessage(),
				[ 'status' => 400 ]
			);
		} catch ( VkApiException $exception ) {
			return new WP_Error(
				'rest_vk_bot_credentials_validation_failed',
				$exception->getMessage(),
				[
					'status' => 502,
					'vk_error_code' => $exception->getCode(),
				]
			);
		} catch ( wppaSavePostException $exception ) {
			return new WP_Error(
				'rest_vk_bot_credentials_save_failed',
				__( 'VK bot credentials could not be saved.', 'message-bridge-for-contact-form-7-and-vk' ),
				[ 'status' => 500 ]
			);
		}

		return rest_ensure_response(
			array_merge(
				$result,
				[
					'bot' => $this->botResponseData( $bot ),
				]
			)
		);
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
				__( 'VK chat is not linked to this bot.', 'message-bridge-for-contact-form-7-and-vk' ),
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
			'credentials',
			rest_url( trailingslashit( $base ) . $post->ID . '/credentials' )
		);

		$response->add_link(
			'settings',
			rest_url( trailingslashit( $base ) . $post->ID )
		);

		return $response;
	}

	private function requestParam( $request, string $key, $default = null ) {
		if ( is_object( $request ) && method_exists( $request, 'get_param' ) ) {
			$value = $request->get_param( $key );
			return null === $value ? $default : $value;
		}

		return $request[ $key ] ?? $default;
	}

	private function hasRequestParam( $request, string $key ): bool {
		if ( is_object( $request ) && method_exists( $request, 'get_params' ) ) {
			return array_key_exists( $key, $request->get_params() );
		}

		return is_array( $request ) || $request instanceof \ArrayAccess
			? isset( $request[ $key ] )
			: false;
	}

	private function botResponseData( Bot $bot ): array {
		$token = $bot->getAccessToken();

		if ( empty( $token ) ) {
			$access_token = Bot::getEmptySecret();
		} elseif ( $bot->isAccessTokenDefined() ) {
			$access_token = substr( $token, -4 );
		} else {
			$access_token = $token;
		}

		return [
			'id' => $bot->getPost()->ID,
			'status' => $bot->getPost()->post_status,
			'title' => [
				'rendered' => $bot->getTitle(),
			],
			'groupId' => $bot->getGroupId(),
			'accessToken' => $access_token,
			'isAccessTokenEmpty' => $bot->isAccessTokenEmpty(),
			'isAccessTokenDefinedByConst' => $bot->isAccessTokenDefined(),
			'isGroupIdDefinedByConst' => $bot->isGroupIdDefined(),
			'groupIdConst' => $bot->getGroupIdConstName(),
			'accessTokenConst' => $bot->getAccessTokenConstName(),
			'apiVersion' => $bot->getApiVersion(),
			'authCommand' => $bot->getAuthCommand(),
			'lastStatus' => $bot->getLastStatus(),
			'longPollServer' => $bot->getLongPollServer(),
			'longPollTs' => $bot->getLongPollTs(),
			'lastSyncAt' => $bot->getLastSyncAt(),
			'communityId' => $bot->getCommunityId(),
			'communityName' => $bot->getCommunityName(),
			'communityScreenName' => $bot->getCommunityScreenName(),
		];
	}
}
