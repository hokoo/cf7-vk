<?php
/**
 * Runs a real WordPress REST fetch-updates request through a fake VK gateway.
 *
 * Intended to be executed with WP-CLI:
 * wp eval-file /e1-tests/wp-fake-vk-fetch-updates.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

use iTRON\cf7Vk\Bot;
use iTRON\cf7Vk\Client;
use iTRON\cf7Vk\LogRedactor;
use iTRON\cf7Vk\Vk\VkDeliveryResult;
use iTRON\cf7Vk\Vk\VkGateway;

if ( ! class_exists( Bot::class ) || ! interface_exists( VkGateway::class ) ) {
	cf7vk_e1_fake_vk_fail( 'Candidate plugin classes are not available.' );
}

if ( ! class_exists( 'Cf7vk_E1_Fake_Vk_Gateway' ) ) {
	final class Cf7vk_E1_Fake_Vk_Gateway implements VkGateway {
		public static array $calls = [];

		public function api( string $method, array $params, string $accessToken, string $apiVersion ): VkDeliveryResult {
			self::$calls[] = [
				'type'       => 'api',
				'method'     => $method,
				'apiVersion' => $apiVersion,
			];

			if ( 'users.get' === $method ) {
				return VkDeliveryResult::success(
					$method,
					[
						[
							'id'          => 101,
							'first_name'  => 'Smoke',
							'last_name'   => 'User',
							'screen_name' => 'cf7vk_e1_smoke',
						],
					]
				);
			}

			if ( 'messages.getByConversationMessageId' === $method ) {
				return VkDeliveryResult::success(
					$method,
					[
						'items' => [
							[
								'conversation_message_id' => 501,
								'chat_settings'           => [
									'title' => 'CF7VK E1 Smoke Chat',
								],
							],
						],
					]
				);
			}

			return VkDeliveryResult::success( $method, true );
		}

		public function longPoll( string $server, string $key, string $ts, int $wait = 25 ): VkDeliveryResult {
			self::$calls[] = [
				'type'       => 'long_poll',
				'method'     => 'long_poll',
				'serverHost' => (string) ( wp_parse_url( $server, PHP_URL_HOST ) ?: '' ),
				'wait'       => $wait,
			];

			return VkDeliveryResult::success(
				'long_poll',
				[
					'ts'      => 'e1-ts-next',
					'updates' => [
						[
							'type'     => 'message_new',
							'event_id' => 'e1-event-1',
							'object'   => [
								'message' => [
									'id'                      => 7001,
									'peer_id'                 => '2000000456',
									'from_id'                 => 101,
									'conversation_message_id' => 501,
									'date'                    => 1700000000,
									'text'                    => 'join-e1',
								],
							],
						],
					],
				]
			);
		}
	}
}

function cf7vk_e1_fake_vk_fail( string $message, array $context = [] ): void {
	$redacted_context = class_exists( LogRedactor::class ) ? LogRedactor::redact( $context ) : $context;

	echo wp_json_encode(
		[
			'ok'      => false,
			'message' => $message,
			'context' => $redacted_context,
		],
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
	);
	echo "\n";
	exit( 1 );
}

function cf7vk_e1_fake_vk_bucket( string $value ): string {
	return 'sha256:' . substr( hash( 'sha256', $value ), 0, 12 );
}

global $wpdb;

Client::getInstance()->init();
wp_set_current_user( 1 );

$access_token = 'vk1.e1FakeTokenValueForSmokeOnly1234567890';
$long_poll_key = 'e1-long-poll-key-for-smoke';
$peer_id = '2000000456';

add_filter(
	'cf7vk_vk_gateway',
	static function ( VkGateway $default, string $token ) use ( $access_token ): VkGateway {
		return $access_token === $token ? new Cf7vk_E1_Fake_Vk_Gateway() : $default;
	},
	10,
	2
);

$bot = new Bot();
$bot->setTitle( 'CF7VK E1 Fake VK Bot' );
$bot->setParam( 'accessToken', $access_token );
$bot->setParam( 'groupId', '1001' );
$bot->setParam( 'apiVersion', Bot::DEFAULT_API_VERSION );
$bot->setParam( 'authCommand', 'join-e1' );
$bot->setParam( 'lastStatus', Bot::STATUS_ONLINE );
$bot->setParam( 'longPollServer', 'https://lp.e1.test' );
$bot->setParam( 'longPollKey', $long_poll_key );
$bot->setParam( 'longPollTs', 'e1-ts-start' );
$bot->publish();

$route = '/wp/v2/cf7vk_bot/' . $bot->getPost()->ID . '/fetch_updates';
$request = new WP_REST_Request( 'POST', $route );
$request->set_param( 'id', $bot->getPost()->ID );

$response = rest_do_request( $request );

if ( is_wp_error( $response ) ) {
	cf7vk_e1_fake_vk_fail(
		'REST fetch updates returned WP_Error.',
		[
			'code'    => $response->get_error_code(),
			'message' => $response->get_error_message(),
		]
	);
}

$status = (int) $response->get_status();
$data = (array) $response->get_data();

if ( $status < 200 || $status >= 300 ) {
	cf7vk_e1_fake_vk_fail(
		'REST fetch updates returned a non-2xx response.',
		[
			'status' => $status,
			'data'   => $data,
		]
	);
}

$reloaded_bot = new Bot( $bot->getPost()->ID );
$chat_posts = get_posts(
	[
		'post_type'      => Client::CPT_CHAT,
		'post_status'    => 'any',
		'posts_per_page' => -1,
		'fields'         => 'ids',
	]
);
$connection_count = 0;
$connection_table = $wpdb->prefix . 'post_connections_cf7_vk';

if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $connection_table ) ) ) {
	$connection_count = (int) $wpdb->get_var(
		$wpdb->prepare(
			"SELECT COUNT(*) FROM `{$connection_table}` WHERE relation = %s AND `from` = %d",
			Client::BOT2CHAT,
			$bot->getPost()->ID
		)
	);
}

$log_rows = (array) $wpdb->get_results(
	"SELECT msg, data FROM `{$wpdb->prefix}cf7vk_log` ORDER BY ID ASC",
	ARRAY_A
);
$log_payload = wp_json_encode( $log_rows );

foreach ( [ $access_token, $long_poll_key, $peer_id ] as $private_value ) {
	if ( '' !== $private_value && false !== strpos( (string) $log_payload, $private_value ) ) {
		cf7vk_e1_fake_vk_fail( 'Raw private VK value leaked into plugin logs.' );
	}
}

if ( empty( $data['cursorAdvanced'] ) || 'e1-ts-next' !== $reloaded_bot->getLongPollTs() ) {
	cf7vk_e1_fake_vk_fail(
		'Long Poll cursor did not advance after the clean REST batch.',
		[
			'cursorAdvanced' => (bool) ( $data['cursorAdvanced'] ?? false ),
			'storedTs'       => $reloaded_bot->getLongPollTs(),
		]
	);
}

if ( 1 !== (int) ( $data['updatesCount'] ?? 0 ) || empty( $data['hasNewChats'] ) || empty( $data['hasNewConnections'] ) ) {
	cf7vk_e1_fake_vk_fail(
		'REST fetch updates did not process the fake VK update.',
		[
			'updatesCount'      => (int) ( $data['updatesCount'] ?? 0 ),
			'hasNewChats'      => (bool) ( $data['hasNewChats'] ?? false ),
			'hasNewConnections' => (bool) ( $data['hasNewConnections'] ?? false ),
		]
	);
}

if ( count( $chat_posts ) < 1 || $connection_count < 1 ) {
	cf7vk_e1_fake_vk_fail(
		'Fake VK update did not create the expected chat relation.',
		[
			'chatCount'       => count( $chat_posts ),
			'connectionCount' => $connection_count,
		]
	);
}

$methods = array_map(
	static fn( array $call ): string => (string) $call['method'],
	Cf7vk_E1_Fake_Vk_Gateway::$calls
);
$evidence = [
	'ok'       => true,
	'route'    => '/wp/v2/cf7vk_bot/{id}/fetch_updates',
	'status'   => $status,
	'result'   => [
		'updatesCount'      => (int) ( $data['updatesCount'] ?? 0 ),
		'errorCount'        => (int) ( $data['errorCount'] ?? 0 ),
		'cursorAdvanced'    => (bool) ( $data['cursorAdvanced'] ?? false ),
		'hasNewChats'      => (bool) ( $data['hasNewChats'] ?? false ),
		'hasNewConnections' => (bool) ( $data['hasNewConnections'] ?? false ),
		'storedTsBucket'    => cf7vk_e1_fake_vk_bucket( $reloaded_bot->getLongPollTs() ),
	],
	'storage'  => [
		'chatCount'       => count( $chat_posts ),
		'connectionCount' => $connection_count,
	],
	'gateway'  => [
		'callCount'  => count( Cf7vk_E1_Fake_Vk_Gateway::$calls ),
		'methods'    => $methods,
		'peerBucket' => cf7vk_e1_fake_vk_bucket( $peer_id ),
	],
	'redaction' => [
		'pluginLogsChecked' => count( $log_rows ),
	],
];

$evidence_payload = wp_json_encode( $evidence );
foreach ( [ $access_token, $long_poll_key, $peer_id ] as $private_value ) {
	if ( '' !== $private_value && false !== strpos( (string) $evidence_payload, $private_value ) ) {
		cf7vk_e1_fake_vk_fail( 'Raw private VK value leaked into fake VK evidence.' );
	}
}

echo wp_json_encode( $evidence, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
echo "\n";
