<?php

declare( strict_types=1 );

use iTRON\cf7Vk\Vk\VkDeliveryResult;
use iTRON\cf7Vk\Vk\VkGateway;

final class Cf7vk_RecordingVkGateway implements VkGateway {
	public array $calls = [];
	private array $queues = [];

	public function queue( string $method, VkDeliveryResult $result ): void {
		$this->queues[ $method ][] = $result;
	}

	public function api( string $method, array $params, string $accessToken, string $apiVersion ): VkDeliveryResult {
		$this->calls[] = [
			'type'         => 'api',
			'method'       => $method,
			'params'       => $params,
			'accessToken'  => $accessToken,
			'apiVersion'   => $apiVersion,
		];

		return $this->next( $method, VkDeliveryResult::success( $method, true ) );
	}

	public function longPoll( string $server, string $key, string $ts, int $wait = 25 ): VkDeliveryResult {
		$this->calls[] = [
			'type'   => 'long_poll',
			'method' => 'long_poll',
			'server' => $server,
			'key'    => $key,
			'ts'     => $ts,
			'wait'   => $wait,
		];

		return $this->next( 'long_poll', VkDeliveryResult::success( 'long_poll', [ 'ts' => $ts, 'updates' => [] ] ) );
	}

	private function next( string $method, VkDeliveryResult $default ): VkDeliveryResult {
		return array_shift( $this->queues[ $method ] ) ?? $default;
	}
}
