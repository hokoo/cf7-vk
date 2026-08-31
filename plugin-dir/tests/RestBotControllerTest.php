<?php

declare( strict_types=1 );

use iTRON\cf7Vk\Bot;
use iTRON\cf7Vk\Client;
use iTRON\cf7Vk\Controllers\RestApi\BotController;
use iTRON\cf7Vk\Exceptions\VkApiException;
use iTRON\cf7Vk\Vk\VkDeliveryResult;
use iTRON\cf7Vk\Vk\VkGateway;

require_once __DIR__ . '/Fakes/RecordingVkGateway.php';

final class RestBotControllerTest extends Cf7vk_TestCase {
	public function testRegistersCredentialsRoute(): void {
		$controller = new BotController( Client::CPT_BOT );
		$controller->register_routes();

		$route = 'wp/v2/cf7vk_bot/(?P<id>[\d]+)/credentials';
		$this->assertArrayHasKey( $route, $GLOBALS['wp_rest_routes'] );
		$this->assertSame( WP_REST_Server::CREATABLE, $GLOBALS['wp_rest_routes'][ $route ]['args'][0]['methods'] );
		$this->assertSame( 'update_credentials', $GLOBALS['wp_rest_routes'][ $route ]['args'][0]['callback'][1] );
	}

	public function testFailedCredentialValidationPreservesPersistedStateAndRelations(): void {
		$bot = $this->bot( 'old-token', '1001', '5.199', '1001', 'Old Community' );
		$this->seedBotOwnedRelations( $bot->getPost()->ID );
		$beforeRows = wp_json_encode( $GLOBALS['wp_connection_rows'] );
		$beforeMetaRows = wp_json_encode( $GLOBALS['wp_connection_meta_rows'] );
		$gateway = new Cf7vk_RecordingVkGateway();
		$gateway->queue(
			'groups.getById',
			VkDeliveryResult::failure(
				'groups.getById',
				200,
				5,
				'User authorization failed',
				null,
				VkDeliveryResult::ERROR_VK_API,
				[
					'error_code' => 5,
					'error_msg'  => 'User authorization failed',
				]
			)
		);
		$this->installGateway( [ 'new-token' => $gateway ] );

		try {
			$bot->updateCredentials( '2002', 'new-token', '5.200' );
			$this->fail( 'Expected credential validation to fail.' );
		} catch ( VkApiException $exception ) {
			$reloaded = new Bot( $bot->getPost()->ID );
			$this->assertSame( 'old-token', $reloaded->getAccessToken() );
			$this->assertSame( '1001', $reloaded->getGroupId() );
			$this->assertSame( '5.199', $reloaded->getApiVersion() );
			$this->assertSame( '1001', $reloaded->getCommunityId() );
			$this->assertSame( 'https://lp.old.test', $reloaded->getLongPollServer() );
			$this->assertSame( 'old-long-poll-key', $reloaded->getLongPollKey() );
			$this->assertSame( '10', $reloaded->getLongPollTs() );
			$this->assertSame( Bot::STATUS_ONLINE, $reloaded->getLastStatus() );
			$this->assertSame( 'Old Community', $reloaded->getTitle() );
			$this->assertSame( $beforeRows, wp_json_encode( $GLOBALS['wp_connection_rows'] ) );
			$this->assertSame( $beforeMetaRows, wp_json_encode( $GLOBALS['wp_connection_meta_rows'] ) );
		}
	}

	public function testSameCommunityCredentialUpdatePreservesRelations(): void {
		$bot = $this->bot( 'old-token', '1001', '5.199', '1001', 'Old Community' );
		$this->seedBotOwnedRelations( $bot->getPost()->ID );
		$gateway = $this->successfulGateway( '1001', 'Same Community', 'same_community', 'https://lp.same.test', 'same-key', '22' );
		$this->installGateway( [ 'new-token' => $gateway ] );

		$result = $bot->updateCredentials( '-1001', 'new-token', '5.200', 'join' );
		$reloaded = new Bot( $bot->getPost()->ID );

		$this->assertFalse( $result['identityChanged'] );
		$this->assertSame( 0, $result['relationsReset'] );
		$this->assertSame( 'new-token', $reloaded->getAccessToken() );
		$this->assertSame( '1001', $reloaded->getGroupId() );
		$this->assertSame( '5.200', $reloaded->getApiVersion() );
		$this->assertSame( '1001', $reloaded->getCommunityId() );
		$this->assertSame( 'Same Community', $reloaded->getTitle() );
		$this->assertSame( 'join', $reloaded->getAuthCommand() );
		$this->assertSame( 'https://lp.same.test', $reloaded->getLongPollServer() );
		$this->assertSame( 'same-key', $reloaded->getLongPollKey() );
		$this->assertSame( '22', $reloaded->getLongPollTs() );
		$this->assertSame( 4, count( $GLOBALS['wp_connection_rows'] ) );
	}

	public function testDifferentCommunityCredentialUpdateResetsOnlyBotOwnedRelations(): void {
		$bot = $this->bot( 'old-token', '1001', '5.199', '1001', 'Old Community' );
		$this->seedBotOwnedRelations( $bot->getPost()->ID );
		$gateway = $this->successfulGateway( '2002', 'New Community', 'new_community', 'https://lp.new.test', 'new-key', '55' );
		$this->installGateway( [ 'new-token' => $gateway ] );

		$result = $bot->updateCredentials( '2002', 'new-token', '5.200' );
		$remainingIds = array_map( static fn( object $row ): int => (int) $row->ID, $GLOBALS['wp_connection_rows'] );
		$remainingMetaConnectionIds = array_map( static fn( object $row ): int => (int) $row->connection_id, $GLOBALS['wp_connection_meta_rows'] );

		$this->assertTrue( $result['identityChanged'] );
		$this->assertSame( 2, $result['relationsReset'] );
		$this->assertSame( [ 3, 4 ], $remainingIds );
		$this->assertSame( [ 3, 4 ], $remainingMetaConnectionIds );
		$this->assertSame( '2002', ( new Bot( $bot->getPost()->ID ) )->getCommunityId() );
	}

	public function testCredentialsRouteRejectsPhpConstantAccessTokenOverwrite(): void {
		$bot = $this->botAtId( 9001, 'stored-token', '1001', '5.199', '1001', 'Const Community' );
		if ( ! defined( 'CF7VK_ACCESS_TOKEN__9001' ) ) {
			define( 'CF7VK_ACCESS_TOKEN__9001', 'const-token' );
		}

		$response = ( new BotController( Client::CPT_BOT ) )->update_credentials(
			$this->request(
				$bot->getPost()->ID,
				[
					'groupId'     => '1001',
					'accessToken' => 'new-token',
					'apiVersion'  => '5.200',
				]
			)
		);

		$this->assertSame( WP_Error::class, get_class( $response ) );
		$this->assertSame( 'rest_vk_bot_access_token_const', $response->get_error_code() );
		$this->assertSame( 409, $response->get_error_data()['status'] );
		$this->assertSame( 0, count( $GLOBALS['wp_remote_post_requests'] ) );
		$this->assertSame( 'stored-token', ( new Bot( $bot->getPost()->ID ) )->getParam( 'accessToken' ) );
	}

	public function testCredentialsRouteReturnsUpdatedBotPayloadAfterValidation(): void {
		$bot = $this->bot( 'old-token', '1001', '5.199', '1001', 'Old Community' );
		$gateway = $this->successfulGateway( '1001', 'REST Community', 'rest_community', 'https://lp.rest.test', 'rest-key', '33' );
		$this->installGateway( [ 'new-token' => $gateway ] );

		$response = ( new BotController( Client::CPT_BOT ) )->update_credentials(
			$this->request(
				$bot->getPost()->ID,
				[
					'groupId'     => '1001',
					'accessToken' => 'new-token',
					'apiVersion'  => '5.200',
					'authCommand' => 'hello',
				]
			)
		);
		$data = $response->get_data();

		$this->assertSame( WP_REST_Response::class, get_class( $response ) );
		$this->assertSame( 'REST Community', $data['bot']['title']['rendered'] );
		$this->assertSame( 'new-token', $data['bot']['accessToken'] );
		$this->assertSame( '1001', $data['communityId'] );
		$this->assertTrue( $data['longPollReady'] );
		$this->assertSame( '33', $data['bot']['longPollTs'] );
		$this->assertSame( 'hello', $data['bot']['authCommand'] );
	}

	private function request( int $id, array $params = [] ): WP_REST_Request {
		$request = new WP_REST_Request( 'POST', '/wp/v2/cf7vk_bot/' . $id . '/credentials' );
		$request->set_param( 'id', $id );

		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}

		return $request;
	}

	private function bot(
		string $token,
		string $groupId,
		string $apiVersion,
		string $communityId,
		string $title
	): Bot {
		$bot = new Bot();
		$this->fillBot( $bot, $token, $groupId, $apiVersion, $communityId, $title );

		return $bot;
	}

	private function botAtId(
		int $id,
		string $token,
		string $groupId,
		string $apiVersion,
		string $communityId,
		string $title
	): Bot {
		$post = new WP_Post();
		$post->ID = $id;
		$post->post_type = Client::CPT_BOT;
		$post->post_status = 'publish';
		$post->post_title = $title;
		$GLOBALS['wp_posts'][ $id ] = $post;
		$GLOBALS['wp_posts_by_type'][ Client::CPT_BOT ][] = $id;
		$bot = new Bot( $id );
		$this->fillBot( $bot, $token, $groupId, $apiVersion, $communityId, $title );

		return $bot;
	}

	private function fillBot(
		Bot $bot,
		string $token,
		string $groupId,
		string $apiVersion,
		string $communityId,
		string $title
	): void {
		$bot->setTitle( $title );
		$bot->setParam( 'accessToken', $token );
		$bot->setParam( 'groupId', $groupId );
		$bot->setParam( 'apiVersion', $apiVersion );
		$bot->setParam( 'authCommand', 'start' );
		$bot->setParam( 'communityId', $communityId );
		$bot->setParam( 'communityName', $title );
		$bot->setParam( 'communityScreenName', 'old_community' );
		$bot->setParam( 'longPollServer', 'https://lp.old.test' );
		$bot->setParam( 'longPollKey', 'old-long-poll-key' );
		$bot->setParam( 'longPollTs', '10' );
		$bot->setParam( 'lastSyncAt', '2026-08-31T00:00:00+00:00' );
		$bot->setParam( 'lastStatus', Bot::STATUS_ONLINE );
		$bot->publish();
	}

	private function installGateway( array $gateways ): void {
		add_filter(
			'cf7vk_vk_gateway',
			static fn( VkGateway $default, string $token ): VkGateway => $gateways[ $token ] ?? $default,
			10,
			2
		);
	}

	private function successfulGateway(
		string $communityId,
		string $communityName,
		string $screenName,
		string $server,
		string $key,
		string $ts
	): Cf7vk_RecordingVkGateway {
		$gateway = new Cf7vk_RecordingVkGateway();
		$gateway->queue(
			'groups.getById',
			VkDeliveryResult::success(
				'groups.getById',
				[
					'id'          => $communityId,
					'name'        => $communityName,
					'screen_name' => $screenName,
				]
			)
		);
		$gateway->queue(
			'groups.getLongPollServer',
			VkDeliveryResult::success(
				'groups.getLongPollServer',
				[
					'server' => $server,
					'key'    => $key,
					'ts'     => $ts,
				]
			)
		);

		return $gateway;
	}

	private function seedBotOwnedRelations( int $botId ): void {
		$GLOBALS['wp_connection_rows'] = [
			(object) [ 'ID' => 1, 'relation' => Client::BOT2CHAT, 'from' => $botId, 'to' => 20, 'order' => 0, 'title' => '' ],
			(object) [ 'ID' => 2, 'relation' => Client::BOT2CHANNEL, 'from' => $botId, 'to' => 30, 'order' => 0, 'title' => '' ],
			(object) [ 'ID' => 3, 'relation' => Client::CHAT2CHANNEL, 'from' => 20, 'to' => 30, 'order' => 0, 'title' => '' ],
			(object) [ 'ID' => 4, 'relation' => Client::BOT2CHAT, 'from' => 999, 'to' => 21, 'order' => 0, 'title' => '' ],
		];
		$GLOBALS['wp_connection_meta_rows'] = [
			(object) [ 'meta_id' => 1, 'connection_id' => 1, 'meta_key' => 'status', 'meta_value' => 'active' ],
			(object) [ 'meta_id' => 2, 'connection_id' => 2, 'meta_key' => 'status', 'meta_value' => 'active' ],
			(object) [ 'meta_id' => 3, 'connection_id' => 3, 'meta_key' => 'status', 'meta_value' => 'active' ],
			(object) [ 'meta_id' => 4, 'connection_id' => 4, 'meta_key' => 'status', 'meta_value' => 'active' ],
		];
		$GLOBALS['wp_next_connection_id'] = 5;
		$GLOBALS['wp_next_connection_meta_id'] = 5;
	}
}
