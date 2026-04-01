<?php

namespace iTRON\cf7Vk\Controllers\RestApi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Response;

class BotController extends Controller {
	public function prepare_item_for_response( $post, $request ): WP_REST_Response {
		$response = parent::prepare_item_for_response( $post, $request );
		$base = sprintf( '%s/%s', $this->namespace, $this->rest_base );

		$response->add_link(
			'settings',
			rest_url( trailingslashit( $base ) . $post->ID )
		);

		return $response;
	}
}
