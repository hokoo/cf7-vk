<?php

if ( ! function_exists( 'dbDelta' ) ) {
	function dbDelta( $queries ): array {
		global $wpdb;

		foreach ( (array) $queries as $query ) {
			$wpdb->query( (string) $query );
		}

		return [];
	}
}
