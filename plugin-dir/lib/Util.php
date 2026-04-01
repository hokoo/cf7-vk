<?php

namespace iTRON\cf7Vk;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use InvalidArgumentException;
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
		$chat_ids = get_posts(
			[
				'post_type' => Client::CPT_CHAT,
				'posts_per_page' => -1,
				'fields' => 'ids',
				'post_status' => 'any',
				'meta_query' => [
					[
						'key' => 'peerId',
						'value' => $peer_id,
					],
				],
			]
		);

		if ( empty( $chat_ids ) ) {
			return null;
		}

		return new Chat( (int) $chat_ids[0] );
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
