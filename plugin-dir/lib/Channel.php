<?php

namespace iTRON\cf7Vk;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use iTRON\cf7Vk\Collections\BotCollection;
use iTRON\cf7Vk\Collections\ChatCollection;
use iTRON\cf7Vk\Collections\FormCollection;
use iTRON\cf7Vk\Exceptions\TransportNotConfigured;
use iTRON\cf7Vk\Exceptions\VkApiException;
use iTRON\wpConnections\Abstracts\Connection;
use iTRON\wpConnections\Exceptions\ConnectionNotFound;
use iTRON\wpConnections\Exceptions\ConnectionWrongData;
use iTRON\wpConnections\Exceptions\MissingParameters;
use iTRON\wpConnections\Exceptions\RelationNotFound;
use iTRON\wpConnections\Query;
use iTRON\wpPostAble\Exceptions\wppaCreatePostException;
use iTRON\wpPostAble\Exceptions\wppaLoadPostException;
use iTRON\wpPostAble\wpPostAble;
use iTRON\wpPostAble\wpPostAbleTrait;
use OutOfBoundsException;
use Ramsey\Collection\Exception\NoSuchElementException;
use Throwable;

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
		} catch ( OutOfBoundsException | NoSuchElementException $e ) {
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

	public function doSendOut( string $message, array $context = [] ): array {
		$result = $this->createDeliveryResult();

		do_action( 'cf7vk_channel_sendout', $this, $message, $context );

		try {
			$bot = $this->getBot();
		} catch ( RelationNotFound $e ) {
			return $this->recordChannelRelationFailure( $result, 'bot_lookup', $e, $context );
		}

		if ( ! $bot ) {
			$result['status'] = 'no_bot';

			return $result;
		}

		$result['botId'] = $bot->getPost()->ID;
		$result['hasBot'] = true;

		try {
			$chats = $this->getChats();
		} catch ( RelationNotFound $e ) {
			return $this->recordChannelRelationFailure( $result, 'chat_lookup', $e, $context );
		}

		if ( $chats->isEmpty() ) {
			$result['status'] = 'no_chats';

			return $result;
		}

		foreach ( $chats as $chat ) {
			/** @var Chat $chat */
			try {
				$chat_status = $chat->getConnectionStatus( $bot );
			} catch ( ConnectionNotFound | RelationNotFound $e ) {
				$exception_context = array_merge(
					$context,
					[
						'channelId' => $this->getPost()->ID,
						'stage' => 'status_lookup',
					]
				);

				$this->logger->write(
					[
						'channelId' => $this->getPost()->ID,
						'channelTitle' => $this->getTitle(),
						'chatId' => $chat->getPost()->ID,
						'chatPeerId' => $chat->getPeerId(),
						'error' => $e->getMessage(),
						'context' => $this->normalizeLogContext( $exception_context ),
					],
					'VK channel delivery status lookup failed.',
					Logger::LEVEL_WARNING
				);

				do_action( 'cf7vk_delivery_exception', $e, $this, $chat, $exception_context );
				$result['failed']++;
				$result['recipients'][] = $this->createRecipientResult( $chat, 'status_lookup_failed', null, $e );

				continue;
			}

			if ( Chat::STATUS_ACTIVE !== $chat_status ) {
				$result['skipped']++;
				$result['recipients'][] = $this->createRecipientResult( $chat, 'skipped', $chat_status );
				continue;
			}

			$delivery_context = array_merge(
				$context,
				[
					'channelId' => $this->getPost()->ID,
					'chatStatus' => $chat_status,
				]
			);
			$result['attempted']++;

			try {
				$message_id = $bot->sendMessage(
					$chat,
					$message,
					true,
					$delivery_context
				);
				$result['succeeded']++;
				$result['recipients'][] = $this->createRecipientResult( $chat, 'sent', $chat_status, null, $message_id );
			} catch ( TransportNotConfigured | VkApiException $e ) {
				$this->logger->write(
					[
						'channelId' => $this->getPost()->ID,
						'channelTitle' => $this->getTitle(),
						'chatId' => $chat->getPost()->ID,
						'chatPeerId' => $chat->getPeerId(),
						'chatStatus' => $chat_status,
						'error' => $e->getMessage(),
						'context' => $this->normalizeLogContext( $delivery_context ),
					],
					'VK channel delivery failed.',
					Logger::LEVEL_WARNING
				);

				do_action( 'cf7vk_delivery_exception', $e, $this, $chat, $delivery_context );
				$result['failed']++;
				$result['recipients'][] = $this->createRecipientResult( $chat, 'failed', $chat_status, $e );
			}
		}

		return $this->finalizeDeliveryResult( $result );
	}

	private function createDeliveryResult(): array {
		return [
			'channelId' => $this->getPost()->ID,
			'botId' => null,
			'hasBot' => false,
			'status' => 'pending',
			'attempted' => 0,
			'succeeded' => 0,
			'failed' => 0,
			'skipped' => 0,
			'recipients' => [],
			'errors' => [],
		];
	}

	private function recordChannelRelationFailure( array $result, string $stage, RelationNotFound $exception, array $context ): array {
		$exception_context = array_merge(
			$context,
			[
				'channelId' => $this->getPost()->ID,
				'stage' => $stage,
			]
		);

		$this->logger->write(
			[
				'channelId' => $this->getPost()->ID,
				'channelTitle' => $this->getTitle(),
				'error' => $exception->getMessage(),
				'context' => $this->normalizeLogContext( $exception_context ),
			],
			'VK channel delivery relation lookup failed.',
			Logger::LEVEL_WARNING
		);

		do_action( 'cf7vk_delivery_exception', $exception, $this, null, $exception_context );

		$result['status'] = 'failed';
		$result['failed']++;
		$result['errors'][] = array_merge(
			[
				'stage' => $stage,
			],
			$this->errorData( $exception )
		);

		return $result;
	}

	private function createRecipientResult(
		Chat $chat,
		string $status,
		?string $chat_status = null,
		?Throwable $exception = null,
		?int $message_id = null
	): array {
		$result = [
			'chatId' => $chat->getPost()->ID,
			'status' => $status,
		];

		if ( null !== $chat_status ) {
			$result['chatStatus'] = $chat_status;
		}

		if ( null !== $message_id ) {
			$result['messageId'] = $message_id;
		}

		if ( null !== $exception ) {
			$result['error'] = $this->errorData( $exception );
		}

		return $result;
	}

	private function errorData( Throwable $exception ): array {
		return [
			'type' => get_class( $exception ),
			'code' => (int) $exception->getCode(),
			'message' => LogRedactor::redactString( $exception->getMessage() ),
		];
	}

	private function finalizeDeliveryResult( array $result ): array {
		if ( $result['failed'] > 0 && $result['succeeded'] > 0 ) {
			$result['status'] = 'partial_failure';

			return $result;
		}

		if ( $result['failed'] > 0 ) {
			$result['status'] = 'failed';

			return $result;
		}

		if ( $result['succeeded'] > 0 ) {
			$result['status'] = 'sent';

			return $result;
		}

		if ( $result['skipped'] > 0 ) {
			$result['status'] = 'no_active_chats';

			return $result;
		}

		$result['status'] = 'no_recipients';

		return $result;
	}

	private function normalizeLogContext( array $context ): array {
		$normalized = [];

		foreach ( $context as $key => $value ) {
			$normalized[ $key ] = $this->normalizeLogValue( $value );
		}

		return $normalized;
	}

	private function normalizeLogValue( $value ) {
		if ( null === $value || is_scalar( $value ) ) {
			return $value;
		}

		if ( is_array( $value ) ) {
			$normalized = [];

			foreach ( $value as $key => $item ) {
				$normalized[ $key ] = $this->normalizeLogValue( $item );
			}

			return $normalized;
		}

		return sprintf( '[object:%s]', get_class( $value ) );
	}

	protected function connectChannel( Channel $channel ): Entity {
		return $this;
	}

	protected function disconnectChannel( Channel $channel = null ): Entity {
		return $this;
	}
}
