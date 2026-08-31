<?php

declare( strict_types=1 );

use iTRON\cf7Vk\Bot;

final class BotTest extends Cf7vk_TestCase {
	public function testMaskedSecretDetectionOnlyAcceptsBracketMask(): void {
		$this->assertTrue( Bot::isMaskedSecretValue( '[empty]' ) );
		$this->assertTrue( Bot::isMaskedSecretValue( ' [defined by PHP constant] ' ) );
		$this->assertFalse( Bot::isMaskedSecretValue( '' ) );
		$this->assertFalse( Bot::isMaskedSecretValue( null ) );
		$this->assertFalse( Bot::isMaskedSecretValue( 'vk1.a.raw-token' ) );
		$this->assertFalse( Bot::isMaskedSecretValue( '[unterminated' ) );
	}

	public function testDefaultVkRuntimeValuesAreStable(): void {
		$this->assertSame( '5.199', Bot::DEFAULT_API_VERSION );
		$this->assertSame( 'start', Bot::DEFAULT_AUTH_COMMAND );
		$this->assertSame( 25, Bot::LONG_POLL_WAIT );
		$this->assertSame( 'CF7VK_ACCESS_TOKEN__123', sprintf( Bot::ACCESS_TOKEN_CONST_MASK, 123 ) );
		$this->assertSame( 'CF7VK_GROUP_ID__123', sprintf( Bot::GROUP_ID_CONST_MASK, 123 ) );
	}
}
