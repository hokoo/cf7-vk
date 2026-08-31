<?php

declare( strict_types=1 );

use iTRON\cf7Vk\Util;

final class UtilTest extends Cf7vk_TestCase {
	public function testVersionToIntOrdersPrereleasesBeforeStable(): void {
		$this->assertGreaterThan( Util::versionToInt( '1.2.3-beta2' ), Util::versionToInt( '1.2.3-rc1' ) );
		$this->assertGreaterThan( Util::versionToInt( '1.2.3-rc1' ), Util::versionToInt( '1.2.3' ) );
		$this->assertGreaterThan( Util::versionToInt( '1.2.3' ), Util::versionToInt( '1.2.4-dev1' ) );
	}

	public function testVersionToIntRejectsMalformedVersion(): void {
		try {
			Util::versionToInt( 'bad.version' );
			$this->fail( 'Expected invalid version exception.' );
		} catch ( InvalidArgumentException $exception ) {
			$this->assertStringContainsString( 'Invalid version', $exception->getMessage() );
		}
	}

	public function testResolveVkChatTitlePrefersConversationThenProfileThenPeerFallback(): void {
		$this->assertSame(
			'Support chat',
			Util::resolveVkChatTitle(
				[ 'peer_id' => 2000000001 ],
				[],
				[ 'chat_settings' => [ 'title' => 'Support chat' ] ]
			)
		);

		$this->assertSame(
			'Alice Smith',
			Util::resolveVkChatTitle(
				[ 'peer_id' => 123, 'from_id' => 123 ],
				[ 'first_name' => 'Alice', 'last_name' => 'Smith' ]
			)
		);

		$this->assertSame( 'VK user 123', Util::resolveVkChatTitle( [ 'peer_id' => 123, 'from_id' => 123 ] ) );
		$this->assertSame( 'VK chat 2000000001', Util::resolveVkChatTitle( [ 'peer_id' => 2000000001 ] ) );
	}

	public function testNormalizeVkTimestampAcceptsUnixTimestampAndExistingString(): void {
		$this->assertSame( '2023-11-14T22:13:20+00:00', Util::normalizeVkTimestamp( 1700000000 ) );
		$this->assertSame( 'already-normalized', Util::normalizeVkTimestamp( 'already-normalized' ) );
	}
}
