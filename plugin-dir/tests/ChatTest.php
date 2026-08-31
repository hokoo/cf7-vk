<?php

declare( strict_types=1 );

use iTRON\cf7Vk\Chat;

final class ChatTest extends Cf7vk_TestCase {
	public function testDetectTypeByPeerIdMatchesVkPeerRanges(): void {
		$this->assertSame( Chat::TYPE_PRIVATE, Chat::detectTypeByPeerId( 1 ) );
		$this->assertSame( Chat::TYPE_PRIVATE, Chat::detectTypeByPeerId( 2000000000 ) );
		$this->assertSame( Chat::TYPE_CHAT, Chat::detectTypeByPeerId( 2000000001 ) );
		$this->assertSame( Chat::TYPE_COMMUNITY, Chat::detectTypeByPeerId( -1 ) );
	}
}
