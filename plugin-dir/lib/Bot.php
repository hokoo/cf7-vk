<?php

namespace iTRON\cf7Vk;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use iTRON\wpConnections\Connection;
use iTRON\wpConnections\Exceptions\ConnectionWrongData;
use iTRON\wpConnections\Exceptions\Exception;
use iTRON\wpConnections\Exceptions\MissingParameters;
use iTRON\wpConnections\Exceptions\RelationNotFound;
use iTRON\wpConnections\Query;
use iTRON\wpPostAble\Exceptions\wppaCreatePostException;
use iTRON\wpPostAble\Exceptions\wppaLoadPostException;
use iTRON\wpPostAble\Exceptions\wppaSavePostException;
use iTRON\wpPostAble\wpPostAble;
use iTRON\wpPostAble\wpPostAbleTrait;

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

		return $this;
	}

	public function isAccessTokenEmpty(): bool {
		$token = $this->getAccessToken();

		return
			empty( $token ) ||
			$token !== rtrim( ltrim( $token, '[' ), ']' );
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
		} catch ( Exception $e ) {
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

		return $this;
	}
}
