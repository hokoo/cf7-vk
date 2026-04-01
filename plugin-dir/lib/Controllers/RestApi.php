<?php

namespace iTRON\cf7Vk\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use iTRON\cf7Vk\Bot;
use iTRON\cf7Vk\Chat;
use iTRON\cf7Vk\Client;
use iTRON\cf7Vk\Settings;
use iTRON\wpPostAble\Exceptions\wppaSavePostException;

class RestApi {
	public static function init(): void {
		add_action( 'rest_api_init', [ self::class, 'registerFields' ] );
	}

	public static function registerFields(): void {
		register_setting(
			'options',
			Settings::EARLY_FLAG_OPTION,
			[
				'type' => 'boolean',
				'show_in_rest' => true,
				'default' => false,
				'auth_callback' => static function (): bool {
					return current_user_can( Settings::getCaps() );
				},
			]
		);

		self::registerBotFields();
		self::registerChatFields();
	}

	private static function registerBotFields(): void {
		register_rest_field(
			Client::CPT_BOT,
			'accessToken',
			[
				'get_callback' => static function ( $object ) {
					$bot = new Bot( $object['id'] );
					$token = $bot->getAccessToken();

					return $token ? $token : Bot::getEmptySecret();
				},
				'update_callback' => static function ( $updated_value, $wp_post ) {
					try {
						( new Bot( $wp_post->ID ) )->setAccessToken( (string) $updated_value );
					} catch ( wppaSavePostException $e ) {
						return false;
					}

					return true;
				},
				'schema' => [
					'description' => 'Stored VK community access token.',
					'type' => 'string',
				],
			]
		);

		register_rest_field(
			Client::CPT_BOT,
			'isAccessTokenEmpty',
			[
				'get_callback' => static function ( $object ) {
					return ( new Bot( $object['id'] ) )->isAccessTokenEmpty();
				},
				'schema' => [
					'description' => 'Whether the access token is empty.',
					'type' => 'boolean',
				],
			]
		);

		register_rest_field(
			Client::CPT_BOT,
			'isAccessTokenDefinedByConst',
			[
				'get_callback' => static function ( $object ) {
					return ( new Bot( $object['id'] ) )->isAccessTokenDefined();
				},
				'schema' => [
					'description' => 'Whether the access token is defined by PHP constant.',
					'type' => 'boolean',
				],
			]
		);

		register_rest_field(
			Client::CPT_BOT,
			'accessTokenConst',
			[
				'get_callback' => static function ( $object ) {
					return ( new Bot( $object['id'] ) )->getAccessTokenConstName();
				},
				'schema' => [
					'description' => 'PHP constant name for the access token.',
					'type' => 'string',
				],
			]
		);

		self::registerBotMetaField( 'groupId', 'Stored VK community ID.' );
		self::registerBotMetaField( 'apiVersion', 'Configured VK API version.' );
		self::registerBotMetaField( 'authCommand', 'Authorization command for initial linking.' );
		self::registerBotMetaField( 'lastStatus', 'Last known transport status.' );
	}

	private static function registerBotMetaField( string $field, string $description ): void {
		register_rest_field(
			Client::CPT_BOT,
			$field,
			[
				'get_callback' => static function ( $object ) use ( $field ) {
					$bot = new Bot( $object['id'] );

					switch ( $field ) {
						case 'groupId':
							return $bot->getGroupId();
						case 'apiVersion':
							return $bot->getApiVersion();
						case 'authCommand':
							return $bot->getAuthCommand();
						case 'lastStatus':
							return $bot->getLastStatus();
						default:
							return null;
					}
				},
				'update_callback' => static function ( $updated_value, $wp_post ) use ( $field ) {
					$bot = new Bot( $wp_post->ID );

					try {
						switch ( $field ) {
							case 'groupId':
								$bot->setGroupId( (string) $updated_value );
								break;
							case 'apiVersion':
								$bot->setApiVersion( (string) $updated_value );
								break;
							case 'authCommand':
								$bot->setAuthCommand( (string) $updated_value );
								break;
							case 'lastStatus':
								$bot->setLastStatus( (string) $updated_value );
								break;
						}
					} catch ( wppaSavePostException $e ) {
						return false;
					}

					return true;
				},
				'schema' => [
					'description' => $description,
					'type' => 'string',
				],
			]
		);
	}

	private static function registerChatFields(): void {
		self::registerChatMetaField( 'peerId', 'Stored VK peer ID.' );
		self::registerChatMetaField( 'userId', 'Stored VK user ID.' );
		self::registerChatMetaField( 'chatType', 'Chat type.' );
		self::registerChatMetaField( 'displayName', 'Display name.' );
		self::registerChatMetaField( 'username', 'Optional chat username.' );
	}

	private static function registerChatMetaField( string $field, string $description ): void {
		register_rest_field(
			Client::CPT_CHAT,
			$field,
			[
				'get_callback' => static function ( $object ) use ( $field ) {
					$chat = new Chat( $object['id'] );

					switch ( $field ) {
						case 'peerId':
							return $chat->getPeerId();
						case 'userId':
							return $chat->getUserId();
						case 'chatType':
							return $chat->getChatType();
						case 'displayName':
							return $chat->getDisplayName();
						case 'username':
							return $chat->getUsername();
						default:
							return null;
					}
				},
				'update_callback' => static function ( $updated_value, $wp_post ) use ( $field ) {
					$chat = new Chat( $wp_post->ID );

					switch ( $field ) {
						case 'peerId':
							$chat->setPeerId( (string) $updated_value );
							break;
						case 'userId':
							$chat->setUserId( (string) $updated_value );
							break;
						case 'chatType':
							$chat->setChatType( (string) $updated_value );
							break;
						case 'displayName':
							$chat->setDisplayName( (string) $updated_value );
							break;
						case 'username':
							$chat->setUsername( (string) $updated_value );
							break;
					}

					return true;
				},
				'schema' => [
					'description' => $description,
					'type' => 'string',
				],
			]
		);
	}
}
