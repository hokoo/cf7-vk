<?php

declare( strict_types=1 );

use iTRON\cf7Vk\Controllers\MessageFormatter;

final class MessageFormatterTest extends Cf7vk_TestCase {
	public function testFormatForVkIncludesFormSubjectFieldsAndPlainMessage(): void {
		$form = new WPCF7_ContactForm( 10, 'Lead form' );
		$submission = new WPCF7_Submission(
			[
				'your-name' => 'Alice <b>Admin</b>',
				'your-email' => 'alice@example.test',
				'_wpcf7' => '10',
				'empty' => '',
				'choices' => [ 'First', 'Second item' ],
			]
		);

		$message = MessageFormatter::formatForVk(
			'<p>Hello <strong>Alice</strong></p><ul><li>One</li><li>Two</li></ul>',
			$form,
			$submission,
			[
				'use_html' => true,
				'subject' => 'New <b>lead</b>',
			]
		);

		$this->assertStringContainsString( 'Form: Lead form', $message );
		$this->assertStringContainsString( 'Subject: New lead', $message );
		$this->assertStringContainsString( 'Fields:', $message );
		$this->assertStringContainsString( 'Name: Alice Admin', $message );
		$this->assertStringContainsString( 'Email: alice@example.test', $message );
		$this->assertStringContainsString( 'Choices: First, Second item', $message );
		$this->assertStringContainsString( 'Message:', $message );
		$this->assertStringContainsString( 'Hello Alice', $message );
		$this->assertStringNotContainsString( '_wpcf7', $message );
		$this->assertStringNotContainsString( '<strong>', $message );
	}

	public function testFormatForVkReturnsTrimmedBodyWhenMetadataIsEmpty(): void {
		$form = new WPCF7_ContactForm( 10, '' );
		$submission = new WPCF7_Submission( [] );

		$message = MessageFormatter::formatForVk( "  Plain body\n\n\n", $form, $submission );

		$this->assertSame( "Message:\nPlain body", $message );
	}

	public function testFormatForVkNormalizesMultilineHtmlArraysAndPrivateFields(): void {
		$form = new WPCF7_ContactForm( 10, 'Support form' );
		$submission = new WPCF7_Submission(
			[
				'your-message' => "Line one\nLine two",
				'tags' => [ '<b>alpha</b>', 'beta' ],
				'empty-array' => [],
				'empty-string' => '',
				'_private-token' => 'do-not-include',
			]
		);

		$message = MessageFormatter::formatForVk(
			"<div>First line</div><div><strong>Second</strong> line</div>",
			$form,
			$submission,
			[
				'use_html' => true,
				'subject' => 'Support <em>request</em>',
			]
		);

		$this->assertStringContainsString( 'Subject: Support request', $message );
		$this->assertStringContainsString( 'Message: Line one Line two', $message );
		$this->assertStringContainsString( 'Tags: alpha, beta', $message );
		$this->assertStringContainsString( "Message:\nFirst line\nSecond line", $message );
		$this->assertStringNotContainsString( 'empty-array', $message );
		$this->assertStringNotContainsString( 'empty-string', $message );
		$this->assertStringNotContainsString( '_private-token', $message );
		$this->assertStringNotContainsString( '<strong>', $message );
	}
}
