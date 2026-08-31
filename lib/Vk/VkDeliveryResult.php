<?php

namespace iTRON\cf7Vk\Vk;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VkDeliveryResult implements \JsonSerializable {
	public const ERROR_TRANSPORT = 'transport';
	public const ERROR_HTTP = 'http';
	public const ERROR_VK_API = 'vk_api';
	public const ERROR_LONG_POLL = 'long_poll';
	public const ERROR_MALFORMED_RESPONSE = 'malformed_response';
	public const ERROR_MISSING_RESPONSE = 'missing_response';

	public bool $ok;
	public string $method;
	public int $status;
	public int $errorCode;
	public string $description;
	public ?int $retryAfter;
	public string $errorType;
	public mixed $result;
	public string $requestId;

	public function __construct(
		bool $ok,
		string $method,
		int $status = 0,
		int $errorCode = 0,
		string $description = '',
		?int $retryAfter = null,
		string $errorType = '',
		mixed $result = null,
		string $requestId = ''
	) {
		$this->ok = $ok;
		$this->method = $method;
		$this->status = $status;
		$this->errorCode = $errorCode;
		$this->description = VkRedactor::text( $description );
		$this->retryAfter = $retryAfter;
		$this->errorType = $errorType;
		$this->result = $result;
		$this->requestId = $requestId;
	}

	public static function success( string $method, mixed $result = null, int $status = 200, string $requestId = '' ): self {
		return new self( true, $method, $status, 0, '', null, '', $result, $requestId );
	}

	public static function failure(
		string $method,
		int $status,
		int $errorCode,
		string $description,
		?int $retryAfter = null,
		string $errorType = self::ERROR_VK_API,
		mixed $result = null,
		string $requestId = ''
	): self {
		return new self( false, $method, $status, $errorCode, $description, $retryAfter, $errorType, $result, $requestId );
	}

	public function jsonSerialize(): array {
		return [
			'ok'          => $this->ok,
			'method'      => $this->method,
			'status'      => $this->status,
			'errorCode'   => $this->errorCode,
			'description' => $this->description,
			'retryAfter'  => $this->retryAfter,
			'errorType'   => $this->errorType,
			'result'      => $this->result,
			'requestId'   => $this->requestId,
		];
	}

	public function toArray(): array {
		return $this->jsonSerialize();
	}
}
