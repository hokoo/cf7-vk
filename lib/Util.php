<?php

namespace iTRON\cf7Vk;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InvalidArgumentException;
use iTRON\wpPostAble\Exceptions\wppaSavePostException;
use wpdb;

class Util {
	public static function installTable( string $table_name, string $columns, array $options = [] ): void {
		self::getWPDB()->tables[] = $table_name;
		self::getWPDB()->$table_name = self::getWPDB()->prefix . $table_name;

		$full_table_name = self::getWPDB()->$table_name;

		$options = wp_parse_args(
			$options,
			[
				'upgrade_method' => 'dbDelta',
				'table_options' => '',
			]
		);

		$charset_collate = '';
		if ( self::getWPDB()->has_cap( 'collation' ) ) {
			if ( ! empty( self::getWPDB()->charset ) ) {
				$charset_collate = 'DEFAULT CHARACTER SET ' . self::getWPDB()->charset;
			}

			if ( ! empty( self::getWPDB()->collate ) ) {
				$charset_collate .= ' COLLATE ' . self::getWPDB()->collate;
			}
		}

		$table_options = trim( $charset_collate . ' ' . $options['table_options'] );

		if ( 'dbDelta' === $options['upgrade_method'] ) {
			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( "CREATE TABLE {$full_table_name} ( {$columns} ) {$table_options}" );

			return;
		}

		if ( 'delete_first' === $options['upgrade_method'] ) {
			self::getWPDB()->query( "DROP TABLE IF EXISTS {$full_table_name}" );
		}

		self::getWPDB()->query( "CREATE TABLE IF NOT EXISTS {$full_table_name} ( {$columns} ) {$table_options}" );
	}

	public static function getWPDB(): wpdb {
		global $wpdb;

		return $wpdb;
	}

	public static function getChatByPeerId( string $peer_id ): ?Chat {
		$peer_id = trim( $peer_id );

		if ( '' === $peer_id ) {
			return null;
		}

		$pattern = sprintf(
			'"peerId"[[:space:]]*:[[:space:]]*"%s"',
			preg_quote( $peer_id, '/' )
		);

		$chat_id = (int) self::getWPDB()->get_var(
			self::getWPDB()->prepare(
				"SELECT ID
				FROM " . self::getWPDB()->posts . "
				WHERE post_type = %s
					AND post_status <> 'trash'
					AND post_content_filtered REGEXP %s
				ORDER BY ID ASC
				LIMIT 1",
				Client::CPT_CHAT,
				$pattern
			)
		);

		if ( $chat_id <= 0 ) {
			return null;
		}

		return new Chat( $chat_id );
	}

	/**
	 * @throws wppaSavePostException
	 */
	public static function createOrUpdateChatFromVkMessage(
		array $message,
		array $profile = [],
		array $conversation = []
	): Chat {
		$peer_id = trim( (string) ( $message['peer_id'] ?? '' ) );

		if ( '' === $peer_id ) {
			throw new InvalidArgumentException( esc_html__( 'VK message peer ID is missing.', 'message-bridge-for-contact-form-7-and-vk' ) );
		}

		$chat = self::getChatByPeerId( $peer_id ) ?: new Chat();
		$title = self::resolveVkChatTitle( $message, $profile, $conversation );
		$timestamp = self::normalizeVkTimestamp( $message['date'] ?? '' );

		$chat->syncFromVkPayload(
			[
				'peerId' => $peer_id,
				'userId' => (string) ( $message['from_id'] ?? '' ),
				'chatType' => Chat::detectTypeByPeerId( (int) $peer_id ),
				'displayName' => $title,
				'username' => (string) ( $profile['screen_name'] ?? '' ),
				'connectedAt' => $chat->getConnectedAt() ?: $timestamp,
				'conversationMessageId' => (string) ( $message['conversation_message_id'] ?? '' ),
				'lastMessageId' => (string) ( $message['id'] ?? '' ),
				'lastMessageText' => (string) ( $message['text'] ?? '' ),
				'lastEventAt' => $timestamp,
				'title' => $title,
			]
		);

		return $chat;
	}

	public static function resolveVkChatTitle(
		array $message,
		array $profile = [],
		array $conversation = []
	): string {
		$conversation_title =
			(string) (
				$conversation['chat_settings']['title'] ??
				$conversation['conversation']['chat_settings']['title'] ??
				$message['title'] ??
				''
			);

		if ( '' !== trim( $conversation_title ) ) {
			return trim( $conversation_title );
		}

		$profile_name = trim(
			implode(
				' ',
				array_filter(
					[
						(string) ( $profile['first_name'] ?? '' ),
						(string) ( $profile['last_name'] ?? '' ),
					]
				)
			)
		);

		if ( '' !== $profile_name ) {
			return $profile_name;
		}

		if ( ! empty( $profile['screen_name'] ) ) {
			return (string) $profile['screen_name'];
		}

		$peer_id = (int) ( $message['peer_id'] ?? 0 );
		$user_id = (string) ( $message['from_id'] ?? '' );

		if ( Chat::TYPE_PRIVATE === Chat::detectTypeByPeerId( $peer_id ) ) {
			return sprintf(
				/* translators: %s: VK user ID */
				__( 'VK user %s', 'message-bridge-for-contact-form-7-and-vk' ),
				$user_id ?: (string) $peer_id
			);
		}

		return sprintf(
			/* translators: %s: VK peer ID */
			__( 'VK chat %s', 'message-bridge-for-contact-form-7-and-vk' ),
			(string) $peer_id
		);
	}

	public static function normalizeVkTimestamp( $timestamp ): string {
		if ( is_numeric( $timestamp ) ) {
			return gmdate( 'c', (int) $timestamp );
		}

		$timestamp = trim( (string) $timestamp );

		return '' === $timestamp ? gmdate( 'c' ) : $timestamp;
	}

	/**
	 * Converts a version string to an integer for comparison.
	 *
	 * @throws InvalidArgumentException If the version string is invalid.
	 */
	public static function versionToInt( string $version ): int {
		if ( ! preg_match(
			'/^(\d+)(?:\.(\d+))?(?:\.(\d+))?(?:-([a-z]+)(\d+)?)?$/i',
			$version,
			$matches
		) ) {
			throw new InvalidArgumentException( esc_html( "Invalid version: {$version}" ) );
		}

		$major = (int) ( $matches[1] ?? 0 );
		$minor = (int) ( $matches[2] ?? 0 );
		$patch = (int) ( $matches[3] ?? 0 );
		$tag = strtolower( $matches[4] ?? '' );
		$tag_no = (int) ( $matches[5] ?? 0 );

		$tag_rank_map = [
			'dev' => 0,
			'alpha' => 1,
			'a' => 1,
			'beta' => 2,
			'b' => 2,
			'rc' => 3,
			'' => 4,
		];

		$tag_rank = $tag_rank_map[ $tag ] ?? 4;

		return $major * 10000000000
			+ $minor * 10000000
			+ $patch * 10000
			+ $tag_rank * 1000
			+ $tag_no;
	}
}
