<?php
declare(strict_types=1);

namespace {

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

if ( ! defined( 'WP_CONTENT_DIR' ) ) {
	define( 'WP_CONTENT_DIR', dirname( __DIR__ ) . '/wp-content' );
}

if ( ! defined( 'SPARXSTAR_SENDGRID_API_KEY' ) ) {
	define( 'SPARXSTAR_SENDGRID_API_KEY', 'test-api-key' );
}

$GLOBALS['sparxstar_test_filters']          = [];
$GLOBALS['sparxstar_test_home_url']         = 'https://www.example.com';
$GLOBALS['sparxstar_test_blog_name']        = 'Test Network';
$GLOBALS['sparxstar_test_environment_type'] = 'development';
$GLOBALS['sparxstar_test_wrong_calls']      = [];

if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
	}
}

if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook, callable $callback, int $priority = 10, int $accepted_args = 1 ): void {
		$GLOBALS['sparxstar_test_filters'][ $hook ][] = $callback;
	}
}

if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $hook, mixed $value, mixed ...$args ): mixed {
		$callbacks = $GLOBALS['sparxstar_test_filters'][ $hook ] ?? [];

		foreach ( $callbacks as $callback ) {
			$value = $callback( $value, ...$args );
		}

		return $value;
	}
}

if ( ! function_exists( 'do_action' ) ) {
	function do_action( string $hook, mixed ...$args ): void {
	}
}

if ( ! function_exists( 'get_bloginfo' ) ) {
	function get_bloginfo( string $show ): string {
		return 'name' === $show ? (string) $GLOBALS['sparxstar_test_blog_name'] : '';
	}
}

if ( ! function_exists( 'home_url' ) ) {
	function home_url(): string {
		return (string) $GLOBALS['sparxstar_test_home_url'];
	}
}

if ( ! function_exists( 'wp_parse_url' ) ) {
	function wp_parse_url( string $url, int $component = -1 ): string|array|int|null|false {
		return parse_url( $url, $component );
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( string $text ): string {
		return strip_tags( $text );
	}
}

if ( ! function_exists( 'is_email' ) ) {
	function is_email( string $email ): string|false {
		return filter_var( $email, FILTER_VALIDATE_EMAIL ) ? $email : false;
	}
}

if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability ): bool {
		return true;
	}
}

if ( ! function_exists( 'add_menu_page' ) ) {
	function add_menu_page(
		string $page_title,
		string $menu_title,
		string $capability,
		string $menu_slug,
		callable $callback,
		string $icon_url = '',
		int $position = 0
	): void {
	}
}

if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

if ( ! function_exists( 'wp_get_environment_type' ) ) {
	function wp_get_environment_type(): string {
		return (string) $GLOBALS['sparxstar_test_environment_type'];
	}
}

if ( ! function_exists( 'wp_doing_it_wrong' ) ) {
	function wp_doing_it_wrong( string $function_name, string $message, string $version ): void {
		$GLOBALS['sparxstar_test_wrong_calls'][] = [
			'function' => $function_name,
			'message'  => $message,
			'version'  => $version,
		];
	}
}
}

namespace SendGrid\Mail {

final class Attachment {
	public string $content = '';
	public string $type = '';
	public string $filename = '';
	public string $disposition = '';

	public function setContent( string $content ): void {
		$this->content = $content;
	}

	public function setType( string $type ): void {
		$this->type = $type;
	}

	public function setFilename( string $filename ): void {
		$this->filename = $filename;
	}

	public function setDisposition( string $disposition ): void {
		$this->disposition = $disposition;
	}
}

final class Mail {
	public string $from_email = '';
	public ?string $from_name = null;
	public string $subject = '';
	public array $to = [];
	public array $cc = [];
	public array $bcc = [];
	public array $contents = [];
	public array $attachments = [];
	public ?array $reply_to = null;

	public function setFrom( string $email, ?string $name = null ): void {
		$this->from_email = $email;
		$this->from_name  = $name;
	}

	public function setSubject( string $subject ): void {
		$this->subject = $subject;
	}

	public function addTo( string $email ): void {
		$this->to[] = $email;
	}

	public function setReplyTo( string $email, ?string $name = null ): void {
		$this->reply_to = [
			'email' => $email,
			'name'  => $name,
		];
	}

	public function addCc( string $email ): void {
		$this->cc[] = $email;
	}

	public function addBcc( string $email ): void {
		$this->bcc[] = $email;
	}

	public function addContent( string $type, string $content ): void {
		$this->contents[] = [
			'type'    => $type,
			'content' => $content,
		];
	}

	public function addAttachment( Attachment $attachment ): void {
		$this->attachments[] = $attachment;
	}
}
}

namespace {

final class SendGridResponseStub {
	public function __construct(
		private readonly int $status_code
	) {
	}

	public function statusCode(): int {
		return $this->status_code;
	}
}

final class SendGrid {
	public static ?\SendGrid\Mail\Mail $last_email = null;
	public static int $status_code = 202;
	public static bool $throw_on_send = false;

	public function __construct(
		string $api_key,
		array $options = []
	) {
		if ( '' === $api_key && [] === $options ) {
			return;
		}
	}

	public function send( \SendGrid\Mail\Mail $email ): SendGridResponseStub {
		self::$last_email = $email;

		if ( self::$throw_on_send ) {
			throw new \RuntimeException( 'Simulated SendGrid failure' );
		}

		return new SendGridResponseStub( self::$status_code );
	}
}

require_once dirname( __DIR__ ) . '/SparxstarSendGridRuntime.php';
}
