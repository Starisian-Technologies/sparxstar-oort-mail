<?php
declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Starisian\Sparxstar\SendGrid\SparxstarSendGridRuntime;

final class SparxstarSendGridRuntimeTest extends TestCase {
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['sparxstar_test_filters']          = [];
		$GLOBALS['sparxstar_test_home_url']         = 'https://www.example.com';
		$GLOBALS['sparxstar_test_blog_name']        = 'Test Network';
		$GLOBALS['sparxstar_test_environment_type'] = 'development';
		$GLOBALS['sparxstar_test_wrong_calls']      = [];

		SendGrid::$last_email   = null;
		SendGrid::$status_code  = 202;
		SendGrid::$throw_on_send = false;

		$this->setPrivateStaticProperty( 'api_key', 'test-api-key' );
	}

	public function test_parse_headers_supports_comma_delimited_cc_and_bcc(): void {
		$headers = implode(
			"\r\n",
			[
				'From: Sender <sender@example.com>',
				'Reply-To: Reply <reply@example.com>',
				'CC: cc-one@example.com, cc-two@example.com',
				'BCC: bcc-one@example.com, Bcc Two <bcc-two@example.com>',
				'Content-Type: text/plain',
			]
		);

		$parsed_headers = $this->invokePrivateStaticMethod( 'sparx_sendgrid_parse_headers', $headers );

		self::assertSame( 'sender@example.com', $parsed_headers['from']['email'] );
		self::assertSame( 'Sender', $parsed_headers['from']['name'] );
		self::assertSame(
			[
				'email' => 'reply@example.com',
				'name'  => 'Reply',
			],
			$parsed_headers['reply_to']
		);
		self::assertSame( [ 'cc-one@example.com', 'cc-two@example.com' ], $parsed_headers['cc'] );
		self::assertSame( [ 'bcc-one@example.com', 'bcc-two@example.com' ], $parsed_headers['bcc'] );
		self::assertSame( 'text/plain', $parsed_headers['content_type'] );
	}

	public function test_is_safe_sender_requires_exact_domain_boundary(): void {
		self::assertTrue(
			$this->invokePrivateStaticMethod( 'sparx_sendgrid_is_safe_sender', 'sender@star.com', 'star.com' )
		);
		self::assertTrue(
			$this->invokePrivateStaticMethod( 'sparx_sendgrid_is_safe_sender', 'sender@sub.star.com', 'star.com' )
		);
		self::assertFalse(
			$this->invokePrivateStaticMethod( 'sparx_sendgrid_is_safe_sender', 'sender@evil-star.com', 'star.com' )
		);
	}

	public function test_resolve_domain_handles_standard_and_cc_tld_hosts(): void {
		$GLOBALS['sparxstar_test_home_url'] = 'https://mail.service.co.uk';
		self::assertSame( 'service.co.uk', $this->invokePrivateStaticMethod( 'sparx_sendgrid_resolve_domain' ) );

		$GLOBALS['sparxstar_test_home_url'] = 'https://app.example.com';
		self::assertSame( 'example.com', $this->invokePrivateStaticMethod( 'sparx_sendgrid_resolve_domain' ) );
	}

	public function test_send_skips_oversized_attachments(): void {
		$attachment_path = tempnam( sys_get_temp_dir(), 'spx' );
		self::assertNotFalse( $attachment_path );

		$handle = fopen( $attachment_path, 'wb' );
		self::assertNotFalse( $handle );

		fseek( $handle, ( 25 * 1024 * 1024 ) + 1 );
		fwrite( $handle, 'A' );
		fclose( $handle );

		try {
			$sent = SparxstarSendGridRuntime::sparx_sendgrid_send(
				[ 'recipient@example.com' ],
				'Subject',
				'<p>HTML</p>',
				'Text body',
				'sender@example.com',
				'Sender',
				null,
				[],
				[],
				[ $attachment_path ]
			);

			self::assertTrue( $sent );
			self::assertNotNull( SendGrid::$last_email );
			self::assertCount( 0, SendGrid::$last_email->attachments );
		} finally {
			unlink( $attachment_path );
		}
	}

	/**
	 * @param mixed ...$args Private method arguments.
	 * @return mixed
	 */
	private function invokePrivateStaticMethod( string $method_name, mixed ...$args ): mixed {
		$reflection = new ReflectionMethod( SparxstarSendGridRuntime::class, $method_name );
		$reflection->setAccessible( true );

		return $reflection->invokeArgs( null, $args );
	}

	private function setPrivateStaticProperty( string $property_name, mixed $value ): void {
		$reflection = new ReflectionProperty( SparxstarSendGridRuntime::class, $property_name );
		$reflection->setAccessible( true );
		$reflection->setValue( null, $value );
	}
}
