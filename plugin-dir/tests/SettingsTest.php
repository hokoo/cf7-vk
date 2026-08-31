<?php

declare( strict_types=1 );

use iTRON\cf7Vk\Controllers\Migration;
use iTRON\cf7Vk\Settings;

final class SettingsTest extends Cf7vk_TestCase {
	private array $reactBuildBackups = [];

	protected function setUp(): void {
		parent::setUp();
		$this->reactBuildBackups = [];
	}

	protected function tearDown(): void {
		foreach ( array_reverse( $this->reactBuildBackups, true ) as $path => $backup ) {
			if ( $backup['exists'] ) {
				file_put_contents( $path, $backup['content'] );
				continue;
			}

			if ( is_file( $path ) ) {
				unlink( $path );
			}
		}
	}

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

	public function testAdminEnqueueScriptsUsesWordPressBuildAssetMetadata(): void {
		$this->writeReactBuildFile( 'static/js/main.js', 'console.log("cf7vk");' );
		$this->writeReactBuildFile( 'static/css/main.css', '#settings-content{display:block;}' );
		$this->writeReactBuildFile(
			'static/js/main.asset.php',
			"<?php return ['dependencies' => ['react', 'wp-element'], 'version' => 'asset-version'];\n"
		);

		do_action( 'cf7vk_settings_screen' );
		Settings::admin_enqueue_scripts();

		$this->assertArrayHasKey( 'cf7-vk-admin-styles', $GLOBALS['wp_enqueued_styles'] );
		$this->assertArrayHasKey( 'cf7-vk-admin', $GLOBALS['wp_enqueued_scripts'] );
		$this->assertSame( 'asset-version', $GLOBALS['wp_enqueued_styles']['cf7-vk-admin-styles']['ver'] );
		$this->assertSame( 'asset-version', $GLOBALS['wp_enqueued_scripts']['cf7-vk-admin']['ver'] );
		$this->assertSame( [ 'react', 'wp-element', 'wp-i18n' ], $GLOBALS['wp_enqueued_scripts']['cf7-vk-admin']['deps'] );
		$this->assertStringContainsString( '/react/build/static/css/main.css', $GLOBALS['wp_enqueued_styles']['cf7-vk-admin-styles']['src'] );
		$this->assertStringContainsString( '/react/build/static/js/main.js', $GLOBALS['wp_enqueued_scripts']['cf7-vk-admin']['src'] );
		$this->assertSame(
			'message-bridge-for-contact-form-7-and-vk',
			$GLOBALS['wp_script_translations']['cf7-vk-admin']['domain']
		);
		$this->assertArrayHasKey( 'cf7vkData', $GLOBALS['wp_localized_scripts']['cf7-vk-admin'] );
	}

	private function writeReactBuildFile( string $relative_path, string $content ): void {
		$path = Settings::pluginDir() . '/react/build/' . ltrim( $relative_path, '/\\' );

		if ( ! array_key_exists( $path, $this->reactBuildBackups ) ) {
			$this->reactBuildBackups[ $path ] = [
				'exists'  => is_file( $path ),
				'content' => is_file( $path ) ? (string) file_get_contents( $path ) : '',
			];
		}

		$directory = dirname( $path );

		if ( ! is_dir( $directory ) ) {
			mkdir( $directory, 0777, true );
		}

		file_put_contents( $path, $content );
	}
}
