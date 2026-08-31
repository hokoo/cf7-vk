<?php

declare( strict_types=1 );

use iTRON\cf7Vk\Client;

final class ClientTest extends Cf7vk_TestCase {
	public function testGetInstanceReturnsSingleton(): void {
		$this->assertSame( Client::getInstance(), Client::getInstance() );
	}

	public function testGetChannelsQueriesAllChannelPosts(): void {
		$this->requiresPhp81Runtime();

		$client = ( new ReflectionClass( Client::class ) )->newInstanceWithoutConstructor();
		$GLOBALS['wp_query_posts'][ Client::CPT_CHANNEL ] = [];

		$client->getChannels();

		$this->assertSame( Client::CPT_CHANNEL, WP_Query::$last_args['post_type'] );
		$this->assertSame( 'ids', WP_Query::$last_args['fields'] );
		$this->assertSame( -1, WP_Query::$last_args['posts_per_page'] );
		$this->assertArrayNotHasKey( 'posts_per_pge', WP_Query::$last_args );
	}
}
