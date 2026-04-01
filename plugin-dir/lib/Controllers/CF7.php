<?php

namespace iTRON\cf7Vk\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use iTRON\cf7Vk\Channel;
use iTRON\cf7Vk\Client;
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

		$prepared_message = wp_strip_all_tags( (string) $message );
		$prepared_message = apply_filters(
			'cf7vk_prepared_message',
			$prepared_message,
			$submission,
			$contact_form
		);

		$target_channels = $client->getChannels()->filterByIDs( $connections->column( 'to' ) );

		foreach ( $target_channels as $channel ) {
			try {
				/** @var Channel $channel */
				$channel->doSendOut(
					$prepared_message,
					[
						'contact_form_id' => $contact_form->id(),
						'submission' => $submission,
						'mail' => $mail,
					]
				);
			} catch ( RelationNotFound $e ) {
				do_action( 'cf7vk_delivery_exception', $e, $channel, $contact_form, $submission );
			}
		}
	}
}
