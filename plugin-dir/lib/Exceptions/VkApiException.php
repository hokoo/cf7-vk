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

	public function getPayload(): array {
		return $this->payload;
	}
}
