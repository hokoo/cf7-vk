<?php

declare( strict_types=1 );

use iTRON\cf7Vk\Bot;
use iTRON\cf7Vk\Client;
use iTRON\cf7Vk\Maintenance;
use iTRON\cf7Vk\Vk\VkDeliveryResult;
use iTRON\cf7Vk\Vk\VkGateway;

require_once __DIR__ . '/Fakes/RecordingVkGateway.php';

final class BotLongPollTest extends Cf7vk_TestCase {
	public function testFetchUpdatesSkipsVkWhenCleanupLockIsActive(): void {
		$bot = $this->bot();
		$gateway = new Cf7vk_RecordingVkGateway();
		$this->installGateway( [ 'token-a' => $gateway ] );
		add_option( Maintenance::CLEANUP_LOCK_OPTION, time(), '', false );

		$result = $bot->fetchUpdates();

		$this->assertTrue( $result['locked'] );
		$this->assertTrue( $result['cleanupLocked'] );
		$this->assertSame( 0, count( $gateway->calls ) );
	}

	public function testFetchUpdatesSkipsVkWhenFetchLockIsActive(): void {
		$bot = $this->bot();
		$gateway = new Cf7vk_RecordingVkGateway();
		$this->installGateway( [ 'token-a' => $gateway ] );
		add_option( Bot::FETCH_UPDATES_LOCK_PREFIX . $bot->getPost()->ID, time(), '', false );

		$result = $bot->fetchUpdates();

		$this->assertTrue( $result['locked'] );
		$this->assertFalse( $result['cleanupLocked'] );
		$this->assertSame( 0, count( $gateway->calls ) );
	}

	public function testFetchUpdatesClearsStaleFetchLockAndAdvancesCursorOnCleanBatch(): void {
		$bot = $this->bot();
		$gateway = new Cf7vk_RecordingVkGateway();
		$gateway->queue( 'long_poll', VkDeliveryResult::success( 'long_poll', [ 'ts' => '11', 'updates' => [] ] ) );
		$this->installGateway( [ 'token-a' => $gateway ] );
		add_option( Bot::FETCH_UPDATES_LOCK_PREFIX . $bot->getPost()->ID, time() - Bot::FETCH_UPDATES_LOCK_TTL - 1, '', false );

		$result = $bot->fetchUpdates();
		$reloaded = new Bot( $bot->getPost()->ID );

		$this->assertFalse( $result['locked'] );
		$this->assertTrue( $result['cursorAdvanced'] );
		$this->assertSame( '11', $result['ts'] );
		$this->assertSame( '11', $reloaded->getLongPollTs() );
		$this->assertSame( false, get_option( Bot::FETCH_UPDATES_LOCK_PREFIX . $bot->getPost()->ID, false ) );
		$this->assertSame( 1, count( $gateway->calls ) );
	}

	public function testLongPollFailedOneAdvancesOnlyTimestamp(): void {
		$bot = $this->bot();
		$gateway = new Cf7vk_RecordingVkGateway();
		$gateway->queue( 'long_poll', VkDeliveryResult::success( 'long_poll', [ 'failed' => 1, 'ts' => '22' ] ) );
		$this->installGateway( [ 'token-a' => $gateway ] );

		$result = $bot->fetchUpdates();
		$reloaded = new Bot( $bot->getPost()->ID );

		$this->assertSame( 1, $result['failed'] );
		$this->assertTrue( $result['cursorAdvanced'] );
		$this->assertSame( '22', $result['ts'] );
		$this->assertSame( '22', $reloaded->getLongPollTs() );
	}

	public function testLongPollFailedTwoRefreshesBootstrapWithoutLoggingStaleKey(): void {
		$bot = $this->bot();
		$gateway = new Cf7vk_RecordingVkGateway();
		$gateway->queue( 'long_poll', VkDeliveryResult::success( 'long_poll', [ 'failed' => 2 ] ) );
		$gateway->queue(
			'groups.getLongPollServer',
			VkDeliveryResult::success(
				'groups.getLongPollServer',
				[
					'server' => 'https://lp.new.test',
					'key'    => 'new-key',
					'ts'     => '33',
				]
			)
		);
		$this->installGateway( [ 'token-a' => $gateway ] );

		$result = $bot->fetchUpdates();
		$reloaded = new Bot( $bot->getPost()->ID );

		$this->assertSame( 2, $result['failed'] );
		$this->assertTrue( $result['cursorAdvanced'] );
		$this->assertSame( '33', $result['ts'] );
		$this->assertSame( 'https://lp.new.test', $reloaded->getLongPollServer() );
		$this->assertSame( 'new-key', $reloaded->getLongPollKey() );
		$this->assertStringNotContainsString( 'old-key', wp_json_encode( $GLOBALS['wp_log_rows'] ) );
	}

	public function testLongPollTransportFailureReturnsStructuredErrorAndRedactedLog(): void {
		$bot = $this->bot();
		$gateway = new Cf7vk_RecordingVkGateway();
		$gateway->queue(
			'long_poll',
			VkDeliveryResult::failure(
				'long_poll',
				0,
				0,
				'Could not connect to https://lp.old.test?key=old-key',
				null,
				VkDeliveryResult::ERROR_TRANSPORT
			)
		);
		$this->installGateway( [ 'token-a' => $gateway ] );

		$result = $bot->fetchUpdates();
		$logs = wp_json_encode( $GLOBALS['wp_log_rows'] );

		$this->assertFalse( $result['locked'] );
		$this->assertTrue( $result['transientError'] );
		$this->assertSame( 'transport', $result['error']['type'] );
		$this->assertStringNotContainsString( 'old-key', $result['error']['message'] );
		$this->assertStringNotContainsString( 'old-key', $logs );
		$this->assertSame( Bot::STATUS_OFFLINE, ( new Bot( $bot->getPost()->ID ) )->getLastStatus() );
	}

	public function testProcessingErrorDoesNotAdvanceCursor(): void {
		$bot = $this->bot();
		$gateway = new Cf7vk_RecordingVkGateway();
		$gateway->queue(
			'long_poll',
			VkDeliveryResult::success(
				'long_poll',
				[
					'ts'      => '44',
					'updates' => [
						$this->update( 'event-1', '', 0, 'start' ),
					],
				]
			)
		);
		$this->installGateway( [ 'token-a' => $gateway ] );

		$result = $bot->fetchUpdates();
		$reloaded = new Bot( $bot->getPost()->ID );

		$this->assertSame( 1, $result['errorCount'] );
		$this->assertFalse( $result['cursorAdvanced'] );
		$this->assertSame( '10', $result['ts'] );
		$this->assertSame( '44', $result['nextTs'] );
		$this->assertSame( '10', $reloaded->getLongPollTs() );
		$this->assertStringContainsString( 'VK Long Poll update processing failed.', wp_json_encode( $GLOBALS['wp_log_rows'] ) );
	}

	public function testOptionalProfileLookupFailureIsSafelyIgnorableAndCursorAdvances(): void {
		$this->requiresPhp81Runtime();
		Client::getInstance()->init();
		$bot = $this->bot();
		$gateway = new Cf7vk_RecordingVkGateway();
		$gateway->queue(
			'long_poll',
			VkDeliveryResult::success(
				'long_poll',
				[
					'ts'      => '55',
					'updates' => [
						$this->update( 'event-1', '2000000001', 101, 'start', '501' ),
					],
				]
			)
		);
		$gateway->queue(
			'users.get',
			VkDeliveryResult::failure( 'users.get', 200, 5, 'User lookup failed', null, VkDeliveryResult::ERROR_VK_API )
		);
		$gateway->queue( 'messages.getByConversationMessageId', VkDeliveryResult::success( 'messages.getByConversationMessageId', [ 'items' => [] ] ) );
		$this->installGateway( [ 'token-a' => $gateway ] );

		$result = $bot->fetchUpdates();

		$this->assertSame( 0, $result['errorCount'] );
		$this->assertTrue( $result['cursorAdvanced'] );
		$this->assertSame( '55', ( new Bot( $bot->getPost()->ID ) )->getLongPollTs() );
		$this->assertSame( 1, count( get_posts( [ 'post_type' => Client::CPT_CHAT, 'post_status' => 'any', 'posts_per_page' => -1 ] ) ) );
		$this->assertStringContainsString( 'VK Long Poll optional lookup failed.', wp_json_encode( $GLOBALS['wp_log_rows'] ) );
	}

	public function testDuplicatePeerIdInOneBatchIsProcessedOnce(): void {
		$this->requiresPhp81Runtime();
		Client::getInstance()->init();
		$bot = $this->bot();
		$gateway = new Cf7vk_RecordingVkGateway();
		$gateway->queue(
			'long_poll',
			VkDeliveryResult::success(
				'long_poll',
				[
					'ts'      => '66',
					'updates' => [
						$this->update( 'event-1', '2000000001', 101, 'start', '501' ),
						$this->update( 'event-2', '2000000001', 101, 'start', '502' ),
					],
				]
			)
		);
		$gateway->queue( 'users.get', VkDeliveryResult::success( 'users.get', [] ) );
		$gateway->queue( 'messages.getByConversationMessageId', VkDeliveryResult::success( 'messages.getByConversationMessageId', [ 'items' => [] ] ) );
		$this->installGateway( [ 'token-a' => $gateway ] );

		$result = $bot->fetchUpdates();

		$this->assertSame( 0, $result['errorCount'] );
		$this->assertSame( 1, $result['duplicatePeerIds'] );
		$this->assertTrue( $result['cursorAdvanced'] );
		$this->assertSame( 1, count( get_posts( [ 'post_type' => Client::CPT_CHAT, 'post_status' => 'any', 'posts_per_page' => -1 ] ) ) );
	}

	private function bot(): Bot {
		$bot = new Bot();
		$bot->setTitle( 'Long Poll Bot' );
		$bot->setParam( 'accessToken', 'token-a' );
		$bot->setParam( 'groupId', '1001' );
		$bot->setParam( 'apiVersion', Bot::DEFAULT_API_VERSION );
		$bot->setParam( 'authCommand', 'start' );
		$bot->setParam( 'lastStatus', Bot::STATUS_ONLINE );
		$bot->setParam( 'longPollServer', 'https://lp.old.test' );
		$bot->setParam( 'longPollKey', 'old-key' );
		$bot->setParam( 'longPollTs', '10' );
		$bot->publish();

		return $bot;
	}

	private function installGateway( array $gateways ): void {
		add_filter(
			'cf7vk_vk_gateway',
			static fn( VkGateway $default, string $token ): VkGateway => $gateways[ $token ] ?? $default,
			10,
			2
		);
	}

	private function update( string $eventId, string $peerId, int $fromId, string $text, string $conversationMessageId = '1' ): array {
		return [
			'type'     => 'message_new',
			'event_id' => $eventId,
			'object'   => [
				'message' => [
					'id'                      => 100,
					'peer_id'                 => $peerId,
					'from_id'                 => $fromId,
					'conversation_message_id' => $conversationMessageId,
					'date'                    => 1700000000,
					'text'                    => $text,
				],
			],
		];
	}
}
