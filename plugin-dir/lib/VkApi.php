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
			throw TransportNotConfigured::missingAccessToken();
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

		if (
			is_array( $response ) &&
			isset( $response['groups'] ) &&
			is_array( $response['groups'] ) &&
			isset( $response['groups'][0] ) &&
			is_array( $response['groups'][0] )
		) {
			return (array) $response['groups'][0];
		}

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
	 * @throws VkApiException
	 */
	public function checkLongPoll( string $server, string $key, string $ts, int $wait = 25 ): array {
		$response = wp_remote_get(
			add_query_arg(
				[
					'act' => 'a_check',
					'key' => $key,
					'ts' => $ts,
					'wait' => min( 90, max( 1, $wait ) ),
				],
				$server
			),
			[
				'timeout' => min( 95, max( 10, $wait + 10 ) ),
				'headers' => [
					'Accept' => 'application/json',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			$exception = VkApiException::fromWpError( $response );
			throw $exception;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$decoded = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $status_code >= 400 ) {
			$exception = VkApiException::longPollRequestFailed(
				$status_code,
				is_array( $decoded ) ? $decoded : []
			);
			throw $exception;
		}

		if ( ! is_array( $decoded ) ) {
			throw VkApiException::invalidLongPollJson();
		}

		return $decoded;
	}

	/**
	 * @throws VkApiException
	 */
	public function getConversationByMessage( string $peer_id, string $message_id ): array {
		$response = $this->call(
			'messages.getByConversationMessageId',
			[
				'peer_id' => $peer_id,
				'conversation_message_ids' => $message_id,
			]
		);

		if ( isset( $response['items'][0] ) && is_array( $response['items'][0] ) ) {
			return (array) $response['items'][0];
		}

		return [];
	}

	/**
	 * @throws VkApiException
	 */
	public function getUsers( array $user_ids ): array {
		$user_ids = array_values( array_filter( array_map( 'intval', $user_ids ) ) );

		if ( empty( $user_ids ) ) {
			return [];
		}

		$response = $this->call(
			'users.get',
			[
				'user_ids' => implode( ',', $user_ids ),
				'fields' => 'screen_name',
			]
		);

		return is_array( $response ) ? $response : [];
	}

	/**
	 * @throws TransportNotConfigured
	 * @throws VkApiException
	 */
	public function sendMessage( string $peer_id, string $message, array $options = [] ): int {
		$peer_id = trim( $peer_id );

		if ( '' === $peer_id ) {
			throw TransportNotConfigured::missingPeerId();
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
			$exception = VkApiException::fromWpError( $response );
			throw $exception;
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		$decoded = json_decode( $body, true );

		if ( $status_code >= 400 ) {
			$exception = VkApiException::apiRequestFailed(
				$status_code,
				is_array( $decoded ) ? $decoded : []
			);
			throw $exception;
		}

		if ( ! is_array( $decoded ) ) {
			throw VkApiException::invalidApiJson();
		}

		if ( isset( $decoded['error'] ) ) {
			$error = (array) $decoded['error'];

			$exception = VkApiException::fromApiError( $error );
			throw $exception;
		}

		if ( ! array_key_exists( 'response', $decoded ) ) {
			$exception = VkApiException::missingResponsePayload( $decoded );
			throw $exception;
		}

		return $decoded['response'];
	}

	private function isSequentialArray( array $value ): bool {
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
