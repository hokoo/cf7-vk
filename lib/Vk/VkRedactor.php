<?php

namespace iTRON\cf7Vk\Vk;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class VkRedactor {
	public const ACCESS_TOKEN_MARKER = '[vk-access-token]';
	public const LONG_POLL_KEY_MARKER = '[vk-long-poll-key]';
	public const GENERIC_MARKER = '[vk-secret]';

	private const SENSITIVE_KEYS = [
		'access_token',
		'accesstoken',
		'authorization',
		'long_poll_key',
		'longpollkey',
		'secret',
		'token',
	];

	public static function text( string $text, array $secrets = [] ): string {
		foreach ( self::secretVariants( $secrets ) as $variant => $marker ) {
			$text = str_replace( $variant, $marker, $text );
		}

		$text = preg_replace_callback(
			'/((?:access_token|token|authorization|key)(?:=|%3d))([^&\s"\'<>]+)/i',
			static function ( array $matches ): string {
				return str_starts_with( $matches[2], '[vk-' )
					? $matches[0]
					: $matches[1] . self::GENERIC_MARKER;
			},
			$text
		) ?? $text;

		return preg_replace(
			'/(?<![A-Za-z0-9._-])vk1\.[A-Za-z0-9._-]{20,}(?![A-Za-z0-9._-])/i',
			self::ACCESS_TOKEN_MARKER,
			$text
		) ?? $text;
	}

	public static function data( mixed $data, array $secrets = [] ): mixed {
		if ( is_string( $data ) ) {
			return self::text( $data, $secrets );
		}

		if ( is_array( $data ) ) {
			$redacted = [];

			foreach ( $data as $key => $value ) {
				$redacted[ $key ] = self::isSensitiveKey( $key )
					? self::markerForKey( (string) $key )
					: self::data( $value, $secrets );
			}

			return $redacted;
		}

		if ( is_object( $data ) ) {
			$redacted = clone $data;

			foreach ( get_object_vars( $data ) as $key => $value ) {
				$redacted->$key = self::isSensitiveKey( $key )
					? self::markerForKey( (string) $key )
					: self::data( $value, $secrets );
			}

			return $redacted;
		}

		return $data;
	}

	private static function secretVariants( array $secrets ): array {
		$variants = [];

		foreach ( $secrets as $key => $secret ) {
			$secret = trim( (string) $secret );

			if ( '' === $secret ) {
				continue;
			}

			$marker = self::markerForKey( (string) $key );
			$encoded = $secret;
			$variants[ $secret ] = $marker;

			for ( $depth = 0; $depth < 3; $depth++ ) {
				$encoded = rawurlencode( $encoded );
				$variants[ $encoded ] = $marker;
			}
		}

		return $variants;
	}

	private static function isSensitiveKey( mixed $key ): bool {
		if ( ! is_string( $key ) && ! is_int( $key ) ) {
			return false;
		}

		$normalized = strtolower( preg_replace( '/[^a-z0-9_]/i', '', (string) $key ) ?? '' );

		return in_array( $normalized, self::SENSITIVE_KEYS, true );
	}

	private static function markerForKey( string $key ): string {
		$normalized = strtolower( preg_replace( '/[^a-z0-9_]/i', '', $key ) ?? '' );
		$compact = str_replace( '_', '', $normalized );

		if ( str_contains( $compact, 'access' ) || 'token' === $compact ) {
			return self::ACCESS_TOKEN_MARKER;
		}

		if ( str_contains( $compact, 'longpoll' ) || 'key' === $compact ) {
			return self::LONG_POLL_KEY_MARKER;
		}

		return self::GENERIC_MARKER;
	}
}
