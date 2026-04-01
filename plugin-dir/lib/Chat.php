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

		$connection->meta->where( 'key', self::STATUS_KEY )->clear();
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
		$meta = $connection->meta->where( 'key', self::STATUS_KEY )->first();

		return $meta ? (string) $meta->value : self::STATUS_PENDING;
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
