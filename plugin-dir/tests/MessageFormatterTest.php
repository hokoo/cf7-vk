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
}
