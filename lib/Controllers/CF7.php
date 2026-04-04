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
			return;
		}

		$client = Client::getInstance();
		$connections = $client
			->getForm2ChannelRelation()
			->findConnections( new Query\Connection( $contact_form->id() ) );

		if ( $connections->isEmpty() ) {
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

		foreach ( $target_channels as $channel ) {
			try {
				/** @var Channel $channel */
				$channel->doSendOut( $prepared_message, $delivery_context );
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
			}
		}
	}
}
