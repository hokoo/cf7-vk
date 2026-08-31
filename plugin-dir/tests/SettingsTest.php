<?php

declare( strict_types=1 );

use iTRON\cf7Vk\Controllers\Migration;
use iTRON\cf7Vk\Settings;

final class SettingsTest extends Cf7vk_TestCase {
	public function testRenderPageUsesStableAdminWrapperWithoutDuplicateReactRoot(): void {
		ob_start();
		Settings::renderPage();
		$output = (string) ob_get_clean();

		$this->assertSame( 1, substr_count( $output, 'id="cf7-vk-admin-page"' ) );
		$this->assertSame( 1, substr_count( $output, 'class="cf7vk-admin-page"' ) );
		$this->assertSame( 1, substr_count( $output, 'id="settings-content"' ) );
		$this->assertSame( 0, substr_count( $output, 'id="cf7-vk-container"' ) );
	}

	public function testRenderPageKeepsPluginOwnedFailedMigrationNoticeVisible(): void {
		update_option(
			Migration::STATE_OPTION,
			[
				'schema'         => Migration::STATE_SCHEMA,
				'status'         => Migration::STATUS_FAILED,
				'reason'         => 'self_heal',
				'source_version' => '0.1.3',
				'target_version' => CF7VK_VERSION,
				'attempts'       => 1,
				'current_step'   => '0.1.4',
				'steps'          => [],
				'errors'         => [],
			],
			false
		);

		ob_start();
		Settings::renderPage();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'notice cf7vk-notice notice-error', $output );
		$this->assertStringContainsString( 'Data migration failed. You can retry it below.', $output );
	}
}
