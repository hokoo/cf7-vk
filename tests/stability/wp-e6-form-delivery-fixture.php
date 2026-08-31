<?php
/**
 * Seeds the E6 fake VK form-delivery fixture in an isolated WordPress install.
 *
 * Intended to be executed with WP-CLI only:
 * wp eval-file /e6-tests/wp-e6-form-delivery-fixture.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit( 1 );
}

use iTRON\cf7Vk\Bot;
use iTRON\cf7Vk\Channel;
use iTRON\cf7Vk\Chat;
use iTRON\cf7Vk\Form;

if ( ! function_exists( 'is_plugin_active' ) ) {
	require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

$admin_user = get_user_by( 'login', 'admin' );
if ( $admin_user ) {
	wp_set_current_user( (int) $admin_user->ID );
}

$mu_dir = WP_CONTENT_DIR . '/mu-plugins';
if ( ! is_dir( $mu_dir ) ) {
	wp_mkdir_p( $mu_dir );
}

$mu_plugin = <<<'PHP'
<?php
/**
 * E6 fake VK transport and browser controls.
 * Created only inside the ephemeral Docker volume.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function cf7vk_e6_peer_bucket( string $peer_id ): string {
	return substr( hash( 'sha256', trim( $peer_id ) ), 0, 16 );
}

function cf7vk_e6_token_hash( string $token ): string {
	return substr( hash( 'sha256', trim( $token ) ), 0, 16 );
}

function cf7vk_e6_extract_markers( string $message ): array {
	preg_match_all( '/cf7vk-e6(?:-[a-z]+)?-[0-9]+/', $message, $matches );

	return array_values( array_unique( $matches[0] ?? [] ) );
}

function cf7vk_e6_empty_vk_state(): array {
	return [
		'schema'   => 1,
		'active'   => true,
		'calls'    => [],
		'failures' => [],
		'updates'  => [],
		'profiles' => [],
	];
}

function cf7vk_e6_vk_state(): array {
	$state = get_option( 'cf7vk_e6_fake_vk_state', [] );

	return is_array( $state )
		? array_merge( cf7vk_e6_empty_vk_state(), $state )
		: cf7vk_e6_empty_vk_state();
}

function cf7vk_e6_save_vk_state( array $state ): void {
	update_option( 'cf7vk_e6_fake_vk_state', array_merge( cf7vk_e6_empty_vk_state(), $state ), false );
}

function cf7vk_e6_fixture(): array {
	$fixture = get_option( 'cf7vk_e6_fixture', [] );

	return is_array( $fixture ) ? $fixture : [];
}

function cf7vk_e6_save_fixture( array $fixture ): array {
	update_option( 'cf7vk_e6_fixture', $fixture, false );

	return $fixture;
}

function cf7vk_e6_delete_posts_by_type( string $post_type ): int {
	$post_ids = get_posts(
		[
			'post_type'      => $post_type,
			'post_status'    => 'any',
			'fields'         => 'ids',
			'posts_per_page' => -1,
		]
	);

	foreach ( $post_ids as $post_id ) {
		wp_delete_post( (int) $post_id, true );
	}

	return count( $post_ids );
}

function cf7vk_e6_seed_chat( string $peer_id, string $title, string $username ): array {
	if ( ! class_exists( '\iTRON\cf7Vk\Chat' ) ) {
		return [
			'error' => 'chat_class_missing',
		];
	}

	$chat = new \iTRON\cf7Vk\Chat();
	$chat->setTitle( $title );
	$chat->setPeerId( $peer_id );
	$chat->setUserId( $peer_id );
	$chat->setChatType( \iTRON\cf7Vk\Chat::TYPE_PRIVATE );
	$chat->setDisplayName( $title );
	$chat->setUsername( $username );
	$chat->setConnectedAt( gmdate( 'c' ) );
	wp_update_post(
		[
			'ID'          => $chat->getPost()->ID,
			'post_status' => 'publish',
		]
	);

	return [
		'post_id'     => (int) $chat->getPost()->ID,
		'peer_id'     => $chat->getPeerId(),
		'peer_bucket' => cf7vk_e6_peer_bucket( $chat->getPeerId() ),
		'title'       => $title,
		'username'    => $username,
	];
}

function cf7vk_e6_reset_admin_flow(): array {
	$deleted = [
		'bots'     => cf7vk_e6_delete_posts_by_type( 'cf7vk_bot' ),
		'channels' => cf7vk_e6_delete_posts_by_type( 'cf7vk_channel' ),
		'chats'    => cf7vk_e6_delete_posts_by_type( 'cf7vk_chat' ),
	];

	$state = cf7vk_e6_empty_vk_state();
	cf7vk_e6_save_vk_state( $state );

	$safety_chat = cf7vk_e6_seed_chat( '990199', 'E6 Safety Chat', 'e6_safety_chat' );
	$fixture     = cf7vk_e6_fixture();
	$fixture['admin_flow'] = [
		'safety_chat'        => $safety_chat,
		'update_peer_id'     => '990123',
		'update_peer_bucket' => cf7vk_e6_peer_bucket( '990123' ),
		'update_chat_title'  => 'E6 Admin Chat',
		'update_username'    => 'e6_admin_chat',
		'group_id'           => '660002',
	];
	cf7vk_e6_save_fixture( $fixture );

	return array_merge( cf7vk_e6_public_control_payload( $state, $fixture ), [ 'deleted' => $deleted ] );
}

function cf7vk_e6_script_start_update(): array {
	$fixture    = cf7vk_e6_fixture();
	$admin_flow = is_array( $fixture['admin_flow'] ?? null ) ? $fixture['admin_flow'] : [];
	$peer_id    = sanitize_text_field( wp_unslash( $_POST['peer_id'] ?? ( $admin_flow['update_peer_id'] ?? '990123' ) ) );
	$title      = sanitize_text_field( wp_unslash( $_POST['title'] ?? ( $admin_flow['update_chat_title'] ?? 'E6 Admin Chat' ) ) );
	$username   = sanitize_key( wp_unslash( $_POST['username'] ?? ( $admin_flow['update_username'] ?? 'e6_admin_chat' ) ) );
	$update_id  = max( 1, (int) ( $_POST['update_id'] ?? ( 990000 + time() % 100000 ) ) );

	$state = cf7vk_e6_vk_state();
	$state['updates'] = [
		[
			'type'     => 'message_new',
			'event_id' => 'e6-' . $update_id,
			'object'   => [
				'message' => [
					'id'                      => $update_id + 1,
					'conversation_message_id' => $update_id + 2,
					'date'                    => time(),
					'peer_id'                 => $peer_id,
					'from_id'                 => $peer_id,
					'text'                    => 'start',
				],
			],
		],
	];
	$state['profiles'][ $peer_id ] = [
		'id'          => (int) $peer_id,
		'first_name'  => $title,
		'last_name'   => '',
		'screen_name' => $username,
	];
	cf7vk_e6_save_vk_state( $state );

	$fixture['admin_flow'] = array_merge(
		$admin_flow,
		[
			'update_peer_id'     => $peer_id,
			'update_peer_bucket' => cf7vk_e6_peer_bucket( $peer_id ),
			'update_chat_title'  => $title,
			'update_username'    => $username,
			'update_id'          => $update_id,
		]
	);
	cf7vk_e6_save_fixture( $fixture );

	return cf7vk_e6_public_control_payload( $state, $fixture );
}

function cf7vk_e6_sanitize_params( array $params ): array {
	$sanitized = [];

	foreach ( $params as $key => $value ) {
		$key = (string) $key;

		if ( preg_match( '/token|secret|password|key/i', $key ) ) {
			$sanitized[ $key ] = '[redacted]';
			continue;
		}

		if ( 'peer_id' === $key ) {
			$sanitized['peer_bucket'] = cf7vk_e6_peer_bucket( (string) $value );
			continue;
		}

		if ( 'message' === $key ) {
			$message = (string) $value;
			$sanitized['message'] = [
				'length'  => strlen( $message ),
				'markers' => cf7vk_e6_extract_markers( $message ),
			];
			continue;
		}

		if ( 'user_ids' === $key ) {
			$user_ids = array_filter( array_map( 'trim', explode( ',', (string) $value ) ) );
			$sanitized['user_buckets'] = array_map( 'cf7vk_e6_peer_bucket', $user_ids );
			continue;
		}

		if ( is_scalar( $value ) || null === $value ) {
			$sanitized[ $key ] = $value;
			continue;
		}

		$sanitized[ $key ] = is_array( $value ) ? cf7vk_e6_sanitize_params( $value ) : '[object]';
	}

	return $sanitized;
}

function cf7vk_e6_public_fixture( array $fixture ): array {
	$public = [];
	foreach ( [ 'schema', 'form_id', 'page_id', 'page_url', 'bot_post_id', 'channel_post_id', 'chat_post_ids', 'expected_peer_buckets', 'unexpected_peer_bucket', 'bot_token_hash' ] as $key ) {
		if ( array_key_exists( $key, $fixture ) ) {
			$public[ $key ] = $fixture[ $key ];
		}
	}

	$admin_flow = is_array( $fixture['admin_flow'] ?? null ) ? $fixture['admin_flow'] : [];
	if ( $admin_flow ) {
		$public['admin_flow'] = [];
		$safety_chat = is_array( $admin_flow['safety_chat'] ?? null ) ? $admin_flow['safety_chat'] : [];
		if ( $safety_chat ) {
			$public['admin_flow']['safety_chat'] = [];
			foreach ( [ 'post_id', 'peer_bucket' ] as $key ) {
				if ( array_key_exists( $key, $safety_chat ) ) {
					$public['admin_flow']['safety_chat'][ $key ] = $safety_chat[ $key ];
				}
			}
		}

		foreach ( [ 'update_peer_bucket', 'update_id', 'group_id' ] as $key ) {
			if ( array_key_exists( $key, $admin_flow ) ) {
				$public['admin_flow'][ $key ] = $admin_flow[ $key ];
			}
		}
	}

	return $public;
}

function cf7vk_e6_public_vk_state( array $state ): array {
	$state   = array_merge( cf7vk_e6_empty_vk_state(), $state );
	$updates = [];

	foreach ( $state['updates'] as $update ) {
		if ( ! is_array( $update ) ) {
			continue;
		}

		$message = is_array( $update['object']['message'] ?? null ) ? $update['object']['message'] : [];
		$updates[] = [
			'type'        => (string) ( $update['type'] ?? '' ),
			'event_id'    => (string) ( $update['event_id'] ?? '' ),
			'peer_bucket' => cf7vk_e6_peer_bucket( (string) ( $message['peer_id'] ?? '' ) ),
			'message_id'  => (int) ( $message['id'] ?? 0 ),
		];
	}

	return [
		'schema'   => (int) ( $state['schema'] ?? 1 ),
		'active'   => (bool) ( $state['active'] ?? false ),
		'calls'    => is_array( $state['calls'] ?? null ) ? $state['calls'] : [],
		'failures' => is_array( $state['failures'] ?? null ) ? $state['failures'] : [],
		'updates'  => $updates,
	];
}

function cf7vk_e6_public_control_payload( $state = null, $fixture = null ): array {
	$state   = is_array( $state ) ? $state : cf7vk_e6_vk_state();
	$fixture = is_array( $fixture ) ? $fixture : cf7vk_e6_fixture();

	return [
		'active'  => true,
		'vk'      => cf7vk_e6_public_vk_state( $state ),
		'fixture' => cf7vk_e6_public_fixture( $fixture ),
	];
}

function cf7vk_e6_response_summary( \iTRON\cf7Vk\Vk\VkDeliveryResult $result, string $category ): array {
	$summary = [
		'status'           => $result->status,
		'ok'               => $result->ok,
		'error_code'       => $result->errorCode,
		'description'      => $result->description,
		'failure_category' => $category,
	];

	if ( is_int( $result->result ) ) {
		$summary['message_id'] = $result->result;
	}

	return $summary;
}

function cf7vk_e6_record_vk_call( string $method, string $access_token, string $api_version, array $params, \iTRON\cf7Vk\Vk\VkDeliveryResult $result, string $category ): void {
	$state = cf7vk_e6_vk_state();
	$state['calls'][] = [
		'index'       => count( $state['calls'] ) + 1,
		'kind'        => 'api',
		'method'      => $method,
		'token_hash'  => cf7vk_e6_token_hash( $access_token ),
		'api_version' => $api_version,
		'params'      => cf7vk_e6_sanitize_params( $params ),
		'response'    => cf7vk_e6_response_summary( $result, $category ),
		'captured_at' => gmdate( 'c' ),
	];
	cf7vk_e6_save_vk_state( $state );
}

function cf7vk_e6_record_long_poll_call( string $server, string $key, string $ts, int $wait, \iTRON\cf7Vk\Vk\VkDeliveryResult $result ): void {
	$state = cf7vk_e6_vk_state();
	$state['calls'][] = [
		'index'       => count( $state['calls'] ) + 1,
		'kind'        => 'long_poll',
		'method'      => 'long_poll',
		'server_hash' => substr( hash( 'sha256', $server ), 0, 16 ),
		'key_hash'    => substr( hash( 'sha256', $key ), 0, 16 ),
		'ts'          => $ts,
		'wait'        => $wait,
		'response'    => cf7vk_e6_response_summary( $result, 'success' ),
		'captured_at' => gmdate( 'c' ),
	];
	cf7vk_e6_save_vk_state( $state );
}

function cf7vk_e6_take_scripted_failure( string $method, array $params, array &$state ): ?\iTRON\cf7Vk\Vk\VkDeliveryResult {
	$peer_bucket = isset( $params['peer_id'] ) ? cf7vk_e6_peer_bucket( (string) $params['peer_id'] ) : '';
	foreach ( array_filter( [ $peer_bucket ? $method . ':' . $peer_bucket : '', $method ] ) as $key ) {
		$count = (int) ( $state['failures'][ $key ] ?? 0 );
		if ( $count < 1 ) {
			continue;
		}

		$state['failures'][ $key ] = $count - 1;

		return \iTRON\cf7Vk\Vk\VkDeliveryResult::failure(
			$method,
			200,
			400,
			'Synthetic VK failure for E6.',
			null,
			\iTRON\cf7Vk\Vk\VkDeliveryResult::ERROR_VK_API,
			[
				'error_code' => 400,
				'error_msg'  => 'Synthetic VK failure for E6.',
			]
		);
	}

	return null;
}

function cf7vk_e6_default_vk_result( string $method, array $params, array $state ): \iTRON\cf7Vk\Vk\VkDeliveryResult {
	if ( 'groups.getById' === $method ) {
		$group_id = (string) ( $params['group_id'] ?? '660001' );

		return \iTRON\cf7Vk\Vk\VkDeliveryResult::success(
			$method,
			[
				'groups' => [
					[
						'id'          => absint( $group_id ),
						'name'        => 'E6 Fake VK Community',
						'screen_name' => 'e6_fake_vk_community',
					],
				],
			]
		);
	}

	if ( 'groups.getLongPollServer' === $method ) {
		return \iTRON\cf7Vk\Vk\VkDeliveryResult::success(
			$method,
			[
				'server' => 'https://lp.vk.example/e6',
				'key'    => 'e6-long-poll-key',
				'ts'     => '1000',
			]
		);
	}

	if ( 'messages.getByConversationMessageId' === $method ) {
		return \iTRON\cf7Vk\Vk\VkDeliveryResult::success(
			$method,
			[
				'items' => [
					[
						'peer_id'                 => (int) ( $params['peer_id'] ?? 0 ),
						'conversation_message_id' => (int) ( $params['conversation_message_ids'] ?? 0 ),
					],
				],
			]
		);
	}

	if ( 'users.get' === $method ) {
		$user_ids = array_filter( array_map( 'trim', explode( ',', (string) ( $params['user_ids'] ?? '' ) ) ) );
		$profiles = [];
		foreach ( $user_ids as $user_id ) {
			$stored     = is_array( $state['profiles'][ $user_id ] ?? null ) ? $state['profiles'][ $user_id ] : [];
			$profiles[] = array_merge(
				[
					'id'          => (int) $user_id,
					'first_name'  => 'E6 VK User',
					'last_name'   => '',
					'screen_name' => 'e6_vk_user_' . cf7vk_e6_peer_bucket( $user_id ),
				],
				$stored
			);
		}

		return \iTRON\cf7Vk\Vk\VkDeliveryResult::success( $method, $profiles );
	}

	if ( 'messages.send' === $method ) {
		return \iTRON\cf7Vk\Vk\VkDeliveryResult::success( $method, 900000 + count( $state['calls'] ?? [] ) + 1 );
	}

	return \iTRON\cf7Vk\Vk\VkDeliveryResult::failure(
		$method,
		404,
		404,
		'Synthetic VK method is not implemented.',
		null,
		\iTRON\cf7Vk\Vk\VkDeliveryResult::ERROR_VK_API,
		[
			'error_code' => 404,
			'error_msg'  => 'Synthetic VK method is not implemented.',
		]
	);
}

add_filter(
	'cf7vk_vk_gateway',
	static function () {
		return new class() implements \iTRON\cf7Vk\Vk\VkGateway {
			public function api( string $method, array $params, string $accessToken, string $apiVersion ): \iTRON\cf7Vk\Vk\VkDeliveryResult {
				$state    = cf7vk_e6_vk_state();
				$result   = cf7vk_e6_take_scripted_failure( $method, $params, $state );
				$category = $result ? 'scripted_failure' : 'success';

				if ( null === $result ) {
					$result = cf7vk_e6_default_vk_result( $method, $params, $state );
					if ( ! $result->ok ) {
						$category = 'unsupported_method';
					}
				}

				cf7vk_e6_save_vk_state( $state );
				cf7vk_e6_record_vk_call( $method, $accessToken, $apiVersion, $params, $result, $category );

				return $result;
			}

			public function longPoll( string $server, string $key, string $ts, int $wait = 25 ): \iTRON\cf7Vk\Vk\VkDeliveryResult {
				$state  = cf7vk_e6_vk_state();
				$result = \iTRON\cf7Vk\Vk\VkDeliveryResult::success(
					'long_poll',
					[
						'ts'      => (string) ( max( 1000, (int) $ts ) + 1 ),
						'updates' => array_values( $state['updates'] ?? [] ),
					]
				);

				cf7vk_e6_record_long_poll_call( $server, $key, $ts, $wait, $result );

				return $result;
			}
		};
	},
	10,
	0
);

add_filter(
	'pre_http_request',
	static function ( $preempt, $args, $url ) {
		$url = (string) $url;
		if (
			false === strpos( $url, 'api.vk.com' ) &&
			false === strpos( $url, 'vk.com' ) &&
			false === strpos( $url, 'lp.vk.example' )
		) {
			return $preempt;
		}

		$state = cf7vk_e6_vk_state();
		$state['calls'][] = [
			'index'       => count( $state['calls'] ) + 1,
			'kind'        => 'blocked_http',
			'method'      => 'live_vk_http_attempt',
			'url_hash'    => substr( hash( 'sha256', $url ), 0, 16 ),
			'captured_at' => gmdate( 'c' ),
			'response'    => [
				'ok'               => false,
				'failure_category' => 'live_vk_egress_blocked',
			],
		];
		cf7vk_e6_save_vk_state( $state );

		return new WP_Error( 'cf7vk_e6_live_vk_blocked', 'Live VK egress is blocked in E6.' );
	},
	10,
	3
);

add_filter(
	'wpcf7_skip_mail',
	static function ( $skip_mail, $contact_form ) {
		$fixture = get_option( 'cf7vk_e6_fixture', [] );
		$form_id = is_object( $contact_form ) && method_exists( $contact_form, 'id' ) ? (int) $contact_form->id() : 0;
		if ( $form_id && $form_id === (int) ( $fixture['form_id'] ?? 0 ) ) {
			return true;
		}

		return $skip_mail;
	},
	10,
	2
);

function cf7vk_e6_control_response( array $data ): void {
	wp_send_json_success( $data );
}

function cf7vk_e6_fake_vk_control(): void {
	$action = sanitize_key( wp_unslash( $_POST['e6_action'] ?? '' ) );
	if ( 'reset' === $action ) {
		$state = cf7vk_e6_empty_vk_state();
		cf7vk_e6_save_vk_state( $state );
		cf7vk_e6_control_response( cf7vk_e6_public_control_payload( $state, cf7vk_e6_fixture() ) );
	}

	if ( 'admin-reset' === $action ) {
		cf7vk_e6_control_response( cf7vk_e6_reset_admin_flow() );
	}

	if ( 'script-failure' === $action ) {
		$method      = sanitize_text_field( wp_unslash( $_POST['method'] ?? 'messages.send' ) );
		$peer_bucket = sanitize_text_field( wp_unslash( $_POST['peer_bucket'] ?? '' ) );
		$count       = max( 1, (int) ( $_POST['count'] ?? 1 ) );
		$key         = $peer_bucket ? $method . ':' . $peer_bucket : $method;
		$state       = cf7vk_e6_vk_state();
		$state['failures'][ $key ] = (int) ( $state['failures'][ $key ] ?? 0 ) + $count;
		cf7vk_e6_save_vk_state( $state );
		cf7vk_e6_control_response( cf7vk_e6_public_control_payload( $state, cf7vk_e6_fixture() ) );
	}

	if ( 'script-start-update' === $action ) {
		cf7vk_e6_control_response( cf7vk_e6_script_start_update() );
	}

	if ( 'evidence' === $action ) {
		cf7vk_e6_control_response( cf7vk_e6_public_control_payload() );
	}

	wp_send_json_error( [ 'message' => 'unknown_action' ], 400 );
}

add_action( 'wp_ajax_cf7vk_e6_fake_vk_control', 'cf7vk_e6_fake_vk_control' );
add_action( 'wp_ajax_nopriv_cf7vk_e6_fake_vk_control', 'cf7vk_e6_fake_vk_control' );
PHP;

file_put_contents( $mu_dir . '/cf7vk-e6-fake-vk.php', $mu_plugin );
update_option( 'cf7vk_e6_fake_vk_state', [ 'schema' => 1, 'active' => true, 'calls' => [], 'failures' => [], 'updates' => [], 'profiles' => [] ], false );

if ( ! function_exists( 'wpcf7_save_contact_form' ) ) {
	echo wp_json_encode(
		[
			'schema' => 1,
			'error'  => 'Contact Form 7 API is not available.',
		],
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
	) . "\n";
	exit( 1 );
}

$form_template = <<<'FORM'
<label> Your name
    [text* your-name] </label>

<label> Your email
    [email* your-email] </label>

<label> Subject
    [text* your-subject] </label>

<label> E6 marker
    [text* e6-marker] </label>

<label> Message
    [textarea your-message] </label>

[submit "Send"]
FORM;

$mail_template = [
	'subject'            => 'CF7VK E6 [your-subject]',
	'sender'             => 'CF7VK E6 <admin@example.test>',
	'body'               => "E6 marker: [e6-marker]\nName: [your-name]\nEmail: [your-email]\nSubject: [your-subject]\nMessage: [your-message]",
	'recipient'          => 'admin@example.test',
	'additional_headers' => 'Reply-To: [your-email]',
	'attachments'        => '',
	'use_html'           => 0,
	'exclude_blank'      => 0,
];

$contact_form = wpcf7_save_contact_form(
	[
		'id'                  => -1,
		'title'               => 'CF7VK E6 Delivery Form',
		'locale'              => 'en_US',
		'form'                => $form_template,
		'mail'                => $mail_template,
		'additional_settings' => "skip_mail: on\n",
	],
	'save'
);

if ( ! $contact_form || ! method_exists( $contact_form, 'id' ) ) {
	echo wp_json_encode(
		[
			'schema' => 1,
			'error'  => 'Could not create Contact Form 7 form.',
		],
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
	) . "\n";
	exit( 1 );
}

$form_id = (int) $contact_form->id();
$page_id = wp_insert_post(
	[
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_title'   => 'CF7VK E6 Delivery Page',
		'post_content' => sprintf( '[contact-form-7 id="%d" title="CF7VK E6 Delivery Form"]', $form_id ),
	],
	true
);

if ( is_wp_error( $page_id ) || ! $page_id ) {
	echo wp_json_encode(
		[
			'schema' => 1,
			'error'  => is_wp_error( $page_id ) ? $page_id->get_error_message() : 'Could not create public page.',
		],
		JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
	) . "\n";
	exit( 1 );
}

$peer_bucket = static function ( string $peer_id ): string {
	return substr( hash( 'sha256', trim( $peer_id ) ), 0, 16 );
};

$bot_token = 'vk1.e6_fake_token_canary_public';
$bot       = new Bot();
$bot->setTitle( 'E6 Fake VK Community' );
$bot->setGroupId( '660001' );
$bot->setAccessToken( $bot_token );
$bot->setApiVersion( Bot::DEFAULT_API_VERSION );
$bot->setAuthCommand( 'start' );
$bot->setLongPollServer( 'https://lp.vk.example/e6' );
$bot->setLongPollKey( 'e6-long-poll-key' );
$bot->setLongPollTs( '1000' );
$bot->setLastStatus( Bot::STATUS_ONLINE );
wp_update_post(
	[
		'ID'          => $bot->getPost()->ID,
		'post_status' => 'publish',
	]
);

$channel = new Channel();
$channel->setTitle( 'E6 Delivery Channel' );
$channel->savePost();
wp_update_post(
	[
		'ID'          => $channel->getPost()->ID,
		'post_status' => 'publish',
	]
);

$expected_peer_ids = [ '990001', '990002' ];
$chat_posts        = [];
foreach ( $expected_peer_ids as $index => $peer_id ) {
	$chat = new Chat();
	$chat->setTitle( sprintf( 'E6 Delivery Chat %d', $index + 1 ) );
	$chat->setPeerId( $peer_id );
	$chat->setUserId( $peer_id );
	$chat->setChatType( Chat::TYPE_PRIVATE );
	$chat->setDisplayName( sprintf( 'E6 Delivery Chat %d', $index + 1 ) );
	$chat->setUsername( sprintf( 'e6_delivery_chat_%d', $index + 1 ) );
	$chat->setConnectedAt( gmdate( 'c' ) );
	wp_update_post(
		[
			'ID'          => $chat->getPost()->ID,
			'post_status' => 'publish',
		]
	);

	$bot->connectChat( $chat );
	$chat->setActivated( $bot );
	$channel->connectChat( $chat );
	$chat_posts[] = $chat->getPost()->ID;
}

$unrelated_chat = new Chat();
$unrelated_chat->setTitle( 'E6 Unrelated Chat' );
$unrelated_chat->setPeerId( '990099' );
$unrelated_chat->setUserId( '990099' );
$unrelated_chat->setChatType( Chat::TYPE_PRIVATE );
$unrelated_chat->setDisplayName( 'E6 Unrelated Chat' );
$unrelated_chat->setUsername( 'e6_unrelated_chat' );
$unrelated_chat->setConnectedAt( gmdate( 'c' ) );
wp_update_post(
	[
		'ID'          => $unrelated_chat->getPost()->ID,
		'post_status' => 'publish',
	]
);

$channel->connectBot( $bot );
( new Form( $form_id ) )->connectChannel( $channel );

$fixture = [
	'schema'                 => 1,
	'form_id'                => $form_id,
	'page_id'                => (int) $page_id,
	'page_url'               => get_permalink( $page_id ),
	'bot_post_id'            => $bot->getPost()->ID,
	'channel_post_id'        => $channel->getPost()->ID,
	'chat_post_ids'          => $chat_posts,
	'expected_peer_ids'      => $expected_peer_ids,
	'expected_peer_buckets'  => array_map( $peer_bucket, $expected_peer_ids ),
	'unexpected_peer_id'     => $unrelated_chat->getPeerId(),
	'unexpected_peer_bucket' => $peer_bucket( $unrelated_chat->getPeerId() ),
	'bot_token_hash'         => substr( hash( 'sha256', $bot_token ), 0, 16 ),
];

update_option( 'cf7vk_e6_fixture', $fixture, false );

$public_fixture = [
	'schema'                 => $fixture['schema'],
	'form_id'                => $fixture['form_id'],
	'page_id'                => $fixture['page_id'],
	'page_url'               => $fixture['page_url'],
	'bot_post_id'            => $fixture['bot_post_id'],
	'channel_post_id'        => $fixture['channel_post_id'],
	'chat_post_ids'          => $fixture['chat_post_ids'],
	'expected_peer_buckets'  => $fixture['expected_peer_buckets'],
	'unexpected_peer_bucket' => $fixture['unexpected_peer_bucket'],
	'bot_token_hash'         => $fixture['bot_token_hash'],
];

echo wp_json_encode(
	[
		'schema'        => 1,
		'plugin_active' => is_plugin_active( 'message-bridge-for-contact-form-7-and-vk/cf7-vk.php' ),
		'cf7_active'    => is_plugin_active( 'contact-form-7/wp-contact-form-7.php' ),
		'mu_plugin'     => $mu_dir . '/cf7vk-e6-fake-vk.php',
		'fixture'       => $public_fixture,
	],
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
) . "\n";
