<?php

namespace iTRON\cf7Vk;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use iTRON\cf7Vk\Exceptions\TransportNotConfigured;
use iTRON\cf7Vk\Exceptions\VkApiException;

class VkApi {
	private const API_URL = 'https://api.vk.com/method/';
	private string $accessToken;
	private string $apiVersion;

	/**
	 * @throws TransportNotConfigured
	 */
	public function __construct( string $access_token, string $api_version = Bot::DEFAULT_API_VERSION ) {
		$access_token = trim( $access_token );

		if ( '' === $access_token ) {
			throw new TransportNotConfigured( __( 'VK access token is not configured.', 'cf7-vk' ) );
		}

		$this->accessToken = $access_token;
		$this->apiVersion = trim( $api_version ) ?: Bot::DEFAULT_API_VERSION;
	}

	/**
	 * @throws VkApiException
	 */
	public function getCommunity( string $group_id ): array {
		$response = $this->call(
			'groups.getById',
			[
				'group_id' => $group_id,
				'fields' => 'description,screen_name',
			]
		);

		if ( is_array( $response ) && $this->isSequentialArray( $response ) ) {
			return (array) reset( $response );
		}

		return (array) $response;
	}

	/**
	 * @throws VkApiException
	 */
	public function getLongPollServer( string $group_id ): array {
		return (array) $this->call(
			'groups.getLongPollServer',
			[
				'group_id' => $group_id,
			]
		);
	}

	/**
	 * @throws TransportNotConfigured
	 * @throws VkApiException
	 */
	public function sendMessage( string $peer_id, string $message, array $options = [] ): int {
		$peer_id = trim( $peer_id );

		if ( '' === $peer_id ) {
			throw new TransportNotConfigured( __( 'VK peer ID is not configured.', 'cf7-vk' ) );
		}

		$response = $this->call(
			'messages.send',
			array_merge(
				[
					'peer_id' => $peer_id,
					'message' => $message,
					'random_id' => random_int( 1, 2147483647 ),
				],
				$options
			)
		);

		return (int) $response;
	}

	/**
	 * @throws VkApiException
	 */
	private function call( string $method, array $params = [] ) {
		$response = wp_remote_post(
			self::API_URL . ltrim( $method, '/' ),
			[
				'timeout' => 15,
				'headers' => [
					'Accept' => 'application/json',
				],
				'body' => array_filter(
					array_merge(
						$params,
						[
							'access_token' => $this->accessToken,
							'v' => $this->apiVersion,
						]
					),
					static function ( $value ): bool {
						return null !== $value && '' !== $value;
					}
				),
			]
		);

		if ( is_wp_error( $response ) ) {
			throw new VkApiException( $response->get_error_message() );
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );

		if ( $status_code >= 400 ) {
			throw new VkApiException(
				sprintf( 'VK API request failed with HTTP status %d.', $status_code ),
				$status_code,
				is_array( $decoded ) ? $decoded : []
			);
		}

		if ( ! is_array( $decoded ) ) {
			throw new VkApiException( __( 'VK API returned an invalid JSON response.', 'cf7-vk' ) );
		}

		if ( isset( $decoded['error'] ) ) {
			$error = (array) $decoded['error'];

			throw new VkApiException(
				(string) ( $error['error_msg'] ?? __( 'VK API returned an error.', 'cf7-vk' ) ),
				(int) ( $error['error_code'] ?? 0 ),
				$error
			);
		}

		if ( ! array_key_exists( 'response', $decoded ) ) {
			throw new VkApiException( __( 'VK API response payload is missing.', 'cf7-vk' ), 0, $decoded );
		}

		return $decoded['response'];
	}

	private function isSequentialArray( array $value ): bool {
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
