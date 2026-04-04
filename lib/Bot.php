<?php

namespace iTRON\cf7Vk;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use iTRON\cf7Vk\Exceptions\TransportNotConfigured;
use iTRON\cf7Vk\Exceptions\VkApiException;
use iTRON\wpConnections\Connection;
use iTRON\wpConnections\Exceptions\ConnectionNotFound;
use iTRON\wpConnections\Exceptions\ConnectionWrongData;
use iTRON\wpConnections\Exceptions\Exception as ConnectionException;
use iTRON\wpConnections\Exceptions\MissingParameters;
use iTRON\wpConnections\Exceptions\RelationNotFound;
use iTRON\wpConnections\Query;
use iTRON\wpPostAble\Exceptions\wppaCreatePostException;
use iTRON\wpPostAble\Exceptions\wppaLoadPostException;
use iTRON\wpPostAble\Exceptions\wppaSavePostException;
use iTRON\wpPostAble\wpPostAble;
use iTRON\wpPostAble\wpPostAbleTrait;
use Throwable;

class Bot extends Entity implements wpPostAble {
	use wpPostAbleTrait;

	public const STATUS_ONLINE = 'online';
	public const STATUS_OFFLINE = 'offline';
	public const STATUS_UNKNOWN = 'unknown';
	public const ACCESS_TOKEN_CONST_MASK = 'CF7VK_ACCESS_TOKEN__%d';
	public const GROUP_ID_CONST_MASK = 'CF7VK_GROUP_ID__%d';
	public const DEFAULT_API_VERSION = '5.199';
	public const DEFAULT_AUTH_COMMAND = 'start';
	public const EMPTY_SECRET_MASK = '[%s]';
	public const LONG_POLL_WAIT = 25;
	private ?VkApi $api = null;

	/**
	 * @throws wppaLoadPostException
	 * @throws wppaCreatePostException
	 */
	public function __construct( int $bot_id = 0 ) {
		parent::__construct();

		$this->wpPostAble( Client::CPT_BOT, $bot_id );
	}

	public static function getEmptySecret(): string {
		return sprintf(
			self::EMPTY_SECRET_MASK,
			_x( 'empty', 'Empty access token field', 'cf7-vk' )
		);
	}

	public static function isMaskedSecretValue( ?string $value ): bool {
		if ( ! is_string( $value ) ) {
			return false;
		}

		$value = trim( $value );

		return '' !== $value && 1 === preg_match( '/^\[[^\]]*]$/', $value );
	}

	public function getAccessTokenConstName(): string {
		return sprintf( self::ACCESS_TOKEN_CONST_MASK, $this->getPost()->ID );
	}

	public function isAccessTokenDefined(): bool {
		return defined( $this->getAccessTokenConstName() );
	}

	public function getAccessToken(): ?string {
		if ( $this->isAccessTokenDefined() ) {
			return (string) constant( $this->getAccessTokenConstName() );
		}

		return $this->getParam( 'accessToken' ) ?: null;
	}

	/**
	 * @throws wppaSavePostException
	 */
	public function setAccessToken( string $token ): self {
		$this->setParam( 'accessToken', trim( $token ) );
		$this->savePost();
		$this->api = null;

		return $this;
	}

	public function isAccessTokenEmpty(): bool {
		$token = $this->getAccessToken();

		return empty( $token ) || self::isMaskedSecretValue( $token );
	}

	public function getGroupIdConstName(): string {
		return sprintf( self::GROUP_ID_CONST_MASK, $this->getPost()->ID );
	}

	public function isGroupIdDefined(): bool {
		return defined( $this->getGroupIdConstName() );
	}

	public function getGroupId(): string {
		if ( $this->isGroupIdDefined() ) {
			return (string) constant( $this->getGroupIdConstName() );
		}

		return (string) $this->getParam( 'groupId' );
	}

	/**
	 * @throws wppaSavePostException
	 */
	public function setGroupId( string $group_id ): self {
		$this->setParam( 'groupId', trim( $group_id ) );
		$this->savePost();

		return $this;
	}

	public function getApiVersion(): string {
		return (string) ( $this->getParam( 'apiVersion' ) ?: self::DEFAULT_API_VERSION );
	}

	/**
	 * @throws wppaSavePostException
	 */
	public function setApiVersion( string $api_version ): self {
		$this->setParam( 'apiVersion', trim( $api_version ) ?: self::DEFAULT_API_VERSION );
		$this->savePost();
		$this->api = null;

		return $this;
	}

	public function getAuthCommand(): string {
		return (string) ( $this->getParam( 'authCommand' ) ?: self::DEFAULT_AUTH_COMMAND );
	}

	/**
	 * @throws wppaSavePostException
	 */
	public function setAuthCommand( string $auth_command ): self {
		$this->setParam( 'authCommand', trim( $auth_command ) ?: self::DEFAULT_AUTH_COMMAND );
		$this->savePost();

		return $this;
	}

	public function getLastStatus(): string {
		return (string) ( $this->getParam( 'lastStatus' ) ?: self::STATUS_UNKNOWN );
	}

	/**
	 * @throws wppaSavePostException
	 */
	public function setLastStatus( string $status ): self {
		$this->setParam( 'lastStatus', trim( $status ) ?: self::STATUS_UNKNOWN );
		$this->savePost();

		return $this;
	}

	public function getLongPollServer(): string {
		return (string) $this->getParam( 'longPollServer' );
	}

	/**
	 * @throws wppaSavePostException
	 */
	public function setLongPollServer( string $server ): self {
		$this->setParam( 'longPollServer', trim( $server ) );
		$this->savePost();

		return $this;
	}

	public function getLongPollKey(): string {
		return (string) $this->getParam( 'longPollKey' );
	}

	/**
	 * @throws wppaSavePostException
	 */
	public function setLongPollKey( string $key ): self {
		$this->setParam( 'longPollKey', trim( $key ) );
		$this->savePost();

		return $this;
	}

	public function getLongPollTs(): string {
		return (string) $this->getParam( 'longPollTs' );
	}

	/**
	 * @throws wppaSavePostException
	 */
	public function setLongPollTs( string $ts ): self {
		$this->setParam( 'longPollTs', trim( $ts ) );
		$this->savePost();

		return $this;
	}

	public function getLastSyncAt(): string {
		return (string) $this->getParam( 'lastSyncAt' );
	}

	/**
	 * @throws wppaSavePostException
	 */
	public function setLastSyncAt( string $timestamp ): self {
		$this->setParam( 'lastSyncAt', trim( $timestamp ) );
		$this->savePost();

		return $this;
	}

	/**
	 * @throws MissingParameters
	 * @throws ConnectionWrongData
	 * @throws RelationNotFound
	 */
	public function connectChannel( Channel $channel ): self {
		$channel->connectBot( $this );

		return $this;
	}

	/**
	 * @throws RelationNotFound
	 */
	public function disconnectChannel( Channel $channel = null ): self {
		$channel_id = $channel?->getPost()->ID;

		$this->client
			->getBot2ChannelRelation()
			->detachConnections( new Query\Connection( $this->getPost()->ID, $channel_id ) );

		return $this;
	}

	/**
	 * @throws RelationNotFound
	 */
	public function getChannels(): Collections\ChannelCollection {
		$connections = $this->client
			->getBot2ChannelRelation()
			->findConnections( new Query\Connection( $this->getPost()->ID ) );

		return ( new Collections\ChannelCollection() )->createByConnections( $connections, 'to' );
	}

	/**
	 * @throws RelationNotFound
	 */
	public function getChats(): Collections\ChatCollection {
		$connections = $this->client
			->getBot2ChatRelation()
			->findConnections( new Query\Connection( $this->getPost()->ID ) );

		return ( new Collections\ChatCollection() )->createByConnections( $connections, 'to' );
	}

	public function connectChat( Chat $chat ): ?Connection {
		try {
			return $this->client
				->getBot2ChatRelation()
				->createConnection( new Query\Connection( $this->getPost()->ID, $chat->getPost()->ID ) );
		} catch ( ConnectionException $e ) {
			$this->logger->write( $e->getMessage(), 'Unable to connect bot and chat.', Logger::LEVEL_CRITICAL );
		}

		return null;
	}

	/**
	 * @throws RelationNotFound
	 */
	public function disconnectChat( Chat $chat ): self {
		$this->client
			->getBot2ChatRelation()
			->detachConnections( new Query\Connection( $this->getPost()->ID, $chat->getPost()->ID ) );

		foreach ( $this->getChannels() as $channel ) {
			/** @var Channel $channel */
			if ( ! $channel->hasChat( $chat ) ) {
				continue;
			}

			$channel->disconnectChat( $chat );
		}

		return $this;
	}

	/**
	 * @throws RelationNotFound
	 */
	public function hasChat( Chat $chat ): bool {
		return $this->getChats()->contains( $chat );
	}

	/**
	 * @throws ConnectionNotFound
	 * @throws ConnectionWrongData
	 * @throws MissingParameters
	 * @throws RelationNotFound
	 */
	public function activateChat( Chat $chat ): array {
		if ( ! $this->hasChat( $chat ) ) {
			throw new ConnectionNotFound();
		}

		$connected_channel_ids = [];

		foreach ( $this->getChannels() as $channel ) {
			/** @var Channel $channel */
			try {
				if ( $channel->hasChat( $chat ) ) {
					continue;
				}

				$channel->connectChat( $chat );
				$connected_channel_ids[] = $channel->getPost()->ID;
			} catch ( Throwable $e ) {
				$this->logger->write(
					[
						'botTitle' => $this->getTitle(),
						'wpPostID' => $this->getPost()->ID,
						'chatId' => $chat->getPost()->ID,
						'chatPeerId' => $chat->getPeerId(),
						'channelId' => $channel->getPost()->ID ?? null,
						'channelTitle' => $channel->getTitle() ?? '',
						'error' => $e->getMessage(),
					],
					'VK bot chat activation failed to connect the chat to a channel.',
					Logger::LEVEL_WARNING
				);
			}
		}

		$chat->setActivated( $this );

		return [
			'chatId' => $chat->getPost()->ID,
			'status' => $chat->getConnectionStatus( $this ),
			'connectedChannelIds' => $connected_channel_ids,
		];
	}

	/**
	 * @throws TransportNotConfigured
	 * @throws VkApiException
	 */
	public function verifyConnection(): array {
		try {
			$group_id = $this->getNormalizedGroupId();
			$community = $this->getApi()->getCommunity( $group_id );
			$community_name = (string) ( $community['name'] ?? $community['screen_name'] ?? $this->getTitle() );

			$details = [
				'groupId' => $group_id,
				'communityName' => $community_name,
				'longPollReady' => false,
				'longPollError' => '',
			];

			try {
				$long_poll = $this->getApi()->getLongPollServer( $group_id );
				$timestamp = gmdate( 'c' );

				$this->persistStateSafely(
					[
						'lastStatus' => self::STATUS_ONLINE,
						'longPollServer' => (string) ( $long_poll['server'] ?? '' ),
						'longPollKey' => (string) ( $long_poll['key'] ?? '' ),
						'longPollTs' => (string) ( $long_poll['ts'] ?? '' ),
						'lastSyncAt' => $timestamp,
					]
				);

				$details['longPollReady'] = true;
				$details['longPollServer'] = (string) ( $long_poll['server'] ?? '' );
				$details['longPollTs'] = (string) ( $long_poll['ts'] ?? '' );
				$details['lastSyncAt'] = $timestamp;
			} catch ( VkApiException $e ) {
				$this->persistStateSafely(
					[
						'lastStatus' => self::STATUS_ONLINE,
						'longPollServer' => '',
						'longPollKey' => '',
						'longPollTs' => '',
						'lastSyncAt' => '',
					]
				);

				$this->logger->write(
					[
						'botTitle' => $this->getTitle(),
						'wpPostID' => $this->getPost()->ID,
						'groupId' => $group_id,
						'error' => $e->getMessage(),
					],
					'VK Long Poll is unavailable.',
					Logger::LEVEL_WARNING
				);

				$details['longPollError'] = $e->getMessage();
			}

			$this->persistCommunityTitle( $community_name );

			return $details;
		} catch ( TransportNotConfigured | VkApiException $e ) {
			$this->markTransportFailure(
				$e,
				'VK community verification failed.',
				Logger::LEVEL_WARNING,
				[
					'groupId' => $this->getGroupId(),
				]
			);

			throw $e;
		}
	}

	private function persistCommunityTitle( string $community_name ): void {
		$community_name = trim( $community_name );

		if ( '' === $community_name || $community_name === $this->getTitle() ) {
			return;
		}

		$result = wp_update_post(
			[
				'ID' => $this->getPost()->ID,
				'post_title' => $community_name,
			],
			true
		);

		if ( is_wp_error( $result ) ) {
			$this->logger->write(
				[
					'botTitle' => $this->getTitle(),
					'wpPostID' => $this->getPost()->ID,
					'error' => $result->get_error_message(),
					'communityName' => $community_name,
				],
				'VK bot title could not be updated.',
				Logger::LEVEL_WARNING
			);

			return;
		}

		$this->setTitle( $community_name );
	}

	public function ping(): bool {
		try {
			$this->verifyConnection();

			return true;
		} catch ( TransportNotConfigured | VkApiException $e ) {
			return false;
		}
	}

	/**
	 * @throws TransportNotConfigured
	 * @throws VkApiException
	 */
	public function fetchUpdates(): array {
		$empty_result = [
			'updates' => [],
			'hasNewChats' => false,
			'hasNewConnections' => false,
			'failed' => 0,
			'locked' => true,
			'ts' => $this->getLongPollTs(),
			'updatesCount' => 0,
		];

		if ( ! $this->acquireFetchUpdatesLock() ) {
			return $empty_result;
		}

		try {
			$this->ensureLongPollBootstrap();

			try {
				$response = $this->getApi()->checkLongPoll(
					$this->getLongPollServer(),
					$this->getLongPollKey(),
					$this->getLongPollTs(),
					self::LONG_POLL_WAIT
				);
			} catch ( VkApiException $e ) {
				$this->markTransportFailure(
					$e,
					'VK Long Poll request failed.',
					Logger::LEVEL_WARNING,
					[
						'groupId' => $this->getGroupId(),
					]
				);

				throw $e;
			}

			$failed = (int) ( $response['failed'] ?? 0 );

			if ( 1 === $failed ) {
				$new_ts = (string) ( $response['ts'] ?? '' );

				if ( '' !== $new_ts ) {
					$this->persistStateSafely(
						[
							'longPollTs' => $new_ts,
							'lastSyncAt' => gmdate( 'c' ),
							'lastStatus' => self::STATUS_ONLINE,
						]
					);
				}

				return [
					'updates' => [],
					'hasNewChats' => false,
					'hasNewConnections' => false,
					'failed' => 1,
					'locked' => false,
					'ts' => $new_ts,
				];
			}

			if ( 2 === $failed || 3 === $failed ) {
				$this->refreshLongPollBootstrap();

				return [
					'updates' => [],
					'hasNewChats' => false,
					'hasNewConnections' => false,
					'failed' => $failed,
					'locked' => false,
					'ts' => $this->getLongPollTs(),
				];
			}

			$updates = is_array( $response['updates'] ?? null ) ? $response['updates'] : [];
			$new_ts = (string) ( $response['ts'] ?? $this->getLongPollTs() );
			$processed = $this->processLongPollUpdates( $updates );

			$this->persistStateSafely(
				[
					'longPollTs' => $new_ts,
					'lastSyncAt' => gmdate( 'c' ),
					'lastStatus' => self::STATUS_ONLINE,
				]
			);

			return array_merge(
				$processed,
				[
					'failed' => 0,
					'locked' => false,
					'ts' => $new_ts,
					'updatesCount' => count( $updates ),
				]
			);
		} finally {
			$this->releaseFetchUpdatesLock();
		}
	}

	/**
	 * @throws TransportNotConfigured
	 * @throws VkApiException
	 */
	public function sendMessage( Chat $chat, string $message, bool $throw_on_error = true, array $extra = [] ): ?int {
		try {
			$message_id = $this->getApi()->sendMessage(
				$chat->getPeerId(),
				$message
			);

			$this->persistStateSafely(
				[
					'lastStatus' => self::STATUS_ONLINE,
				]
			);

			return $message_id;
		} catch ( TransportNotConfigured | VkApiException $e ) {
			$this->markTransportFailure(
				$e,
				'VK delivery failed.',
				Logger::LEVEL_CRITICAL,
				[
					'chatPeerId' => $chat->getPeerId(),
					'chatTitle' => $chat->getTitle(),
					'chatPostID' => $chat->getPost()->ID,
					'extras' => $extra,
				]
			);

			if ( $throw_on_error ) {
				throw $e;
			}
		}

		return null;
	}

	/**
	 * @throws TransportNotConfigured
	 */
	private function getApi(): VkApi {
		if ( null === $this->api ) {
			$access_token = $this->getAccessToken();

			if ( empty( $access_token ) || $this->isAccessTokenEmpty() ) {
				throw TransportNotConfigured::missingAccessToken();
			}

			$this->api = new VkApi( $access_token, $this->getApiVersion() );
		}

		return $this->api;
	}

	/**
	 * @throws TransportNotConfigured
	 */
	private function getNormalizedGroupId(): string {
		$group_id = trim( $this->getGroupId() );

		if ( '' === $group_id ) {
			throw TransportNotConfigured::missingGroupId();
		}

		if ( 1 !== preg_match( '/^-?\d+$/', $group_id ) ) {
			throw TransportNotConfigured::invalidGroupId();
		}

		return ltrim( $group_id, '-' );
	}

	private function persistStateSafely( array $params ): void {
		foreach ( $params as $key => $value ) {
			$this->setParam( $key, $value );
		}

		try {
			$this->savePost();
		} catch ( wppaSavePostException $e ) {
			$this->logger->write(
				[
					'botTitle' => $this->getTitle(),
					'wpPostID' => $this->getPost()->ID,
					'error' => $e->getMessage(),
					'params' => $params,
				],
				'VK bot state could not be persisted.',
				Logger::LEVEL_WARNING
			);
		}
	}

	private function markTransportFailure(
		Throwable $exception,
		string $title,
		int $level = Logger::LEVEL_WARNING,
		array $context = []
	): void {
		$this->persistStateSafely(
			[
				'lastStatus' => self::STATUS_OFFLINE,
			]
		);

		$this->logger->write(
			array_merge(
				[
					'botTitle' => $this->getTitle(),
					'wpPostID' => $this->getPost()->ID,
					'error' => $exception->getMessage(),
				],
				$context
			),
			$title,
			$level
		);
	}

	/**
	 * @throws TransportNotConfigured
	 * @throws VkApiException
	 */
	private function ensureLongPollBootstrap(): void {
		if (
			'' !== $this->getLongPollServer() &&
			'' !== $this->getLongPollKey() &&
			'' !== $this->getLongPollTs()
		) {
			return;
		}

		$this->refreshLongPollBootstrap();
	}

	/**
	 * @throws TransportNotConfigured
	 * @throws VkApiException
	 */
	private function refreshLongPollBootstrap(): void {
		$group_id = $this->getNormalizedGroupId();
		$long_poll = $this->getApi()->getLongPollServer( $group_id );

		$this->persistStateSafely(
			[
				'longPollServer' => (string) ( $long_poll['server'] ?? '' ),
				'longPollKey' => (string) ( $long_poll['key'] ?? '' ),
				'longPollTs' => (string) ( $long_poll['ts'] ?? '' ),
				'lastSyncAt' => gmdate( 'c' ),
				'lastStatus' => self::STATUS_ONLINE,
			]
		);
	}

	private function getFetchUpdatesLockKey(): string {
		return sprintf( 'cf7vk_fetch_updates_lock_%d', $this->getPost()->ID );
	}

	private function acquireFetchUpdatesLock( int $ttl = 60 ): bool {
		$lock_key = $this->getFetchUpdatesLockKey();
		$locked_at = (int) get_option( $lock_key, 0 );
		$now = time();

		if ( $locked_at && ( $now - $locked_at ) < $ttl ) {
			return false;
		}

		if ( $locked_at ) {
			delete_option( $lock_key );
		}

		return add_option( $lock_key, $now, '', false );
	}

	private function releaseFetchUpdatesLock(): void {
		delete_option( $this->getFetchUpdatesLockKey() );
	}

	/**
	 * @throws TransportNotConfigured
	 * @throws VkApiException
	 */
	private function processLongPollUpdates( array $updates ): array {
		$result = [
			'updates' => [],
			'hasNewChats' => false,
			'hasNewConnections' => false,
		];

		foreach ( $updates as $update ) {
			if ( ! is_array( $update ) ) {
				continue;
			}

			if ( 'message_new' !== ( $update['type'] ?? '' ) ) {
				continue;
			}

			try {
				$processed = $this->handleIncomingMessage( (array) ( $update['object'] ?? [] ) );
			} catch ( Throwable $e ) {
				$message = isset( $update['object']['message'] ) && is_array( $update['object']['message'] )
					? $update['object']['message']
					: [];

				$this->logger->write(
					[
						'botTitle' => $this->getTitle(),
						'wpPostID' => $this->getPost()->ID,
						'groupId' => $this->getGroupId(),
						'eventType' => (string) ( $update['type'] ?? '' ),
						'eventId' => (string) ( $update['event_id'] ?? '' ),
						'peerId' => (string) ( $message['peer_id'] ?? '' ),
						'messageId' => (string) ( $message['id'] ?? '' ),
						'conversationMessageId' => (string) ( $message['conversation_message_id'] ?? '' ),
						'error' => $e->getMessage(),
					],
					'VK Long Poll update processing failed.',
					Logger::LEVEL_WARNING
				);

				continue;
			}

			if ( empty( $processed ) ) {
				continue;
			}

			$result['updates'][] = $processed;
			$result['hasNewChats'] = $result['hasNewChats'] || ! empty( $processed['hasNewChat'] );
			$result['hasNewConnections'] = $result['hasNewConnections'] || ! empty( $processed['hasNewConnection'] );
		}

		return $result;
	}

	/**
	 * @throws TransportNotConfigured
	 * @throws VkApiException
	 */
	private function handleIncomingMessage( array $object ): array {
		$message = isset( $object['message'] ) && is_array( $object['message'] )
			? $object['message']
			: [];

		if ( empty( $message ) ) {
			return [];
		}

		$text = trim( (string) ( $message['text'] ?? '' ) );
		$auth_command = trim( $this->getAuthCommand() );

		if ( '' === $auth_command || $text !== $auth_command ) {
			return [];
		}

		$peer_id = (string) ( $message['peer_id'] ?? '' );
		$user_id = (int) ( $message['from_id'] ?? 0 );
		$profile = $this->fetchUserProfile( $user_id );
		$conversation = $this->fetchConversationData( $message );
		$existing_chat = '' !== $peer_id ? Util::getChatByPeerId( $peer_id ) : null;
		$chat = Util::createOrUpdateChatFromVkMessage( $message, $profile, $conversation );
		$has_new_chat = null === $existing_chat;
		$has_new_connection = false;

		if ( ! $this->hasChat( $chat ) ) {
			$connection = $this->connectChat( $chat );

			if ( $connection ) {
				$chat->setPending( $this );
				$has_new_connection = true;
			}
		}

		return [
			'type' => 'message_new',
			'peerId' => $chat->getPeerId(),
			'chatId' => $chat->getPost()->ID,
			'status' => $chat->getConnectionStatus( $this ),
			'hasNewChat' => $has_new_chat,
			'hasNewConnection' => $has_new_connection,
		];
	}

	/**
	 * @throws VkApiException
	 */
	private function fetchUserProfile( int $user_id ): array {
		if ( $user_id <= 0 ) {
			return [];
		}

		$profiles = $this->getApi()->getUsers( [ $user_id ] );

		return isset( $profiles[0] ) && is_array( $profiles[0] ) ? $profiles[0] : [];
	}

	/**
	 * @throws VkApiException
	 */
	private function fetchConversationData( array $message ): array {
		$peer_id = (string) ( $message['peer_id'] ?? '' );
		$conversation_message_id = (string) ( $message['conversation_message_id'] ?? '' );

		if ( '' === $peer_id || '' === $conversation_message_id ) {
			return [];
		}

		return $this->getApi()->getConversationByMessage( $peer_id, $conversation_message_id );
	}
}
