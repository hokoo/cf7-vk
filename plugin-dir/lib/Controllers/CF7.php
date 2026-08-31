<?php

namespace iTRON\cf7Vk\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use iTRON\cf7Vk\Channel;
use iTRON\cf7Vk\Client;
use iTRON\cf7Vk\Logger;
use iTRON\wpConnections\Exceptions\RelationNotFound;
use iTRON\wpConnections\Query;
use WPCF7_ContactForm;
use WPCF7_Submission;

class CF7 {
	public static function handleSubscribe( WPCF7_ContactForm $contact_form, &$abort, WPCF7_Submission $submission ): void {
		if ( $abort ) {
			return;
		}

		if ( apply_filters( 'cf7vk_skip_delivery', false, $contact_form, $submission ) ) {
			self::emitDeliveryCompletion( self::createDeliverySummary( $contact_form, 'skipped' ) );
			return;
		}

		$client = Client::getInstance();
		try {
			$connections = $client
				->getForm2ChannelRelation()
				->findConnections( new Query\Connection( $contact_form->id() ) );
		} catch ( RelationNotFound $e ) {
			$client->getLogger()->write(
				[
					'contactFormId' => $contact_form->id(),
					'contactFormTitle' => $contact_form->title(),
					'error' => $e->getMessage(),
				],
				'CF7 delivery relation lookup failed.',
				Logger::LEVEL_WARNING
			);

			do_action(
				'cf7vk_delivery_exception',
				$e,
				null,
				null,
				[
					'contact_form_id' => $contact_form->id(),
					'contact_form_title' => $contact_form->title(),
					'stage' => 'form_channel_lookup',
				]
			);

			$summary = self::createDeliverySummary( $contact_form, 'failed' );
			$summary['errors'][] = self::errorData( 'form_channel_lookup', $e );
			self::emitDeliveryCompletion( $summary );

			return;
		}

		if ( $connections->isEmpty() ) {
			self::emitDeliveryCompletion( self::createDeliverySummary( $contact_form, 'no_channels' ) );
			return;
		}

		$mail = (array) $contact_form->prop( 'mail' );
		$message = apply_filters(
			'cf7vk_unfiltered_message',
			wpcf7_mail_replace_tags( $mail['body'] ?? '' ),
			$submission
		);

		$prepared_message = MessageFormatter::formatForVk(
			(string) $message,
			$contact_form,
			$submission,
			$mail
		);
		$prepared_message = apply_filters(
			'cf7vk_prepared_message',
			$prepared_message,
			$submission,
			$contact_form,
			$mail
		);
		$delivery_context = [
			'contact_form_id' => $contact_form->id(),
			'contact_form_title' => $contact_form->title(),
			'submission' => $submission,
			'prepared_message' => $prepared_message,
			'raw_message' => $message,
			'mail' => $mail,
		];

		$target_channels = $client->getChannels()->filterByIDs( $connections->column( 'to' ) );
		$summary = self::createDeliverySummary( $contact_form, 'pending' );
		$summary['messageLength'] = strlen( $prepared_message );

		foreach ( $target_channels as $channel ) {
			try {
				/** @var Channel $channel */
				$channel_result = $channel->doSendOut( $prepared_message, $delivery_context );
				$summary['channels'][] = $channel_result;
				$summary['totals']['channels']++;
				$summary['totals']['attempted'] += (int) ( $channel_result['attempted'] ?? 0 );
				$summary['totals']['succeeded'] += (int) ( $channel_result['succeeded'] ?? 0 );
				$summary['totals']['failed'] += (int) ( $channel_result['failed'] ?? 0 );
				$summary['totals']['skipped'] += (int) ( $channel_result['skipped'] ?? 0 );
			} catch ( RelationNotFound $e ) {
				$client->getLogger()->write(
					[
						'contactFormId' => $contact_form->id(),
						'contactFormTitle' => $contact_form->title(),
						'channelId' => $channel->getPost()->ID ?? null,
						'error' => $e->getMessage(),
					],
					'CF7 delivery relation lookup failed.',
					Logger::LEVEL_WARNING
				);

				do_action(
					'cf7vk_delivery_exception',
					$e,
					$channel,
					null,
					array_merge(
						$delivery_context,
						[
							'stage' => 'relation_lookup',
						]
					)
				);
				$summary['totals']['failed']++;
				$summary['errors'][] = self::errorData( 'channel_relation_lookup', $e );
			}
		}

		self::emitDeliveryCompletion( self::finalizeDeliverySummary( $summary ) );
	}

	private static function createDeliverySummary( WPCF7_ContactForm $contact_form, string $status ): array {
		return [
			'status' => $status,
			'contactFormId' => $contact_form->id(),
			'contactFormTitle' => $contact_form->title(),
			'messageLength' => 0,
			'totals' => [
				'channels' => 0,
				'attempted' => 0,
				'succeeded' => 0,
				'failed' => 0,
				'skipped' => 0,
			],
			'channels' => [],
			'errors' => [],
		];
	}

	private static function finalizeDeliverySummary( array $summary ): array {
		if ( $summary['totals']['failed'] > 0 && $summary['totals']['succeeded'] > 0 ) {
			$summary['status'] = 'partial_failure';

			return $summary;
		}

		if ( $summary['totals']['failed'] > 0 ) {
			$summary['status'] = 'failed';

			return $summary;
		}

		if ( $summary['totals']['succeeded'] > 0 ) {
			$summary['status'] = 'sent';

			return $summary;
		}

		if ( $summary['totals']['skipped'] > 0 ) {
			$summary['status'] = 'no_active_chats';

			return $summary;
		}

		$summary['status'] = 0 === $summary['totals']['channels'] ? 'no_channels' : 'no_recipients';

		return $summary;
	}

	private static function errorData( string $stage, \Throwable $exception ): array {
		return [
			'stage' => $stage,
			'type' => get_class( $exception ),
			'code' => (int) $exception->getCode(),
			'message' => \iTRON\cf7Vk\LogRedactor::redactString( $exception->getMessage() ),
		];
	}

	private static function emitDeliveryCompletion( array $summary ): void {
		do_action( 'cf7vk_deliveries_completed', $summary );
	}
}
