<?php

namespace iTRON\cf7Vk\Controllers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WPCF7_ContactForm;
use WPCF7_Submission;

class MessageFormatter {
	public static function formatForVk(
		string $message,
		WPCF7_ContactForm $contact_form,
		WPCF7_Submission $submission,
		array $mail = []
	): string {
		$body = self::normalizeBody( $message, (bool) ( $mail['use_html'] ?? false ) );
		$subject = trim( (string) ( $mail['subject'] ?? '' ) );
		$form_title = trim( (string) $contact_form->title() );
		$posted = (array) $submission->get_posted_data();
		$meta_lines = [];

		if ( '' !== $form_title ) {
			$meta_lines[] = sprintf(
				/* translators: %s: Contact Form 7 form title */
				__( 'Form: %s', 'vk-notifications-for-contact-form-7' ),
				$form_title
			);
		}

		if ( '' !== $subject ) {
			$meta_lines[] = sprintf(
				/* translators: %s: mail subject */
				__( 'Subject: %s', 'vk-notifications-for-contact-form-7' ),
				self::normalizeInlineText( $subject )
			);
		}

		$posted_pairs = self::preparePostedFields( $posted );

		if ( ! empty( $posted_pairs ) ) {
			$meta_lines[] = '';
			$meta_lines[] = __( 'Fields:', 'vk-notifications-for-contact-form-7' );
			$meta_lines = array_merge( $meta_lines, $posted_pairs );
		}

		if ( '' !== $body ) {
			$meta_lines[] = '';
			$meta_lines[] = __( 'Message:', 'vk-notifications-for-contact-form-7' );
			$meta_lines[] = $body;
		}

		$output = implode( "\n", array_values( array_filter( $meta_lines, static function ( $line, $index ) use ( $meta_lines ) {
			if ( '' !== $line ) {
				return true;
			}

			$prev = $meta_lines[ $index - 1 ] ?? null;
			$next = $meta_lines[ $index + 1 ] ?? null;

			return '' !== $prev && '' !== $next;
		}, ARRAY_FILTER_USE_BOTH ) ) );

		return trim( preg_replace( "/\n{3,}/", "\n\n", $output ) ?? $output );
	}

	private static function normalizeBody( string $message, bool $is_html ): string {
		if ( $is_html ) {
			$message = preg_replace( '/<(br|\/p|\/div|\/li|\/tr|\/h[1-6])\b[^>]*>/i', "\n", $message ) ?? $message;
			$message = preg_replace( '/<li\b[^>]*>/i', "- ", $message ) ?? $message;
		}

		$message = wp_strip_all_tags( html_entity_decode( $message, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );

		return trim( preg_replace( "/[ \t]+\n/", "\n", preg_replace( "/\n{3,}/", "\n\n", $message ) ?? $message ) ?? $message );
	}

	private static function preparePostedFields( array $posted_data ): array {
		$lines = [];

		foreach ( $posted_data as $key => $value ) {
			if ( self::shouldSkipPostedField( (string) $key, $value ) ) {
				continue;
			}

			$normalized = self::normalizePostedValue( $value );

			if ( '' === $normalized ) {
				continue;
			}

			$lines[] = sprintf(
				'%s: %s',
				self::humanizeFieldName( (string) $key ),
				$normalized
			);
		}

		return $lines;
	}

	private static function shouldSkipPostedField( string $key, $value ): bool {
		if ( '' === $key ) {
			return true;
		}

		if ( 0 === strpos( $key, '_' ) ) {
			return true;
		}

		return is_array( $value ) && empty( $value );
	}

	private static function normalizePostedValue( $value ): string {
		if ( is_array( $value ) ) {
			$parts = array_filter(
				array_map(
					static function ( $item ): string {
						return self::normalizeInlineText( (string) $item );
					},
					$value
				)
			);

			return implode( ', ', $parts );
		}

		return self::normalizeInlineText( (string) $value );
	}

	private static function normalizeInlineText( string $value ): string {
		$value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$value = wp_strip_all_tags( $value );
		$value = preg_replace( '/\s+/u', ' ', $value ) ?? $value;

		return trim( $value );
	}

	private static function humanizeFieldName( string $key ): string {
		$key = preg_replace( '/^your-/', '', $key ) ?? $key;
		$key = str_replace( [ '_', '-' ], ' ', $key );
		$key = trim( $key );

		if ( '' === $key ) {
			return __( 'Field', 'vk-notifications-for-contact-form-7' );
		}

		return ucwords( $key );
	}
}
