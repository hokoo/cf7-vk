<?php

namespace iTRON\cf7Vk\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TransportNotConfigured extends Exception {
	public static function missingAccessToken(): self {
		return new self( esc_html__( 'VK access token is not configured.', 'cf7-vk' ) );
	}

	public static function missingPeerId(): self {
		return new self( esc_html__( 'VK peer ID is not configured.', 'cf7-vk' ) );
	}

	public static function missingGroupId(): self {
		return new self( esc_html__( 'VK group ID is not configured.', 'cf7-vk' ) );
	}

	public static function invalidGroupId(): self {
		return new self( esc_html__( 'VK group ID must be a numeric value.', 'cf7-vk' ) );
	}
}
