<?php

namespace iTRON\cf7Vk;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use iTRON\wpConnections\Connection;
use iTRON\wpConnections\Exceptions\ConnectionNotFound;
use iTRON\wpConnections\Exceptions\ConnectionWrongData;
use iTRON\wpConnections\Exceptions\MissingParameters;
use iTRON\wpConnections\Exceptions\RelationNotFound;
use iTRON\wpConnections\Meta;
use iTRON\wpConnections\Query;
use iTRON\wpPostAble\Exceptions\wppaCreatePostException;
use iTRON\wpPostAble\Exceptions\wppaLoadPostException;
use iTRON\wpPostAble\Exceptions\wppaSavePostException;
use iTRON\wpPostAble\wpPostAble;
use iTRON\wpPostAble\wpPostAbleTrait;

class Chat extends Entity implements wpPostAble {
	use wpPostAbleTrait;

	public const STATUS_KEY = 'status';
	public const STATUS_ACTIVE = 'active';
	public const STATUS_PENDING = 'pending';
	public const STATUS_MUTED = 'muted';
	public const TYPE_PRIVATE = 'private';
	public const TYPE_CHAT = 'chat';
	public const TYPE_COMMUNITY = 'community';

	/**
	 * @throws wppaLoadPostException
	 * @throws wppaCreatePostException
	 */
	public function __construct( int $chat_id = 0 ) {
		parent::__construct();

		$this->wpPostAble( Client::CPT_CHAT, $chat_id );
	}

	public function getPeerId(): string {
		return (string) $this->getParam( 'peerId' );
	}

	public function setPeerId( string $peer_id ): self {
		$this->setParam( 'peerId', trim( $peer_id ) );
		$this->savePost();

		return $this;
	}

	public function getUserId(): string {
		return (string) $this->getParam( 'userId' );
	}

	public function setUserId( string $user_id ): self {
		$this->setParam( 'userId', trim( $user_id ) );
		$this->savePost();

		return $this;
	}

	public function getChatType(): string {
		return (string) $this->getParam( 'chatType' );
	}

	public function setChatType( string $chat_type ): self {
		$this->setParam( 'chatType', trim( $chat_type ) );
		$this->savePost();

		return $this;
	}

	public function getDisplayName(): string {
		return (string) ( $this->getParam( 'displayName' ) ?: $this->getTitle() );
	}

	public function setDisplayName( string $display_name ): self {
		$this->setParam( 'displayName', trim( $display_name ) );
		$this->savePost();

		return $this;
	}

	public function getUsername(): string {
		return (string) $this->getParam( 'username' );
	}

	public function setUsername( string $username ): self {
		$this->setParam( 'username', trim( $username ) );
		$this->savePost();

		return $this;
	}

	public function getConnectedAt(): string {
		return (string) $this->getParam( 'connectedAt' );
	}

	public function setConnectedAt( string $connected_at ): self {
		$this->setParam( 'connectedAt', trim( $connected_at ) );
		$this->savePost();

		return $this;
	}

	public function getConversationMessageId(): string {
		return (string) $this->getParam( 'conversationMessageId' );
	}

	public function setConversationMessageId( string $conversation_message_id ): self {
		$this->setParam( 'conversationMessageId', trim( $conversation_message_id ) );
		$this->savePost();

		return $this;
	}

	public function getLastMessageId(): string {
		return (string) $this->getParam( 'lastMessageId' );
	}

	public function setLastMessageId( string $message_id ): self {
		$this->setParam( 'lastMessageId', trim( $message_id ) );
		$this->savePost();

		return $this;
	}

	public function getLastMessageText(): string {
		return (string) $this->getParam( 'lastMessageText' );
	}

	public function setLastMessageText( string $text ): self {
		$this->setParam( 'lastMessageText', trim( $text ) );
		$this->savePost();

		return $this;
	}

	public function getLastEventAt(): string {
		return (string) $this->getParam( 'lastEventAt' );
	}

	public function setLastEventAt( string $timestamp ): self {
		$this->setParam( 'lastEventAt', trim( $timestamp ) );
		$this->savePost();

		return $this;
	}

	public function getResolvedTitle(): string {
		return $this->getTitle() ?: $this->getDisplayName() ?: $this->getPeerId();
	}

	public static function detectTypeByPeerId( int $peer_id ): string {
		if ( $peer_id > 2000000000 ) {
			return self::TYPE_CHAT;
		}

		if ( $peer_id < 0 ) {
			return self::TYPE_COMMUNITY;
		}

		return self::TYPE_PRIVATE;
	}

	/**
	 * @throws wppaSavePostException
	 */
	public function syncFromVkPayload( array $payload ): self {
		$params = [
			'peerId',
			'userId',
			'chatType',
			'displayName',
			'username',
			'connectedAt',
			'conversationMessageId',
			'lastMessageId',
			'lastMessageText',
			'lastEventAt',
		];

		foreach ( $params as $param ) {
			if ( array_key_exists( $param, $payload ) ) {
				$this->setParam( $param, trim( (string) $payload[ $param ] ) );
			}
		}

		if ( ! empty( $payload['title'] ) ) {
			$this->setTitle( trim( (string) $payload['title'] ) );
		}

		if ( 'publish' !== ( $this->getPost()->post_status ?? '' ) ) {
			$this->publish();

			return $this;
		}

		$this->savePost();

		return $this;
	}

	/**
	 * @throws ConnectionWrongData
	 * @throws MissingParameters
	 * @throws RelationNotFound
	 */
	public function connectChannel( Channel $channel ): Entity {
		$channel->connectChat( $this );

		return $this;
	}

	/**
	 * @throws RelationNotFound
	 */
	public function disconnectChannel( Channel $channel = null ): Entity {
		$channel_id = $channel?->getPost()->ID;

		$this->client
			->getChat2ChannelRelation()
			->detachConnections( new Query\Connection( $this->getPost()->ID, $channel_id ) );

		return $this;
	}

	/**
	 * @throws ConnectionWrongData
	 * @throws ConnectionNotFound
	 * @throws RelationNotFound
	 */
	public function setPending( Bot $bot ): self {
		return $this->setBotConnectionStatus( $bot, self::STATUS_PENDING );
	}

	/**
	 * @throws ConnectionWrongData
	 * @throws ConnectionNotFound
	 * @throws RelationNotFound
	 */
	public function setActivated( Bot $bot ): self {
		return $this->setBotConnectionStatus( $bot, self::STATUS_ACTIVE );
	}

	/**
	 * @throws ConnectionWrongData
	 * @throws ConnectionNotFound
	 * @throws RelationNotFound
	 */
	public function setMuted( Bot $bot ): self {
		return $this->setBotConnectionStatus( $bot, self::STATUS_MUTED );
	}

	/**
	 * @throws ConnectionWrongData
	 * @throws ConnectionNotFound
	 * @throws RelationNotFound
	 */
	private function setBotConnectionStatus( Bot $bot, string $status ): self {
		$connection = $this->getBotConnection( $bot );

		$filtered_meta = $connection->meta->filter(
			static function ( Meta $meta ): bool {
				return self::STATUS_KEY !== $meta->getKey();
			}
		);

		$connection->meta->clear();
		$connection->meta->fromArray( $filtered_meta->toArray() );
		$connection->meta->add( new Meta( self::STATUS_KEY, $status ) );
		$connection->update();

		return $this;
	}

	/**
	 * @throws ConnectionNotFound
	 * @throws RelationNotFound
	 */
	public function getConnectionStatus( Bot $bot ): string {
		$connection = $this->getBotConnection( $bot );
		$status_values = (array) ( $connection->meta->toArray()[ self::STATUS_KEY ] ?? [] );
		$latest_status = end( $status_values );

		return $latest_status ? (string) $latest_status : self::STATUS_PENDING;
	}

	/**
	 * @throws ConnectionNotFound
	 * @throws RelationNotFound
	 */
	private function getBotConnection( Bot $bot ): Connection {
		$connections = $this->client
			->getBot2ChatRelation()
			->findConnections( new Query\Connection( $bot->getPost()->ID, $this->getPost()->ID ) );

		if ( $connections->isEmpty() ) {
			throw new ConnectionNotFound();
		}

		return $connections->first();
	}
}
