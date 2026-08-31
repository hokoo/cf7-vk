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

$result = [
	'fixture'  => $fixture,
	'seeded'   => true,
	'forms'    => [],
	'bots'     => [],
	'chats'    => [],
	'channels' => [],
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

if ( 'modern-basic' === $fixture ) {
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

if ( in_array( $fixture, [ 'modern-basic', 'modern-heavy', 'partial-modern' ], true ) ) {
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

	$chat_count = 'modern-heavy' === $fixture ? 4 : 1;
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
}

update_option(
	'cf7vk_e1_fixture_expectations',
	[
		'fixture'       => $fixture,
		'form_count'    => count( $result['forms'] ),
		'bot_count'     => count( $result['bots'] ),
		'chat_count'    => count( $result['chats'] ),
		'channel_count' => count( $result['channels'] ),
	],
	false
);
$result['options'][] = 'cf7vk_e1_fixture_expectations';

echo wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
