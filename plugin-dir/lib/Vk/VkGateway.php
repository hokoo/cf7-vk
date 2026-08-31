<?php

namespace iTRON\cf7Vk\Vk;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface VkGateway {
	public function api( string $method, array $params, string $accessToken, string $apiVersion ): VkDeliveryResult;

	public function longPoll( string $server, string $key, string $ts, int $wait = 25 ): VkDeliveryResult;
}
