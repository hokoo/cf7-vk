<?php

declare( strict_types=1 );

use iTRON\cf7Vk\Logger;
use iTRON\cf7Vk\LogRedactor;

final class LoggerTest extends Cf7vk_TestCase {
	public function testLoggerRedactsNestedArraysBeforeWriteAndIntegrationHook(): void {
		$token = 'vk1.' . str_repeat( 'a', 30 );
		$hookRow = null;
		add_action(
			'logger',
			static function ( array $row ) use ( &$hookRow ): void {
				$hookRow = $row;
			}
		);

		( new Logger() )->write(
			[
				'accessToken' => $token,
				'nested'      => [
					'chatPeerId' => '2000000001',
					'safeId'     => 42,
					'message'    => 'Contact jane@example.test or +1 415 555 0134.',
				],
			],
			'Failed token=' . $token,
			Logger::LEVEL_WARNING
		);

		$row = $GLOBALS['wp_log_rows'][0];
		$this->assertStringNotContainsString( $token, $row->data );
		$this->assertStringNotContainsString( 'jane@example.test', $row->data );
		$this->assertStringNotContainsString( '+1 415 555 0134', $row->data );
		$this->assertStringNotContainsString( '2000000001', $row->data );
		$this->assertStringContainsString( LogRedactor::REDACTION_MARKER, $row->data );
		$this->assertStringContainsString( '"safeId":42', $row->data );
		$this->assertStringNotContainsString( $token, $row->msg );
		$this->assertStringNotContainsString( $token, $hookRow['msg'] );
		$this->assertStringNotContainsString( $token, $hookRow['data'] );
	}

	public function testLogRedactorRedactsObjectsAndJsonLikeStrings(): void {
		$token = 'vk1.' . str_repeat( 'b', 30 );
		$object = (object) [
			'longPollKey' => 'secret-long-poll-key',
			'child'       => (object) [
				'email' => 'person@example.test',
			],
		];

		$redactedObject = LogRedactor::redact( $object );
		$redactedString = LogRedactor::redactString(
			'{"accessToken":"' . $token . '","phone":"+995 555 123456","email":"person@example.test"}'
		);

		$this->assertSame( LogRedactor::REDACTION_MARKER, $redactedObject->longPollKey );
		$this->assertSame( LogRedactor::REDACTION_MARKER, $redactedObject->child->email );
		$this->assertStringNotContainsString( $token, $redactedString );
		$this->assertStringNotContainsString( '+995 555 123456', $redactedString );
		$this->assertStringNotContainsString( 'person@example.test', $redactedString );
	}

	public function testLogRedactorRedactsUrlEncodedVkSecretsAndLongPollKeys(): void {
		$token = 'vk1.' . str_repeat( 'c', 30 );
		$key = 'very-secret-long-poll-key';
		$url = 'https://lp.vk.test/server?act=a_check&key=' . rawurlencode( $key ) . '&ts=10';
		$encodedToken = 'access_token%3D' . rawurlencode( $token );
		$redacted = LogRedactor::redactString( $url . ' ' . $encodedToken );

		$this->assertStringNotContainsString( $key, $redacted );
		$this->assertStringNotContainsString( rawurlencode( $key ), $redacted );
		$this->assertStringNotContainsString( $token, $redacted );
		$this->assertStringNotContainsString( rawurlencode( $token ), $redacted );
		$this->assertStringContainsString( LogRedactor::REDACTION_MARKER, $redacted );
	}

	public function testCustomSensitiveKeyAndPatternFilters(): void {
		add_filter( 'cf7vk/logSensitiveKeys', static fn(): array => [ 'customerReference' ] );
		add_filter( 'cf7vk/logRedactionPatterns', static fn(): array => [ '/CUSTOM-[0-9]+/' ] );

		$redactedData = LogRedactor::redact(
			[
				'nested' => [
					'customerReference' => 'ABC-1234',
					'note'              => 'Order CUSTOM-42 failed.',
				],
			]
		);

		$this->assertSame( LogRedactor::REDACTION_MARKER, $redactedData['nested']['customerReference'] );
		$this->assertSame( 'Order [redacted] failed.', $redactedData['nested']['note'] );
	}

	public function testPlainDiagnosticIdsAreNotRedactedWithoutSensitiveKeys(): void {
		$this->assertSame(
			'Connection 123456789 stayed attached.',
			LogRedactor::redactString( 'Connection 123456789 stayed attached.' )
		);
	}

	public function testWpErrorLongPollUrlStoredWithoutKey(): void {
		$key = 'very-secret-long-poll-key';
		$error = new WP_Error(
			'http_request_failed',
			'Could not connect to https://lp.vk.test/server?act=a_check&key=' . rawurlencode( $key ) . '&ts=10'
		);

		( new Logger() )->write( $error->get_error_message(), 'VK Long Poll request failed.', Logger::LEVEL_WARNING );

		$row = $GLOBALS['wp_log_rows'][0];
		$this->assertStringNotContainsString( $key, $row->data );
		$this->assertStringNotContainsString( rawurlencode( $key ), $row->data );
		$this->assertStringContainsString( LogRedactor::REDACTION_MARKER, $row->data );
	}
}
