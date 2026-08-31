<?php

declare( strict_types=1 );

use iTRON\cf7Vk\Exceptions\TransportNotConfigured;
use iTRON\cf7Vk\Exceptions\VkApiException;
use iTRON\cf7Vk\VkApi;

final class VkApiTest extends Cf7vk_TestCase {
	public function testConstructorRejectsEmptyAccessToken(): void {
		try {
			new VkApi( '' );
			$this->fail( 'Expected missing access token exception.' );
		} catch ( TransportNotConfigured $exception ) {
			$this->assertStringContainsString( 'access token', $exception->getMessage() );
		}
	}

	public function testSendMessagePostsExpectedVkApiPayload(): void {
		$GLOBALS['wp_remote_post_handler'] = static function (): array {
			return [
				'response' => [ 'code' => 200 ],
				'headers' => [],
				'body' => '{"response":321}',
			];
		};

		$api = new VkApi( 'vk-test-token', '5.199' );
		$message_id = $api->sendMessage( '12345', 'Hello VK' );

		$this->assertSame( 321, $message_id );
		$this->assertSame( 'https://api.vk.com/method/messages.send', $GLOBALS['wp_remote_post_requests'][0]['url'] );
		$body = $GLOBALS['wp_remote_post_requests'][0]['args']['body'];
		$this->assertSame( '12345', $body['peer_id'] );
		$this->assertSame( 'Hello VK', $body['message'] );
		$this->assertSame( 'vk-test-token', $body['access_token'] );
		$this->assertSame( '5.199', $body['v'] );
		$this->assertArrayHasKey( 'random_id', $body );
	}

	public function testApiErrorIsExposedAsVkApiExceptionWithPayload(): void {
		$GLOBALS['wp_remote_post_handler'] = static function (): array {
			return [
				'response' => [ 'code' => 200 ],
				'headers' => [],
				'body' => '{"error":{"error_code":5,"error_msg":"User authorization failed"}}',
			];
		};

		try {
			( new VkApi( 'bad-token' ) )->getCommunity( '1' );
			$this->fail( 'Expected VK API exception.' );
		} catch ( VkApiException $exception ) {
			$this->assertSame( 5, $exception->getCode() );
			$this->assertSame( 5, $exception->getPayload()['error_code'] );
			$this->assertStringContainsString( 'authorization failed', $exception->getMessage() );
		}
	}

	public function testLongPollCheckClampsWaitAndTimeout(): void {
		$api = new VkApi( 'vk-test-token' );
		$result = $api->checkLongPoll( 'https://lp.vk.test/server', 'secret-key', '10', 500 );

		$this->assertSame( '2', $result['ts'] );
		$request = $GLOBALS['wp_remote_get_requests'][0];
		$this->assertStringContainsString( 'wait=90', $request['url'] );
		$this->assertSame( 95, $request['args']['timeout'] );
	}
}
