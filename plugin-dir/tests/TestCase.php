<?php

declare( strict_types=1 );

class Cf7vk_AssertionFailed extends Exception {
}

class Cf7vk_TestSkipped extends Exception {
}

if ( class_exists( '\PHPUnit\Framework\TestCase' ) ) {
	abstract class Cf7vk_TestCase extends \PHPUnit\Framework\TestCase {
		protected function setUp(): void {
			cf7vk_test_reset_environment();
		}

		protected function requiresPhp81Runtime(): void {
			if ( PHP_VERSION_ID < 80100 ) {
				$this->markTestSkipped( 'Requires PHP 8.1 runtime because project dependencies use PHP 8.1 syntax.' );
			}
		}

		protected function cronEvents( string $hook ): array {
			return cf7vk_test_cron_events( $hook );
		}

		protected function countCronEventsBySchedule( string $hook, $schedule ): int {
			return count(
				array_filter(
					$this->cronEvents( $hook ),
					static fn( array $event ): bool => $schedule === ( $event['schedule'] ?? null )
				)
			);
		}
	}
} else {
	abstract class Cf7vk_TestCase {
		protected function setUp(): void {
			cf7vk_test_reset_environment();
		}

		protected function tearDown(): void {
		}

		protected function requiresPhp81Runtime(): void {
			if ( PHP_VERSION_ID < 80100 ) {
				$this->markTestSkipped( 'Requires PHP 8.1 runtime because project dependencies use PHP 8.1 syntax.' );
			}
		}

		protected function markTestSkipped( string $message = '' ): void {
			throw new Cf7vk_TestSkipped( $message ?: 'Test skipped.' );
		}

		protected function fail( string $message = '' ): void {
			throw new Cf7vk_AssertionFailed( $message ?: 'Test failed.' );
		}

		protected function assertTrue( $actual, string $message = '' ): void {
			if ( true !== $actual ) {
				$this->fail( $message ?: 'Failed asserting that value is true.' );
			}
		}

		protected function assertFalse( $actual, string $message = '' ): void {
			if ( false !== $actual ) {
				$this->fail( $message ?: 'Failed asserting that value is false.' );
			}
		}

		protected function assertSame( $expected, $actual, string $message = '' ): void {
			if ( $expected !== $actual ) {
				$this->fail(
					$message ?: sprintf(
						'Failed asserting that %s is identical to %s.',
						var_export( $actual, true ),
						var_export( $expected, true )
					)
				);
			}
		}

		protected function assertNotSame( $expected, $actual, string $message = '' ): void {
			if ( $expected === $actual ) {
				$this->fail(
					$message ?: sprintf(
						'Failed asserting that %s is not identical to %s.',
						var_export( $actual, true ),
						var_export( $expected, true )
					)
				);
			}
		}

		protected function assertArrayHasKey( $key, array $array, string $message = '' ): void {
			if ( ! array_key_exists( $key, $array ) ) {
				$this->fail( $message ?: sprintf( 'Failed asserting that array has key %s.', (string) $key ) );
			}
		}

		protected function assertArrayNotHasKey( $key, array $array, string $message = '' ): void {
			if ( array_key_exists( $key, $array ) ) {
				$this->fail( $message ?: sprintf( 'Failed asserting that array does not have key %s.', (string) $key ) );
			}
		}

		protected function assertGreaterThan( $expected, $actual, string $message = '' ): void {
			if ( ! ( $actual > $expected ) ) {
				$this->fail(
					$message ?: sprintf(
						'Failed asserting that %s is greater than %s.',
						var_export( $actual, true ),
						var_export( $expected, true )
					)
				);
			}
		}

		protected function assertStringContainsString( string $needle, string $haystack, string $message = '' ): void {
			if ( false === strpos( $haystack, $needle ) ) {
				$this->fail( $message ?: sprintf( 'Failed asserting that string contains "%s".', $needle ) );
			}
		}

		protected function assertStringNotContainsString( string $needle, string $haystack, string $message = '' ): void {
			if ( false !== strpos( $haystack, $needle ) ) {
				$this->fail( $message ?: sprintf( 'Failed asserting that string does not contain "%s".', $needle ) );
			}
		}

		protected function assertInstanceOf( string $expected, $actual, string $message = '' ): void {
			if ( ! $actual instanceof $expected ) {
				$this->fail( $message ?: sprintf( 'Failed asserting that value is an instance of %s.', $expected ) );
			}
		}

		protected function cronEvents( string $hook ): array {
			return cf7vk_test_cron_events( $hook );
		}

		protected function countCronEventsBySchedule( string $hook, $schedule ): int {
			return count(
				array_filter(
					$this->cronEvents( $hook ),
					static fn( array $event ): bool => $schedule === ( $event['schedule'] ?? null )
				)
			);
		}
	}
}
