<?php

namespace iTRON\cf7Vk;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use iTRON\cf7Vk\Exceptions\TransportNotConfigured;
use iTRON\cf7Vk\Exceptions\VkApiException;
use iTRON\cf7Vk\Vk\VkDeliveryResult;
use iTRON\cf7Vk\Vk\VkGateway;
use iTRON\cf7Vk\Vk\WordPressVkGateway;

class VkApi {
	private string $accessToken;
	private string $apiVersion;
	private VkGateway $gateway;

	/**
	 * @throws TransportNotConfigured
	 */
	public function __construct( string $access_token, string $api_version = Bot::DEFAULT_API_VERSION, ?VkGateway $gateway = null ) {
		$access_token = trim( $access_token );

		if ( '' === $access_token ) {
			throw TransportNotConfigured::missingAccessToken();
		}

		$this->accessToken = $access_token;
		$this->apiVersion = trim( $api_version ) ?: Bot::DEFAULT_API_VERSION;
		$this->gateway = $gateway ?? $this->createGateway();
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
		$result = $this->gateway->longPoll( $server, $key, $ts, $wait );

		return $this->unwrapLongPollResult( $result );
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
		$result = $this->gateway->api( $method, $params, $this->accessToken, $this->apiVersion );

		return $this->unwrapApiResult( $result );
	}

	private function createGateway(): VkGateway {
		$gateway = new WordPressVkGateway();
		$filteredGateway = apply_filters( 'cf7vk_vk_gateway', $gateway, $this->accessToken, $this->apiVersion, $this );

		return $filteredGateway instanceof VkGateway ? $filteredGateway : $gateway;
	}

	/**
	 * @throws VkApiException
	 */
	private function unwrapApiResult( VkDeliveryResult $result ) {
		if ( $result->ok ) {
			return $result->result;
		}

		if ( VkDeliveryResult::ERROR_TRANSPORT === $result->errorType ) {
			throw VkApiException::fromWpError( new \WP_Error( 'cf7vk_vk_transport_error', $result->description ) );
		}

		if ( VkDeliveryResult::ERROR_HTTP === $result->errorType ) {
			throw VkApiException::apiRequestFailed( $result->status, $this->resultPayload( $result ) );
		}

		if ( VkDeliveryResult::ERROR_VK_API === $result->errorType ) {
			$error = $this->resultPayload( $result );
			$error['error_code'] = (int) ( $error['error_code'] ?? $result->errorCode );
			$error['error_msg'] = (string) ( $error['error_msg'] ?? $result->description );

			throw VkApiException::fromApiError( $error );
		}

		if ( VkDeliveryResult::ERROR_MISSING_RESPONSE === $result->errorType ) {
			throw VkApiException::missingResponsePayload( $this->resultPayload( $result ) );
		}

		throw VkApiException::invalidApiJson();
	}

	/**
	 * @throws VkApiException
	 */
	private function unwrapLongPollResult( VkDeliveryResult $result ): array {
		if ( $result->ok ) {
			return is_array( $result->result ) ? $result->result : [];
		}

		if ( VkDeliveryResult::ERROR_TRANSPORT === $result->errorType ) {
			throw VkApiException::fromWpError( new \WP_Error( 'cf7vk_vk_transport_error', $result->description ) );
		}

		if ( VkDeliveryResult::ERROR_HTTP === $result->errorType ) {
			throw VkApiException::longPollRequestFailed( $result->status, $this->resultPayload( $result ) );
		}

		throw VkApiException::invalidLongPollJson();
	}

	private function resultPayload( VkDeliveryResult $result ): array {
		return is_array( $result->result ) ? $result->result : [];
	}

	private function isSequentialArray( array $value ): bool {
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}
}
