<?php

namespace iTRON\cf7Vk\Controllers\RestApi;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use WP_REST_Posts_Controller;

abstract class Controller extends WP_REST_Posts_Controller {
	public function check_read_permission( $post ): bool {
		$post_type = get_post_type_object( $post->post_type );

		return 'publish' === $post->post_status && current_user_can( $post_type->cap->read_post, $post->ID );
	}

	public function get_items_permissions_check( $request ): bool {
		$post_type = get_post_type_object( $this->post_type );

		return current_user_can( $post_type->cap->read_post );
	}
}
