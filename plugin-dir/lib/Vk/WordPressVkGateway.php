<?php

namespace iTRON\cf7Vk\Vk;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use Throwable;

class WordPressVkGateway implements VkGateway {
	private const API_URL = 'https://api.vk.com/method/';
	private const LONG_POLL_METHOD = 'long_poll';

	public function api( string $method, array $params, string $accessToken, string $apiVersion ): VkDeliveryResult {
		$method = ltrim( trim( $method ), '/' );
		$accessToken = trim( $accessToken );
		$apiVersion = trim( $apiVersion );
		$secrets = [
			'access_token' => $accessToken,
		];

		try {
			$response = wp_remote_post(
				self::API_URL . $method,
				[
					'timeout' => 15,
					'headers' => [
						'Accept' => 'application/json',
					],
					'body' => array_filter(
						array_merge(
							$params,
							[
								'access_token' => $accessToken,
								'v' => $apiVersion,
							]
						),
						static function ( $value ): bool {
							return null !== $value && '' !== $value;
						}
					),
				]
			);
		} catch ( Throwable $exception ) {
			return $this->failure(
				$method,
				0,
				0,
				$exception->getMessage(),
				null,
				VkDeliveryResult::ERROR_TRANSPORT,
				null,
				$secrets
			);
		}

		if ( is_wp_error( $response ) ) {
			return $this->failure(
				$method,
				0,
				0,
				$response->get_error_message(),
				null,
				VkDeliveryResult::ERROR_TRANSPORT,
				null,
				$secrets
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$retryAfter = $this->retryAfter( $response, is_array( $decoded ) ? $decoded : [] );

		if ( $status >= 400 ) {
			return $this->failure(
				$method,
				$status,
				$status,
				'VK API HTTP request failed.',
				$retryAfter,
				VkDeliveryResult::ERROR_HTTP,
				is_array( $decoded ) ? $decoded : [],
				$secrets
			);
		}

		if ( ! is_array( $decoded ) ) {
			return $this->failure(
				$method,
				$status,
				0,
				'VK API returned an invalid JSON response.',
				$retryAfter,
				VkDeliveryResult::ERROR_MALFORMED_RESPONSE,
				null,
				$secrets
			);
		}

		if ( isset( $decoded['error'] ) && is_array( $decoded['error'] ) ) {
			$error = $decoded['error'];

			return $this->failure(
				$method,
				$status,
				(int) ( $error['error_code'] ?? 0 ),
				(string) ( $error['error_msg'] ?? 'VK API returned an error.' ),
				$retryAfter,
				VkDeliveryResult::ERROR_VK_API,
				$error,
				$secrets
			);
		}

		if ( ! array_key_exists( 'response', $decoded ) ) {
			return $this->failure(
				$method,
				$status,
				0,
				'VK API response payload is missing.',
				$retryAfter,
				VkDeliveryResult::ERROR_MISSING_RESPONSE,
				$decoded,
				$secrets
			);
		}

		return VkDeliveryResult::success( $method, $decoded['response'], $status, $this->requestId( $decoded ) );
	}

	public function longPoll( string $server, string $key, string $ts, int $wait = 25 ): VkDeliveryResult {
		$requestedWait = $wait;
		$wait = min( 90, max( 1, $wait ) );
		$secrets = [
			'long_poll_key' => $key,
		];

		try {
			$response = wp_remote_get(
				add_query_arg(
					[
						'act' => 'a_check',
						'key' => $key,
						'ts' => $ts,
						'wait' => $wait,
					],
					$server
				),
				[
					'timeout' => min( 95, max( 10, $requestedWait + 10 ) ),
					'headers' => [
						'Accept' => 'application/json',
					],
				]
			);
		} catch ( Throwable $exception ) {
			return $this->failure(
				self::LONG_POLL_METHOD,
				0,
				0,
				$exception->getMessage(),
				null,
				VkDeliveryResult::ERROR_TRANSPORT,
				null,
				$secrets
			);
		}

		if ( is_wp_error( $response ) ) {
			return $this->failure(
				self::LONG_POLL_METHOD,
				0,
				0,
				$response->get_error_message(),
				null,
				VkDeliveryResult::ERROR_TRANSPORT,
				null,
				$secrets
			);
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( (string) wp_remote_retrieve_body( $response ), true );
		$retryAfter = $this->retryAfter( $response, is_array( $decoded ) ? $decoded : [] );

		if ( $status >= 400 ) {
			return $this->failure(
				self::LONG_POLL_METHOD,
				$status,
				$status,
				'VK Long Poll HTTP request failed.',
				$retryAfter,
				VkDeliveryResult::ERROR_HTTP,
				is_array( $decoded ) ? $decoded : [],
				$secrets
			);
		}

		if ( ! is_array( $decoded ) ) {
			return $this->failure(
				self::LONG_POLL_METHOD,
				$status,
				0,
				'VK Long Poll returned an invalid JSON response.',
				$retryAfter,
				VkDeliveryResult::ERROR_MALFORMED_RESPONSE,
				null,
				$secrets
			);
		}

		return VkDeliveryResult::success( self::LONG_POLL_METHOD, $decoded, $status, $this->requestId( $decoded ) );
	}

	private function failure(
		string $method,
		int $status,
		int $errorCode,
		string $description,
		?int $retryAfter,
		string $errorType,
		mixed $result,
		array $secrets
	): VkDeliveryResult {
		return VkDeliveryResult::failure(
			$method,
			$status,
			$errorCode,
			VkRedactor::text( $description, $secrets ),
			$retryAfter,
			$errorType,
			VkRedactor::data( $result, $secrets )
		);
	}

	private function retryAfter( array $response, array $body ): ?int {
		$retryAfter = function_exists( 'wp_remote_retrieve_header' )
			? wp_remote_retrieve_header( $response, 'retry-after' )
			: '';

		if ( is_numeric( $retryAfter ) ) {
			return (int) $retryAfter;
		}

		if ( isset( $body['retry_after'] ) && is_numeric( $body['retry_after'] ) ) {
			return (int) $body['retry_after'];
		}

		if ( isset( $body['error'] ) && is_array( $body['error'] ) && isset( $body['error']['retry_after'] ) && is_numeric( $body['error']['retry_after'] ) ) {
			return (int) $body['error']['retry_after'];
		}

		return null;
	}

	private function requestId( array $body ): string {
		if ( isset( $body['request_id'] ) && is_scalar( $body['request_id'] ) ) {
			return (string) $body['request_id'];
		}

		if ( isset( $body['error'] ) && is_array( $body['error'] ) && isset( $body['error']['request_id'] ) && is_scalar( $body['error']['request_id'] ) ) {
			return (string) $body['error']['request_id'];
		}

		return '';
	}
}
