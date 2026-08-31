<?php

declare( strict_types=1 );

use iTRON\cf7Vk\Vk\VkDeliveryResult;
use iTRON\cf7Vk\Vk\WordPressVkGateway;

require_once __DIR__ . '/Fakes/RecordingVkGateway.php';

final class VkGatewayTest extends Cf7vk_TestCase {
	public function testWordPressGatewayPostsVkApiPayloadAndPreservesSuccessPayload(): void {
		$GLOBALS['wp_remote_post_handler'] = static function (): array {
			return [
				'response' => [ 'code' => 200 ],
				'headers'  => [],
				'body'     => wp_json_encode( [ 'response' => [ 'id' => 123, 'name' => 'Community' ] ] ),
			];
		};

		$result = ( new WordPressVkGateway() )->api(
			'groups.getById',
			[ 'group_id' => 'club123' ],
			'vk1.secret-token-value',
			'5.199'
		);

		$this->assertTrue( $result->ok );
		$this->assertSame( 'groups.getById', $result->method );
		$this->assertSame( 200, $result->status );
		$this->assertSame( [ 'id' => 123, 'name' => 'Community' ], $result->result );
		$this->assertSame( 'https://api.vk.com/method/groups.getById', $GLOBALS['wp_remote_post_requests'][0]['url'] );
		$body = $GLOBALS['wp_remote_post_requests'][0]['args']['body'];
		$this->assertSame( 'club123', $body['group_id'] );
		$this->assertSame( 'vk1.secret-token-value', $body['access_token'] );
		$this->assertSame( '5.199', $body['v'] );
	}

	public function testWordPressGatewayNormalizesVkApiErrorWithoutLeakingToken(): void {
		$token = 'vk1.secret-token-value-with-length';
		$GLOBALS['wp_remote_post_handler'] = static function () use ( $token ): array {
			return [
				'response' => [ 'code' => 200 ],
				'headers'  => [],
				'body'     => wp_json_encode(
					[
						'error' => [
							'error_code' => 5,
							'error_msg'  => 'Authorization failed for ' . $token,
							'request_params' => [
								[
									'key'   => 'access_token',
									'value' => $token,
								],
							],
						],
					]
				),
			];
		};

		$result = ( new WordPressVkGateway() )->api( 'groups.getById', [], $token, '5.199' );

		$this->assertFalse( $result->ok );
		$this->assertSame( VkDeliveryResult::ERROR_VK_API, $result->errorType );
		$this->assertSame( 5, $result->errorCode );
		$this->assertFalse( str_contains( $result->description, $token ) );
		$this->assertFalse( str_contains( wp_json_encode( $result->result ), $token ) );
		$this->assertTrue( str_contains( $result->description, '[vk-access-token]' ) );
	}

	public function testWordPressGatewayNormalizesHttpErrorAndRetryAfterHeader(): void {
		$GLOBALS['wp_remote_post_handler'] = static fn(): array => [
			'response' => [ 'code' => 429 ],
			'headers'  => [ 'retry-after' => '12' ],
			'body'     => wp_json_encode( [ 'error' => [ 'error_code' => 6, 'error_msg' => 'Too many requests' ] ] ),
		];

		$result = ( new WordPressVkGateway() )->api( 'messages.send', [], 'vk1.secret-token-value', '5.199' );

		$this->assertFalse( $result->ok );
		$this->assertSame( VkDeliveryResult::ERROR_HTTP, $result->errorType );
		$this->assertSame( 429, $result->status );
		$this->assertSame( 429, $result->errorCode );
		$this->assertSame( 12, $result->retryAfter );
	}

	public function testWordPressGatewayNormalizesMalformedJson(): void {
		$GLOBALS['wp_remote_post_handler'] = static fn(): array => [
			'response' => [ 'code' => 200 ],
			'headers'  => [],
			'body'     => '<html>bad gateway</html>',
		];

		$result = ( new WordPressVkGateway() )->api( 'users.get', [], 'vk1.secret-token-value', '5.199' );

		$this->assertFalse( $result->ok );
		$this->assertSame( VkDeliveryResult::ERROR_MALFORMED_RESPONSE, $result->errorType );
		$this->assertSame( 200, $result->status );
	}

	public function testWordPressGatewayNormalizesMissingResponsePayload(): void {
		$GLOBALS['wp_remote_post_handler'] = static fn(): array => [
			'response' => [ 'code' => 200 ],
			'headers'  => [],
			'body'     => wp_json_encode( [ 'unexpected' => true ] ),
		];

		$result = ( new WordPressVkGateway() )->api( 'users.get', [], 'vk1.secret-token-value', '5.199' );

		$this->assertFalse( $result->ok );
		$this->assertSame( VkDeliveryResult::ERROR_MISSING_RESPONSE, $result->errorType );
		$this->assertSame( [ 'unexpected' => true ], $result->result );
	}

	public function testWordPressGatewayNormalizesWpErrorWithoutLeakingToken(): void {
		$token = 'vk1.secret-token-value-with-length';
		$GLOBALS['wp_remote_post_handler'] = static function ( string $url ) use ( $token ): WP_Error {
			return new WP_Error( 'http_request_failed', 'Could not connect to ' . $url . '?access_token=' . rawurlencode( $token ) );
		};

		$result = ( new WordPressVkGateway() )->api( 'users.get', [], $token, '5.199' );

		$this->assertFalse( $result->ok );
		$this->assertSame( VkDeliveryResult::ERROR_TRANSPORT, $result->errorType );
		$this->assertSame( 0, $result->status );
		$this->assertFalse( str_contains( $result->description, $token ) );
		$this->assertFalse( str_contains( $result->description, rawurlencode( $token ) ) );
		$this->assertTrue( str_contains( $result->description, '[vk-access-token]' ) );
	}

	public function testWordPressGatewayNormalizesThrownTransportException(): void {
		$token = 'vk1.secret-token-value-with-length';
		$GLOBALS['wp_remote_post_handler'] = static function () use ( $token ): void {
			throw new RuntimeException( 'Socket failure for ' . $token );
		};

		$result = ( new WordPressVkGateway() )->api( 'users.get', [], $token, '5.199' );

		$this->assertFalse( $result->ok );
		$this->assertSame( VkDeliveryResult::ERROR_TRANSPORT, $result->errorType );
		$this->assertFalse( str_contains( $result->description, $token ) );
	}

	public function testWordPressGatewayChecksLongPollAndPreservesProtocolFailures(): void {
		$GLOBALS['wp_remote_get_handler'] = static fn(): array => [
			'response' => [ 'code' => 200 ],
			'headers'  => [],
			'body'     => wp_json_encode( [ 'failed' => 1, 'ts' => '22' ] ),
		];

		$result = ( new WordPressVkGateway() )->longPoll( 'https://lp.vk.test/server', 'secret-key', '10', 500 );

		$this->assertTrue( $result->ok );
		$this->assertSame( 'long_poll', $result->method );
		$this->assertSame( [ 'failed' => 1, 'ts' => '22' ], $result->result );
		$request = $GLOBALS['wp_remote_get_requests'][0];
		$this->assertStringContainsString( 'wait=90', $request['url'] );
		$this->assertSame( 95, $request['args']['timeout'] );
	}

	public function testWordPressGatewayKeepsLegacyLongPollTimeoutLowerBound(): void {
		( new WordPressVkGateway() )->longPoll( 'https://lp.vk.test/server', 'secret-key', '10', 0 );

		$request = $GLOBALS['wp_remote_get_requests'][0];
		$this->assertStringContainsString( 'wait=1', $request['url'] );
		$this->assertSame( 10, $request['args']['timeout'] );
	}

	public function testWordPressGatewayNormalizesLongPollHttpFailureWithoutLeakingKey(): void {
		$key = 'very-secret-long-poll-key';
		$GLOBALS['wp_remote_get_handler'] = static function ( string $url ) use ( $key ): WP_Error {
			return new WP_Error( 'http_request_failed', 'Could not connect to ' . $url . '&key=' . rawurlencode( $key ) );
		};

		$result = ( new WordPressVkGateway() )->longPoll( 'https://lp.vk.test/server', $key, '10', 25 );

		$this->assertFalse( $result->ok );
		$this->assertSame( VkDeliveryResult::ERROR_TRANSPORT, $result->errorType );
		$this->assertFalse( str_contains( $result->description, $key ) );
		$this->assertFalse( str_contains( $result->description, rawurlencode( $key ) ) );
		$this->assertTrue( str_contains( $result->description, '[vk-long-poll-key]' ) );
	}

	public function testRecordingFakeGatewayPreservesOrderedCallsAndQueuedResults(): void {
		$gateway = new Cf7vk_RecordingVkGateway();
		$gateway->queue( 'groups.getById', VkDeliveryResult::success( 'groups.getById', [ 'id' => 10 ] ) );
		$gateway->queue( 'messages.send', VkDeliveryResult::failure( 'messages.send', 200, 5, 'Auth failed', null, VkDeliveryResult::ERROR_VK_API ) );

		$community = $gateway->api( 'groups.getById', [ 'group_id' => '10' ], 'token-a', '5.199' );
		$send = $gateway->api( 'messages.send', [ 'peer_id' => '1' ], 'token-a', '5.199' );

		$this->assertTrue( $community->ok );
		$this->assertFalse( $send->ok );
		$this->assertSame( 'groups.getById', $gateway->calls[0]['method'] );
		$this->assertSame( 'messages.send', $gateway->calls[1]['method'] );
		$this->assertSame( [ 'peer_id' => '1' ], $gateway->calls[1]['params'] );
	}
}
