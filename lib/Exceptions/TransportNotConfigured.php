<?php

namespace iTRON\cf7Vk\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TransportNotConfigured extends Exception {
	public static function missingAccessToken(): self {
		return new self( esc_html__( 'VK access token is not configured.', 'vk-notifications-for-contact-form-7' ) );
	}

	public static function missingPeerId(): self {
		return new self( esc_html__( 'VK peer ID is not configured.', 'vk-notifications-for-contact-form-7' ) );
	}

	public static function missingGroupId(): self {
		return new self( esc_html__( 'VK group ID is not configured.', 'vk-notifications-for-contact-form-7' ) );
	}

	public static function invalidGroupId(): self {
		return new self( esc_html__( 'VK group ID must be a numeric value.', 'vk-notifications-for-contact-form-7' ) );
	}
}
