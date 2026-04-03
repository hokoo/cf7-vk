<?php

namespace iTRON\cf7Vk\Collections;

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

use iTRON\cf7Vk\Client;
use iTRON\cf7Vk\Logger;
use iTRON\wpConnections\Connection;
use iTRON\wpConnections\ConnectionCollection;
use iTRON\wpPostAble\wpPostAble;
use iTRON\wpPostAble\Exceptions\wppaLoadPostException;
use Ramsey\Collection\CollectionInterface;

abstract class Collection extends \Ramsey\Collection\Collection {
	public function createByConnections( ConnectionCollection $connections, string $sourceColumn = 'from' ): self {
		foreach ( $connections as $connection ) {
			/** @var Connection $connection */
			$entity_id = (int) ( 'to' === $sourceColumn ? $connection->to : $connection->from );

			$this->addById( $entity_id, $connection );
		}

		return $this;
	}

	/**
	 * @TODO Logging exceptions
	 */
	public function createByIDs( array $ids ): self {
		foreach ( $ids as $id ) {
			$this->addById( (int) $id );
		}

		return $this;
	}

	/**
	 * @param array $ids
	 *
	 * @return CollectionInterface
	 */
	public function filterByIDs( array $ids ): CollectionInterface {
		return $this->filter( function ( wpPostAble $collectionItem ) use ( $ids ) {
			return in_array( $collectionItem->getPost()->ID, $ids, false );
		} );
	}

	public function contains( $element, bool $strict = true ): bool {
		/** @var wpPostAble $element */
		return ! $this->filterByIDs( [ $element->getPost()->ID ] )->isEmpty();
	}

	private function addById( int $id, ?Connection $connection = null ): void {
		if ( $id <= 0 ) {
			return;
		}

		$classname = $this->getType();

		try {
			$this->add( new $classname( $id ) );
		} catch ( wppaLoadPostException $exception ) {
			$this->logOrphanedConnection( $id, $connection, $exception );

			if ( $connection && ! empty( $connection->id ) ) {
				Client::getInstance()->getConnectionsClient()->getStorage()->deleteSpecificConnections( $connection->id );
			}
		}
	}

	private function logOrphanedConnection( int $id, ?Connection $connection, wppaLoadPostException $exception ): void {
		Client::getInstance()->getLogger()->write(
			[
				'entityClass' => $this->getType(),
				'entityId' => $id,
				'connectionId' => $connection->id ?? null,
				'connectionRelation' => $connection->relation ?? null,
				'connectionFrom' => $connection->from ?? null,
				'connectionTo' => $connection->to ?? null,
				'error' => $exception->getMessage(),
			],
			'Orphaned connection removed during collection hydration.',
			Logger::LEVEL_WARNING
		);
	}
}
