<?php

namespace iTRON\cf7Vk;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class LogRedactor {
	public const REDACTION_MARKER = '[redacted]';

	private const DEFAULT_SENSITIVE_KEYS = [
		'token',
		'accessToken',
		'authorization',
		'password',
		'secret',
		'key',
		'longPollKey',
		'peerId',
		'chatPeerId',
		'userId',
		'email',
		'phone',
	];

	public static function redact( mixed $value ): mixed {
		if ( is_array( $value ) ) {
			$redacted = [];

			foreach ( $value as $key => $item ) {
				$redacted[ $key ] = self::isSensitiveKey( $key )
					? self::REDACTION_MARKER
					: self::redact( $item );
			}

			return $redacted;
		}

		if ( is_object( $value ) ) {
			$redacted = clone $value;

			foreach ( get_object_vars( $value ) as $key => $item ) {
				$redacted->$key = self::isSensitiveKey( $key )
					? self::REDACTION_MARKER
					: self::redact( $item );
			}

			return $redacted;
		}

		if ( is_string( $value ) ) {
			return self::redactString( $value );
		}

		return $value;
	}

	public static function redactString( string $value ): string {
		$value = self::redactVkTokens( $value );
		$value = self::redactKeyValueSecrets( $value );
		$value = self::redactEncodedKeyValueSecrets( $value );
		$value = preg_replace(
			'/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i',
			self::REDACTION_MARKER,
			$value
		) ?? $value;
		$value = preg_replace(
			'/(?<![\w])(?:\+\d[\d\s().-]{6,}\d|\d{3}[\s().-]\d{3}[\s().-]\d{4})(?![\w])/',
			self::REDACTION_MARKER,
			$value
		) ?? $value;

		$extraPatterns = apply_filters( 'cf7vk/logRedactionPatterns', [] );
		if ( is_array( $extraPatterns ) ) {
			foreach ( $extraPatterns as $pattern ) {
				if ( is_string( $pattern ) && '' !== $pattern ) {
					$value = preg_replace( $pattern, self::REDACTION_MARKER, $value ) ?? $value;
				}
			}
		}

		return $value;
	}

	private static function redactVkTokens( string $value ): string {
		$value = preg_replace(
			'/(?<![A-Za-z0-9._-])vk1\.[A-Za-z0-9._-]{20,}(?![A-Za-z0-9._-])/i',
			self::REDACTION_MARKER,
			$value
		) ?? $value;

		return preg_replace(
			'/(?<![A-Za-z0-9._%-])vk1%(?:25)*2e[A-Za-z0-9._%\-]{20,}(?![A-Za-z0-9._%-])/i',
			self::REDACTION_MARKER,
			$value
		) ?? $value;
	}

	private static function redactKeyValueSecrets( string $value ): string {
		$keyPattern = self::sensitiveKeyPattern();

		return preg_replace(
			'/((?:["\']?\b' . $keyPattern . '\b["\']?)\s*[:=]\s*["\']?)(?:Bearer\s+)?[^"\'\s,;&}]+/i',
			'$1' . self::REDACTION_MARKER,
			$value
		) ?? $value;
	}

	private static function redactEncodedKeyValueSecrets( string $value ): string {
		$keyPattern = self::sensitiveKeyPattern();

		return preg_replace(
			'/((?:' . $keyPattern . ')%(?:25)*3[ad])[^%&\s"\'<>]+/i',
			'$1' . self::REDACTION_MARKER,
			$value
		) ?? $value;
	}

	private static function sensitiveKeyPattern(): string {
		return 'access[_\-\s]?token|accesstoken|authorization|password|secret|token|long[_\-\s]?poll[_\-\s]?key|longpollkey|chat[_\-\s]?peer[_\-\s]?id|chatpeerid|peer[_\-\s]?id|peerid|user[_\-\s]?id|userid|email|phone|key';
	}

	private static function isSensitiveKey( mixed $key ): bool {
		if ( ! is_string( $key ) && ! is_int( $key ) ) {
			return false;
		}

		$normalizedKey = self::normalizeKey( (string) $key );
		if ( '' === $normalizedKey ) {
			return false;
		}

		$sensitiveKeys = apply_filters( 'cf7vk/logSensitiveKeys', [] );
		if ( ! is_array( $sensitiveKeys ) ) {
			$sensitiveKeys = [];
		}

		foreach ( array_merge( self::DEFAULT_SENSITIVE_KEYS, $sensitiveKeys ) as $sensitiveKey ) {
			$needle = self::normalizeKey( (string) $sensitiveKey );
			if ( '' === $needle ) {
				continue;
			}

			if ( 'key' === $needle ) {
				if ( 'key' === $normalizedKey ) {
					return true;
				}

				continue;
			}

			if ( str_contains( $normalizedKey, $needle ) ) {
				return true;
			}
		}

		return false;
	}

	private static function normalizeKey( string $key ): string {
		return strtolower( preg_replace( '/[^a-z0-9]/i', '', $key ) ?? '' );
	}
}
