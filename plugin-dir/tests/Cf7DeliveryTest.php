<?php

declare( strict_types=1 );

use iTRON\cf7Vk\Bot;
use iTRON\cf7Vk\Channel;
use iTRON\cf7Vk\Chat;
use iTRON\cf7Vk\Client;
use iTRON\cf7Vk\Controllers\CF7;
use iTRON\cf7Vk\Vk\VkDeliveryResult;
use iTRON\cf7Vk\Vk\VkGateway;
use iTRON\wpConnections\Query;

require_once __DIR__ . '/Fakes/RecordingVkGateway.php';

final class Cf7DeliveryTest extends Cf7vk_TestCase {
	protected function setUp(): void {
		parent::setUp();
		$this->requiresPhp81Runtime();
	}

	public function testNoChannelConnectionCompletesWithoutSending(): void {
		$this->initClient();
		$completed = [];
		$this->captureCompletions( $completed );
		$abort = false;

		CF7::handleSubscribe( $this->form(), $abort, $this->submission() );

		$this->assertFalse( $abort );
		$this->assertSame( 1, count( $completed ) );
		$this->assertSame( 'no_channels', $completed[0]['status'] );
		$this->assertSame( 0, $completed[0]['totals']['channels'] );
	}

	public function testChannelWithoutBotCompletesAsNoRecipients(): void {
		$this->initClient();
		$channel = $this->channel();
		$this->connectFormToChannel( 10, $channel );
		$completed = [];
		$this->captureCompletions( $completed );
		$abort = false;

		CF7::handleSubscribe( $this->form(), $abort, $this->submission() );

		$this->assertFalse( $abort );
		$this->assertSame( 'no_recipients', $completed[0]['status'] );
		$this->assertSame( 'no_bot', $completed[0]['channels'][0]['status'] );
		$this->assertSame( 0, $completed[0]['totals']['attempted'] );
	}

	public function testChannelWithoutChatsCompletesAsNoRecipients(): void {
		$this->initClient();
		$bot = $this->bot();
		$channel = $this->channel( $bot );
		$this->connectFormToChannel( 10, $channel );
		$gateway = new Cf7vk_RecordingVkGateway();
		$this->installGateway( $gateway );
		$completed = [];
		$this->captureCompletions( $completed );
		$abort = false;

		CF7::handleSubscribe( $this->form(), $abort, $this->submission() );

		$this->assertFalse( $abort );
		$this->assertSame( 'no_recipients', $completed[0]['status'] );
		$this->assertSame( 'no_chats', $completed[0]['channels'][0]['status'] );
		$this->assertSame( 0, count( $gateway->calls ) );
	}

	public function testInactiveChatIsSkippedWithoutSending(): void {
		$this->initClient();
		$bot = $this->bot();
		$channel = $this->channel( $bot );
		$this->chat( $bot, $channel, '2000000101', Chat::STATUS_MUTED );
		$this->connectFormToChannel( 10, $channel );
		$gateway = new Cf7vk_RecordingVkGateway();
		$this->installGateway( $gateway );
		$completed = [];
		$this->captureCompletions( $completed );
		$abort = false;

		CF7::handleSubscribe( $this->form(), $abort, $this->submission() );

		$this->assertFalse( $abort );
		$this->assertSame( 'no_active_chats', $completed[0]['status'] );
		$this->assertSame( 1, $completed[0]['totals']['skipped'] );
		$this->assertSame( 0, count( $gateway->calls ) );
	}

	public function testStatusLookupFailureIsReportedAndDoesNotAbortCf7(): void {
		$this->initClient();
		$bot = $this->bot();
		$channel = $this->channel( $bot );
		$chat = $this->chat( null, $channel, '2000000102' );
		$this->connectFormToChannel( 10, $channel );
		$completed = [];
		$exceptions = [];
		$this->captureCompletions( $completed );
		$this->captureExceptions( $exceptions );
		$abort = false;

		CF7::handleSubscribe( $this->form(), $abort, $this->submission() );

		$this->assertFalse( $abort );
		$this->assertSame( 'failed', $completed[0]['status'] );
		$this->assertSame( 1, $completed[0]['totals']['failed'] );
		$this->assertSame( 'status_lookup_failed', $completed[0]['channels'][0]['recipients'][0]['status'] );
		$this->assertSame( $chat->getPost()->ID, $exceptions[0]['chatId'] );
	}

	public function testRecipientFailureContinuesFanOutAndKeepsLogsRedacted(): void {
		$this->initClient();
		$token = 'vk1.deliveryFailureTokenForSmokeOnly1234567890';
		$bot = $this->bot( $token );
		$channel = $this->channel( $bot );
		$this->chat( $bot, $channel, '2000000103', Chat::STATUS_ACTIVE );
		$this->chat( $bot, $channel, '2000000104', Chat::STATUS_ACTIVE );
		$this->connectFormToChannel( 10, $channel );
		$gateway = new Cf7vk_RecordingVkGateway();
		$gateway->queue(
			'messages.send',
			VkDeliveryResult::failure(
				'messages.send',
				200,
				5,
				'VK rejected token=' . $token . ' key=delivery-key alice@example.test +1 555 555 0100',
				null,
				VkDeliveryResult::ERROR_VK_API
			)
		);
		$gateway->queue( 'messages.send', VkDeliveryResult::success( 'messages.send', 42 ) );
		$this->installGateway( $gateway, $token );
		$completed = [];
		$exceptions = [];
		$this->captureCompletions( $completed );
		$this->captureExceptions( $exceptions );
		$abort = false;

		CF7::handleSubscribe(
			$this->form(),
			$abort,
			$this->submission(
				[
					'your-name' => 'Alice',
					'your-email' => 'alice@example.test',
					'your-phone' => '+1 555 555 0100',
				]
			)
		);

		$message_calls = array_values(
			array_filter(
				$gateway->calls,
				static fn( array $call ): bool => 'messages.send' === ( $call['method'] ?? '' )
			)
		);
		$log_payload = wp_json_encode( $GLOBALS['wp_log_rows'] );
		$summary_payload = wp_json_encode( $completed );

		$this->assertFalse( $abort );
		$this->assertSame( 2, count( $message_calls ) );
		$this->assertSame( 1, count( $exceptions ) );
		$this->assertSame( 'partial_failure', $completed[0]['status'] );
		$this->assertSame( 2, $completed[0]['totals']['attempted'] );
		$this->assertSame( 1, $completed[0]['totals']['succeeded'] );
		$this->assertSame( 1, $completed[0]['totals']['failed'] );
		$this->assertSame( 'failed', $completed[0]['channels'][0]['recipients'][0]['status'] );
		$this->assertSame( 'sent', $completed[0]['channels'][0]['recipients'][1]['status'] );
		$this->assertStringNotContainsString( $token, $log_payload );
		$this->assertStringNotContainsString( 'delivery-key', $log_payload );
		$this->assertStringNotContainsString( '2000000103', $log_payload );
		$this->assertStringNotContainsString( 'alice@example.test', $log_payload );
		$this->assertStringNotContainsString( '+1 555 555 0100', $log_payload );
		$this->assertStringNotContainsString( '2000000103', $summary_payload );
		$this->assertStringNotContainsString( 'alice@example.test', $summary_payload );
		$this->assertStringNotContainsString( '+1 555 555 0100', $summary_payload );
	}

	public function testFormRelationLookupFailureCompletesWithoutAbortingCf7(): void {
		$this->replaceConnectionsClient( new \iTRON\wpConnections\Client( Client::WPCONNECTIONS_CLIENT ) );
		$completed = [];
		$exceptions = [];
		$this->captureCompletions( $completed );
		$this->captureExceptions( $exceptions );
		$abort = false;

		CF7::handleSubscribe( $this->form(), $abort, $this->submission() );

		$this->assertFalse( $abort );
		$this->assertSame( 1, count( $completed ) );
		$this->assertSame( 'failed', $completed[0]['status'] );
		$this->assertSame( 'form_channel_lookup', $completed[0]['errors'][0]['stage'] );
		$this->assertSame( 1, count( $exceptions ) );
		$this->assertSame( null, $exceptions[0]['channelId'] );
	}

	private function initClient(): void {
		$this->replaceClientSingleton( new \iTRON\wpConnections\Client( Client::WPCONNECTIONS_CLIENT ) )->init();
	}

	private function replaceConnectionsClient( \iTRON\wpConnections\Client $client ): void {
		$this->replaceClientSingleton( $client );
	}

	private function replaceClientSingleton( \iTRON\wpConnections\Client $client ): Client {
		$reflection = new ReflectionClass( Client::class );
		$instance = $reflection->newInstanceWithoutConstructor();
		$instance_property = $reflection->getProperty( 'instance' );
		$instance_property->setAccessible( true );
		$instance_property->setValue( null, $instance );

		$property = $reflection->getProperty( 'connectionsClient' );
		$property->setAccessible( true );
		$property->setValue( null, $client );

		$logger = $reflection->getProperty( 'logger' );
		$logger->setAccessible( true );
		$logger->setValue( $instance, new \iTRON\cf7Vk\Logger() );

		return $instance;
	}

	private function bot( string $access_token = 'token-a' ): Bot {
		$bot = new Bot();
		$bot->setTitle( 'Delivery Bot' );
		$bot->setParam( 'accessToken', $access_token );
		$bot->setParam( 'groupId', '1001' );
		$bot->setParam( 'apiVersion', Bot::DEFAULT_API_VERSION );
		$bot->setParam( 'lastStatus', Bot::STATUS_ONLINE );
		$bot->publish();

		return $bot;
	}

	private function channel( ?Bot $bot = null ): Channel {
		$channel = new Channel();
		$channel->setTitle( 'Delivery Channel' );
		$channel->publish();

		if ( $bot ) {
			$channel->connectBot( $bot );
		}

		return $channel;
	}

	private function chat( ?Bot $bot, Channel $channel, string $peer_id, string $status = Chat::STATUS_ACTIVE ): Chat {
		$chat = new Chat();
		$chat->setTitle( 'Delivery Chat' );
		$chat->setParam( 'peerId', $peer_id );
		$chat->publish();

		if ( $bot ) {
			$bot->connectChat( $chat );

			if ( Chat::STATUS_ACTIVE === $status ) {
				$chat->setActivated( $bot );
			} elseif ( Chat::STATUS_MUTED === $status ) {
				$chat->setMuted( $bot );
			} else {
				$chat->setPending( $bot );
			}
		}

		$channel->connectChat( $chat );

		return $chat;
	}

	private function connectFormToChannel( int $form_id, Channel $channel ): void {
		Client::getInstance()
			->getForm2ChannelRelation()
			->createConnection( new Query\Connection( $form_id, $channel->getPost()->ID ) );
	}

	private function form(): WPCF7_ContactForm {
		return new WPCF7_ContactForm(
			10,
			'Lead form',
			[
				'mail' => [
					'body' => "Lead body\n[your-email]\n[your-phone]",
					'subject' => 'New lead',
				],
			]
		);
	}

	private function submission( array $posted = [] ): WPCF7_Submission {
		return new WPCF7_Submission(
			$posted ?: [
				'your-name' => 'Alice',
			]
		);
	}

	private function installGateway( Cf7vk_RecordingVkGateway $gateway, string $token = 'token-a' ): void {
		add_filter(
			'cf7vk_vk_gateway',
			static fn( VkGateway $default, string $access_token ): VkGateway => $token === $access_token ? $gateway : $default,
			10,
			2
		);
	}

	private function captureCompletions( array &$completed ): void {
		add_action(
			'cf7vk_deliveries_completed',
			static function ( array $summary ) use ( &$completed ): void {
				$completed[] = $summary;
			},
			10,
			1
		);
	}

	private function captureExceptions( array &$exceptions ): void {
		add_action(
			'cf7vk_delivery_exception',
			static function ( Throwable $exception, $channel, $chat, array $context ) use ( &$exceptions ): void {
				$exceptions[] = [
					'type' => get_class( $exception ),
					'channelId' => $channel instanceof Channel ? $channel->getPost()->ID : null,
					'chatId' => $chat instanceof Chat ? $chat->getPost()->ID : null,
					'stage' => $context['stage'] ?? '',
				];
			},
			10,
			4
		);
	}
}
