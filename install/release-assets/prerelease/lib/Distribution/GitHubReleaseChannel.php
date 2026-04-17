<?php

namespace iTRON\cf7Vk\Distribution;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

class GitHubReleaseChannel {
	public static function init(): void {
		$update_checker = PucFactory::buildUpdateChecker(
			'https://github.com/hokoo/cf7-vk',
			CF7VK_FILE,
			'message-bridge-for-contact-form-7-and-vk',
			1
		);

		if ( defined( 'CF7VK_GITHUB_TOKEN' ) && '' !== trim( (string) CF7VK_GITHUB_TOKEN ) ) {
			$update_checker->setAuthentication( CF7VK_GITHUB_TOKEN );
		}

		$update_checker->setBranch( 'plugin-dist' );
	}
}
