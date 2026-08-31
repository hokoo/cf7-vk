<?php
/**
 * Seeds anonymized E1 smoke fixtures in an ephemeral WordPress site.
 *
 * Intended to be executed with WP-CLI:
 * wp eval-file /e1-tests/wp-seed-fixture.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

$fixture = getenv( 'CF7VK_E1_FIXTURE' ) ?: 'modern-heavy';

if ( 'none' === $fixture ) {
	echo wp_json_encode(
		[
			'fixture' => $fixture,
			'seeded'  => false,
			'note'    => 'Fixture seeding disabled.',
		],
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
	);
	return;
}

$fixture_profile = $fixture;

if ( 'legacy-basic' === $fixture ) {
	$fixture_profile = 'modern-basic';
}

if ( 'legacy-heavy' === $fixture || 'damaged-modern' === $fixture ) {
	$fixture_profile = 'modern-heavy';
}

function cf7vk_e1_connection_table(): string {
	global $wpdb;

	return $wpdb->prefix . 'post_connections_cf7_vk';
}

function cf7vk_e1_connection_meta_table(): string {
	global $wpdb;

	return $wpdb->prefix . 'post_connections_meta_cf7_vk';
}

function cf7vk_e1_ensure_connection_tables(): void {
	global $wpdb;

	$connection_table = cf7vk_e1_connection_table();
	$connection_meta_table = cf7vk_e1_connection_meta_table();

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
	$wpdb->query(
		"CREATE TABLE IF NOT EXISTS `{$connection_table}` (
			`ID` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`relation` varchar(255) NOT NULL,
			`from` bigint(20) unsigned NOT NULL,
			`to` bigint(20) unsigned NOT NULL,
			`order` bigint(20) unsigned NULL DEFAULT '0',
			`title` varchar(63) NULL DEFAULT '',
			PRIMARY KEY (`ID`),
			KEY `from` (`from`),
			KEY `to` (`to`),
			KEY `order` (`order`),
			KEY `relation` (`relation`)
		)"
	);
	$wpdb->query(
		"CREATE TABLE IF NOT EXISTS `{$connection_meta_table}` (
			`meta_id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			`connection_id` bigint(20) unsigned NOT NULL DEFAULT '0',
			`meta_key` varchar(255) NOT NULL,
			`meta_value` longtext NOT NULL,
			PRIMARY KEY (`meta_id`),
			KEY `connection_id` (`connection_id`),
			KEY `meta_key` (`meta_key`)
		)"
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.SchemaChange
}

function cf7vk_e1_find_connection( string $relation, int $from, int $to ): int {
	global $wpdb;

	$table = cf7vk_e1_connection_table();

	// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
	return (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT ID FROM `{$table}` WHERE relation = %s AND `from` = %d AND `to` = %d LIMIT 1",
			$relation,
			$from,
			$to
		)
	);
	// phpcs:enable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
}

function cf7vk_e1_insert_connection( string $relation, int $from, int $to, string $title = '' ): ?int {
	global $wpdb;

	cf7vk_e1_ensure_connection_tables();

	$existing = cf7vk_e1_find_connection( $relation, $from, $to );
	if ( $existing > 0 ) {
		return $existing;
	}

	$inserted = $wpdb->insert(
		cf7vk_e1_connection_table(),
		[
			'relation' => $relation,
			'from'     => $from,
			'to'       => $to,
			'order'    => 0,
			'title'    => $title,
		],
		[
			'%s',
			'%d',
			'%d',
			'%d',
			'%s',
		]
	);

	return false === $inserted ? null : (int) $wpdb->insert_id;
}

function cf7vk_e1_insert_connection_meta( int $connection_id, string $key, string $value ): void {
	global $wpdb;

	$wpdb->insert(
		cf7vk_e1_connection_meta_table(),
		[
			'connection_id' => $connection_id,
			'meta_key'      => $key,
			'meta_value'    => $value,
		],
		[
			'%d',
			'%s',
			'%s',
		]
	);
}

$result = [
	'fixture'  => $fixture,
	'seeded'   => true,
	'forms'    => [],
	'bots'     => [],
	'chats'    => [],
	'channels' => [],
	'relations' => [],
	'damaged_relations' => [],
	'options'  => [],
];

$form_definitions = [
	[
		'title' => 'E1 VK Fixture Form 1',
		'body'  => "[text* your-name]\n[email* your-email]\n[textarea your-message]\n[submit \"Send\"]",
	],
	[
		'title' => 'E1 VK Fixture Form 2',
		'body'  => "[text subject \"special chars: _ * [ ] ( ) ~ ` > # + - = | { } . !\"]\n[submit \"Send\"]",
	],
];

if ( 'modern-basic' === $fixture_profile ) {
	$form_definitions = array_slice( $form_definitions, 0, 1 );
}

foreach ( $form_definitions as $definition ) {
	$post_id = wp_insert_post(
		[
			'post_type'    => 'wpcf7_contact_form',
			'post_status'  => 'publish',
			'post_title'   => $definition['title'],
			'post_content' => $definition['body'],
		],
		true
	);

	if ( is_wp_error( $post_id ) ) {
		$result['forms'][] = [
			'title' => $definition['title'],
			'error' => $post_id->get_error_message(),
		];
		continue;
	}

	update_post_meta( $post_id, '_form', $definition['body'] );
	$result['forms'][] = [
		'id'    => (int) $post_id,
		'title' => $definition['title'],
		'sha256' => hash( 'sha256', $definition['body'] ),
	];
}

if ( in_array( $fixture_profile, [ 'modern-basic', 'modern-heavy', 'partial-modern' ], true ) ) {
	$bot_id = wp_insert_post(
		[
			'post_type'             => 'cf7vk_bot',
			'post_status'           => 'publish',
			'post_title'            => 'E1 VK Fixture Community',
			'post_content_filtered' => wp_json_encode(
				[
					'accessToken'    => '[redacted]',
					'groupId'        => '1001',
					'apiVersion'     => '5.199',
					'authCommand'    => 'start',
					'lastStatus'     => 'online',
					'longPollServer' => 'https://lp.vk.example/redacted',
					'longPollKey'    => '[redacted]',
					'longPollTs'     => '100',
				],
				JSON_UNESCAPED_SLASHES
			),
		],
		true
	);

	if ( ! is_wp_error( $bot_id ) ) {
		$result['bots'][] = [ 'id' => (int) $bot_id ];
	}

	$chat_count = 'modern-heavy' === $fixture_profile ? 4 : 1;
	for ( $i = 1; $i <= $chat_count; $i++ ) {
		$peer_id = 2000000000 + $i;
		$chat_id = wp_insert_post(
			[
				'post_type'             => 'cf7vk_chat',
				'post_status'           => 'publish',
				'post_title'            => 'E1 VK Dialog ' . $i,
				'post_content_filtered' => wp_json_encode(
					[
						'peerId'                => (string) $peer_id,
						'userId'                => (string) ( 700000 + $i ),
						'chatType'              => 'chat',
						'displayName'           => 'E1 VK Dialog ' . $i,
						'username'              => 'e1_vk_' . $i,
						'connectedAt'           => gmdate( 'c', 1700000000 + $i ),
						'conversationMessageId' => (string) $i,
						'lastMessageId'         => (string) ( 100 + $i ),
						'lastMessageText'       => '[redacted fixture text]',
						'lastEventAt'           => gmdate( 'c', 1700000000 + $i ),
					],
					JSON_UNESCAPED_SLASHES
				),
			],
			true
		);

		if ( ! is_wp_error( $chat_id ) ) {
			$result['chats'][] = [
				'id'      => (int) $chat_id,
				'peer_id' => hash( 'sha256', (string) $peer_id ),
			];
		}
	}

	$channel_id = wp_insert_post(
		[
			'post_type'   => 'cf7vk_channel',
			'post_status' => 'publish',
			'post_title'  => 'E1 VK Fixture Channel',
		],
		true
	);

	if ( ! is_wp_error( $channel_id ) ) {
		$result['channels'][] = [ 'id' => (int) $channel_id ];
	}

	$bot_ids = array_values( array_map( static fn( array $bot ): int => (int) $bot['id'], $result['bots'] ) );
	$chat_ids = array_values( array_map( static fn( array $chat ): int => (int) $chat['id'], $result['chats'] ) );
	$channel_ids = array_values( array_map( static fn( array $channel ): int => (int) $channel['id'], $result['channels'] ) );
	$form_ids = array_values( array_map( static fn( array $form ): int => (int) $form['id'], $result['forms'] ) );

	if ( ! empty( $bot_ids ) && ! empty( $chat_ids ) ) {
		foreach ( $chat_ids as $index => $chat_id ) {
			$connection_id = cf7vk_e1_insert_connection( 'bot2chat', $bot_ids[0], $chat_id, 'fixture bot2chat' );
			if ( null !== $connection_id ) {
				cf7vk_e1_insert_connection_meta( $connection_id, 'status', 0 === $index ? 'active' : 'pending' );
				$result['relations'][] = [ 'id' => $connection_id, 'relation' => 'bot2chat' ];
			}
		}
	}

	if ( 'partial-modern' !== $fixture_profile && ! empty( $bot_ids ) && ! empty( $channel_ids ) ) {
		$connection_id = cf7vk_e1_insert_connection( 'bot2channel', $bot_ids[0], $channel_ids[0], 'fixture bot2channel' );
		if ( null !== $connection_id ) {
			$result['relations'][] = [ 'id' => $connection_id, 'relation' => 'bot2channel' ];
		}
	}

	if ( 'partial-modern' !== $fixture_profile && ! empty( $chat_ids ) && ! empty( $channel_ids ) ) {
		foreach ( $chat_ids as $chat_id ) {
			$connection_id = cf7vk_e1_insert_connection( 'chat2channel', $chat_id, $channel_ids[0], 'fixture chat2channel' );
			if ( null !== $connection_id ) {
				$result['relations'][] = [ 'id' => $connection_id, 'relation' => 'chat2channel' ];
			}
		}
	}

	if ( 'partial-modern' !== $fixture_profile && ! empty( $form_ids ) && ! empty( $channel_ids ) ) {
		foreach ( $form_ids as $form_id ) {
			$connection_id = cf7vk_e1_insert_connection( 'form2channel', $form_id, $channel_ids[0], 'fixture form2channel' );
			if ( null !== $connection_id ) {
				$result['relations'][] = [ 'id' => $connection_id, 'relation' => 'form2channel' ];
			}
		}
	}

	if ( 'damaged-modern' === $fixture ) {
		$broken_rows = [
			[
				'relation' => 'bot2chat',
				'from'     => $channel_ids[0] ?? 900001,
				'to'       => $chat_ids[0] ?? 900002,
				'title'    => 'fixture damaged wrong-from-type',
			],
			[
				'relation' => 'chat2channel',
				'from'     => 900003,
				'to'       => $channel_ids[0] ?? 900004,
				'title'    => 'fixture damaged missing-chat',
			],
		];

		foreach ( $broken_rows as $broken_row ) {
			$connection_id = cf7vk_e1_insert_connection(
				$broken_row['relation'],
				(int) $broken_row['from'],
				(int) $broken_row['to'],
				$broken_row['title']
			);
			if ( null !== $connection_id ) {
				$result['damaged_relations'][] = [
					'id'       => $connection_id,
					'relation' => $broken_row['relation'],
				];
			}
		}
	}
}

$relation_counts = [];
foreach ( $result['relations'] as $relation ) {
	$name = (string) $relation['relation'];
	$relation_counts[ $name ] = ( $relation_counts[ $name ] ?? 0 ) + 1;
}
ksort( $relation_counts );

update_option(
	'cf7vk_e1_fixture_expectations',
	[
		'fixture'                   => $fixture,
		'profile'                   => $fixture_profile,
		'form_count'                => count( $result['forms'] ),
		'bot_count'                 => count( $result['bots'] ),
		'chat_count'                => count( $result['chats'] ),
		'channel_count'             => count( $result['channels'] ),
		'relation_count_total'      => count( $result['relations'] ),
		'relation_counts'           => $relation_counts,
		'damaged_relation_count'    => count( $result['damaged_relations'] ),
		'expect_repair_connections' => 'damaged-modern' === $fixture ? count( $result['damaged_relations'] ) : 0,
	],
	false
);
$result['options'][] = 'cf7vk_e1_fixture_expectations';

echo wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
