<?php

namespace iTRON\cf7Vk;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use iTRON\cf7Vk\Collections\BotCollection;
use iTRON\cf7Vk\Collections\ChatCollection;
use iTRON\cf7Vk\Collections\FormCollection;
use iTRON\wpConnections\Abstracts\Connection;
use iTRON\wpConnections\Exceptions\ConnectionWrongData;
use iTRON\wpConnections\Exceptions\MissingParameters;
use iTRON\wpConnections\Exceptions\RelationNotFound;
use iTRON\wpConnections\Query;
use iTRON\wpPostAble\Exceptions\wppaCreatePostException;
use iTRON\wpPostAble\Exceptions\wppaLoadPostException;
use iTRON\wpPostAble\wpPostAble;
use iTRON\wpPostAble\wpPostAbleTrait;
use OutOfBoundsException;

class Channel extends Entity implements wpPostAble {
	use wpPostAbleTrait;

	public ChatCollection $chats;
	public FormCollection $forms;
	public ?Bot $bot = null;

	/**
	 * @throws wppaCreatePostException
	 * @throws wppaLoadPostException
	 */
	public function __construct( int $post_id = 0 ) {
		parent::__construct();

		$this->wpPostAble( Client::CPT_CHANNEL, $post_id );
	}

	/**
	 * @throws RelationNotFound
	 */
	public function getChats(): ChatCollection {
		if ( isset( $this->chats ) ) {
			return $this->chats;
		}

		$connections = $this->client
			->getChat2ChannelRelation()
			->findConnections( new Query\Connection( 0, $this->getPost()->ID ) );

		$this->chats = new ChatCollection();

		return $this->chats->createByConnections( $connections );
	}

	/**
	 * @throws RelationNotFound
	 */
	public function getForms(): FormCollection {
		if ( isset( $this->forms ) ) {
			return $this->forms;
		}

		$connections = $this->client
			->getForm2ChannelRelation()
			->findConnections( new Query\Connection( 0, $this->getPost()->ID ) );

		$this->forms = new FormCollection();

		return $this->forms->createByConnections( $connections );
	}

	/**
	 * @throws RelationNotFound
	 */
	public function getBot(): ?Bot {
		if ( isset( $this->bot ) ) {
			return $this->bot;
		}

		$connections = $this->client
			->getBot2ChannelRelation()
			->findConnections( new Query\Connection( 0, $this->getPost()->ID ) );

		$bots = new BotCollection();

		try {
			$this->bot = $bots->createByConnections( $connections )->first();
		} catch ( OutOfBoundsException $e ) {
			$this->bot = null;
		}

		return $this->bot;
	}

	/**
	 * @throws MissingParameters
	 * @throws ConnectionWrongData
	 * @throws RelationNotFound
	 */
	public function connectChat( Chat $chat ): Connection {
		return $this->client
			->getChat2ChannelRelation()
			->createConnection( new Query\Connection( $chat->getPost()->ID, $this->getPost()->ID ) );
	}

	/**
	 * @throws RelationNotFound
	 */
	public function disconnectChat( Chat $chat ): self {
		$this->client
			->getChat2ChannelRelation()
			->detachConnections( new Query\Connection( $chat->getPost()->ID, $this->getPost()->ID ) );

		return $this;
	}

	/**
	 * @throws RelationNotFound
	 */
	public function hasChat( Chat $chat ): bool {
		return $this->getChats()->contains( $chat );
	}

	/**
	 * @throws MissingParameters
	 * @throws ConnectionWrongData
	 * @throws RelationNotFound
	 */
	public function connectForm( Form $form ): self {
		$this->client
			->getForm2ChannelRelation()
			->createConnection( new Query\Connection( $form->getPost()->ID, $this->getPost()->ID ) );

		return $this;
	}

	/**
	 * @throws RelationNotFound
	 */
	public function disconnectForm( Form $form ): self {
		$this->client
			->getForm2ChannelRelation()
			->detachConnections( new Query\Connection( $form->getPost()->ID, $this->getPost()->ID ) );

		return $this;
	}

	/**
	 * @throws MissingParameters
	 * @throws ConnectionWrongData
	 * @throws RelationNotFound
	 */
	public function connectBot( Bot $bot ): self {
		$this->disconnectBot();

		$this->client
			->getBot2ChannelRelation()
			->createConnection( new Query\Connection( $bot->getPost()->ID, $this->getPost()->ID ) );

		$this->bot = $bot;

		return $this;
	}

	/**
	 * @throws RelationNotFound
	 */
	public function disconnectBot(): self {
		if ( ! $this->getBot() ) {
			return $this;
		}

		$query = new Query\Connection();
		$query->set( 'from', $this->getBot()->getPost()->ID );
		$query->set( 'to', $this->getPost()->ID );

		$this->client->getBot2ChannelRelation()->detachConnections( $query );
		$this->bot = null;

		return $this;
	}

	/**
	 * @throws RelationNotFound
	 */
	public function hasBot( Bot $bot = null ): bool {
		if ( ! $this->getBot() ) {
			return false;
		}

		if ( ! $bot ) {
			return true;
		}

		return $this->getBot()->getPost()->ID === $bot->getPost()->ID;
	}

	public function doSendOut( string $message, array $context = [] ): void {
		/**
		 * Transport delivery is implemented in later epics.
		 * The shell still exposes a stable hook so the submission flow remains wired.
		 */
		do_action( 'cf7vk_channel_sendout', $this, $message, $context );
	}

	protected function connectChannel( Channel $channel ): Entity {
		return $this;
	}

	protected function disconnectChannel( Channel $channel = null ): Entity {
		return $this;
	}
}
