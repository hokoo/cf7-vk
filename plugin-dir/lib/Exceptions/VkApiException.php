<?php

namespace iTRON\cf7Vk\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VkApiException extends Exception {
	private array $payload;

	public function __construct( string $message = '', int $code = 0, array $payload = [], ?\Throwable $previous = null ) {
		parent::__construct( $message, $code, $previous );
		$this->payload = $payload;
	}

	public static function fromWpError( \WP_Error $error ): self {
		return new self( esc_html( $error->get_error_message() ) );
	}

	public static function longPollRequestFailed( int $status_code, array $payload = [] ): self {
		return self::withPayload(
			sprintf(
				/* translators: %d: HTTP status code returned by VK Long Poll */
				esc_html__( 'VK Long Poll request failed with HTTP status %d.', 'cf7-vk' ),
				$status_code
			),
			$status_code,
			$payload
		);
	}

	public static function invalidLongPollJson(): self {
		return new self( esc_html__( 'VK Long Poll returned an invalid JSON response.', 'cf7-vk' ) );
	}

	public static function apiRequestFailed( int $status_code, array $payload = [] ): self {
		return self::withPayload(
			sprintf(
				/* translators: %d: HTTP status code returned by the VK API */
				esc_html__( 'VK API request failed with HTTP status %d.', 'cf7-vk' ),
				$status_code
			),
			$status_code,
			$payload
		);
	}

	public static function invalidApiJson(): self {
		return new self( esc_html__( 'VK API returned an invalid JSON response.', 'cf7-vk' ) );
	}

	public static function fromApiError( array $error ): self {
		$message = $error['error_msg'] ?? esc_html__( 'VK API returned an error.', 'cf7-vk' );

		return self::withPayload(
			esc_html( (string) $message ),
			(int) ( $error['error_code'] ?? 0 ),
			$error
		);
	}

	public static function missingResponsePayload( array $payload ): self {
		return self::withPayload(
			esc_html__( 'VK API response payload is missing.', 'cf7-vk' ),
			0,
			$payload
		);
	}

	public function getPayload(): array {
		return $this->payload;
	}

	private static function withPayload( string $message, int $code, array $payload ): self {
		return new self( $message, $code, $payload );
	}
}
