<?php

/**
 * SparxStar SendGrid Mail Runtime
 *
 * Network-wide MU-plugin runtime that provides a deterministic SendGrid-based
 * email transport layer for WordPress multisite environments.
 *
 * This file:
 * - Intercepts wp_mail() safely via pre_wp_mail
 * - Sends mail through the SendGrid API (no SMTP)
 * - Loads SendGrid SDK via optional Composer autoload
 * - Provides network admin health diagnostics
 * - Exposes WP-CLI commands for testing
 *
 * IMPORTANT:
 * This MU-plugin is infrastructure, not a product.
 * It is always loaded and does not use activation hooks.
 *
 * Runtime Hooks:
 * - sparxstar_sendgrid/before_send
 * - sparxstar_sendgrid/after_send
 *
 * @package   Starisian\Sparxstar\SendGrid
 * @author    Starisian Technologies (Max Barrett) <support@starisian.com>
 * @license   MIT
 * @copyright Copyright (c) 2025–2026 Starisian Technologies
 *
 * @wordpress-muplugin
 * Plugin Name:         SparxStar SendGrid Mail Runtime
 * Description:         Infrastructure-level SendGrid mail transport for WordPress multisite.
 * Version:             0.7.1
 * Requires PHP:        8.2
 * Requires at least:   6.8
 * Author:              Starisian Technologies (Max Barrett) <support@starisian.com>
 * Author URI:          https://starisian.com
 * License: MIT
 * Plugin URI:          https://github.com/Starisian-Technologies/sparxstar-sendgrid-mail-runtime
 */

declare(strict_types=1);

namespace Starisian\Sparxstar\SendGrid;

use SendGrid\Mail\Mail;
use WP_CLI;
use function base64_encode;
use function add_action;
use function add_filter;
use function class_exists;
use function defined;
use function esc_html;
use function error_log;
use function file_exists;
use function getenv;
use function implode;
use function is_array;
use function is_email;
use function wp_parse_url;
use function wp_strip_all_tags;
use function apply_filters;
use function do_action;
use function get_bloginfo;
use function current_user_can;
use function home_url;
use function add_menu_page;
use function explode;
use function count;
use function str_ends_with;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if( ! defined('SPARXSTAR_SENDGRID_API_KEY')) {
	define( 'SPARXSTAR_SENDGRID_API_KEY', 'keys-do-not-belong-here' );
}

/**
 * Class Sparxstar_SendGrid_Runtime
 *
 * Provides a shared SendGrid-backed mail transport
 * for the Sparxstar multisite ecosystem.
 *
 * @package Starisian\Sparxstar\SendGrid
 */
final class Sparxstar_SendGrid_Runtime {




	/**
	 * Singleton instance container.
	 *
	 * @var self|null
	 */
	private static ?self $Sparxstar_SendGrid_Runtime = null;

	/**
	 * Runtime version.
	 *
	 * @var string
	 */
	private const VERSION = '0.7.1';

	/**
	 * SendGrid API Key.
	 *
	 * Loaded from environment variable.
	 *
	 * @var string
	 */
	private string $SENDGRID_API_KEY = '';



	/**
	 * Initialize runtime.
	 *
	 * Populates API key from environment.
	 */
	private function __construct() {
		$this->SENDGRID_API_KEY = SPARXSTAR_SENDGRID_API_KEY;
	}

	/**
	 * Bootstrap runtime.
	 *
	 * @return void
	 */
	public static function sparx_sendgrid_bootstrap(): void {
		self::sparx_sendgrid_bootstrap_autoload();

		add_filter(
			'pre_wp_mail',
			[ self::class, 'sparx_sendgrid_intercept' ],
			10,
			2
		);

		add_action(
			'network_admin_menu',
			[ self::class, 'sparx_sendgrid_register_health_page' ]
		);
	}

	/**
	 * Attempt to load Composer autoloader if available.
	 *
	 * @internal
	 * @return void
	 */
	private static function sparx_sendgrid_bootstrap_autoload(): void {
		$autoload_paths = [
			ABSPATH . 'vendor/autoload.php',
			WP_CONTENT_DIR . '/vendor/autoload.php',
		];

		foreach ( $autoload_paths as $path ) {
			if ( file_exists( $path ) ) {
				require_once $path;
				break;
			}
		}
	}

	/**
	 * Intercept wp_mail() and route through SendGrid.
	 *
	 * @param mixed $return_value Short-circuit value.
	 * @param array $atts         wp_mail arguments.
	 * @return bool|null
	 */
	public static function sparx_sendgrid_intercept( $return_value, array $atts ): bool|null {
		$to      = $atts['to'] ?? null;
		$subject = $atts['subject'] ?? null;
		$message = $atts['message'] ?? null;

		if ( ! $to || ! $subject || ! $message ) {
			return null;
		}

		$recipients = is_array( $to ) ? $to : [ $to ];
		$recipients = array_filter(
			$recipients,
			static fn( $email ) => is_email( $email )
		);

		if ( [] === $recipients ) {
			return null;
		}

		// Parse headers for From, CC, BCC, Reply-To, Content-Type.
		$parsed_headers = self::sparx_sendgrid_parse_headers( $atts['headers'] ?? [] );

		return self::sparx_sendgrid_send(
			$recipients,
			(string) $subject,
			(string) $message,
			wp_strip_all_tags( (string) $message ),
			$parsed_headers['from']['email'],
			$parsed_headers['from']['name'],
			$parsed_headers['reply_to'],
			$parsed_headers['cc'],
			$parsed_headers['bcc'],
			$atts['attachments'] ?? [],
			$parsed_headers['content_type']
		);
	}

	/**
	 * Send email via SendGrid API.
	 *
	 * @param array<string> $recipients          Indexed array of recipient emails.
	 * @param string        $subject             Email subject.
	 * @param string        $html                HTML body content.
	 * @param string|null   $text                Plain text body content.
	 * @param string|null   $override_from_email Custom From Email address.
	 * @param string|null   $override_from_name  Custom From Name.
	 * @param array|null    $reply_to            Optional. Associative array ['email' => string, 'name' => string].
	 * @param array         $cc                  Indexed array of CC emails.
	 * @param array         $bcc                 Indexed array of BCC emails.
	 * @param array         $attachments         Indexed array of file paths to attach.
	 * @param string        $content_type        MIME type (e.g. text/html).
	 *
	 * @return bool True if successfully accepted by SendGrid (HTTP 202).
	 */
	public static function sparx_sendgrid_send(
		array $recipients,
		string $subject,
		string $html,
		?string $text = null,
		?string $override_from_email = null,
		?string $override_from_name = null,
		?array $reply_to = null,
		array $cc = [],
		array $bcc = [],
		array $attachments = [],
		string $content_type = 'text/html'
	): bool {
		$api_key = getenv( 'SENDGRID_API_KEY' );

		if ( ! $api_key ) {
			self::sparx_sendgrid_log( 'WARN', 'Missing SENDGRID_API_KEY' );
			return false;
		}

		if ( ! class_exists( \SendGrid\Mail\Mail::class ) ) {
			self::sparx_sendgrid_log( 'ERROR', 'SendGrid SDK not loaded (autoload missing)' );
			return false;
		}

		// Backward compatibility: If no override passed (e.g. direct call), act as standalone + filter.
		if ( null === $override_from_email ) {
			$domain = self::sparx_sendgrid_resolve_domain();

			$from_email = apply_filters(
				'sparxstar_sendgrid/from_email',
				'support@' . $domain
			);

			$from_name = apply_filters(
				'sparxstar_sendgrid/from_name',
				get_bloginfo( 'name' )
			);
		} else {
			// Override passed from intercept (already filtered via parse_headers).
			$from_email = $override_from_email;
			$from_name  = $override_from_name;
		}

		do_action( 'sparxstar_sendgrid/before_send', $recipients, $subject );

		$email = new Mail();
		$email->setFrom( $from_email, $from_name );
		$email->setSubject( $subject );

		foreach ( $recipients as $recipient ) {
			$email->addTo( $recipient );
		}

		// Add Reply-To
		if ( $reply_to && ! empty( $reply_to['email'] ) ) {
			$email->setReplyTo( $reply_to['email'], $reply_to['name'] ?? null );
		}

		// Add CC
		foreach ( $cc as $cc_email ) {
			if ( is_email( $cc_email ) ) {
				$email->addCc( $cc_email );
			}
		}

		// Add BCC
		foreach ( $bcc as $bcc_email ) {
			if ( is_email( $bcc_email ) ) {
				$email->addBcc( $bcc_email );
			}
		}

		// Handle Content Type & Body
		if ( str_contains( strtolower( $content_type ), 'text/plain' ) ) {
			$email->addContent( 'text/plain', $html );
		} else {
			if ( $text ) {
				$email->addContent( 'text/plain', $text );
			}
			$email->addContent( 'text/html', $html );
		}

		// Handle Attachments
		foreach ( $attachments as $file_path ) {
			if ( is_string( $file_path ) && is_readable( $file_path ) && is_file( $file_path ) ) {
				try {
					$file_content = file_get_contents( $file_path );
					if ( $file_content === false ) {
						continue;
					}

					$attachment = new \SendGrid\Mail\Attachment();
					$attachment->setContent( base64_encode( $file_content ) );
					$attachment->setType( mime_content_type( $file_path ) ?: 'application/octet-stream' );
					$attachment->setFilename( basename( $file_path ) );
					$attachment->setDisposition( 'attachment' );
					$email->addAttachment( $attachment );
				} catch ( \Throwable $e ) {
					self::sparx_sendgrid_log( 'WARN', "Failed to attach file: $file_path. " . $e->getMessage() );
				}
			}
		}

		try {
			$options = [];
			if ( class_exists( \Composer\CaBundle\CaBundle::class ) ) {
				$options['curl'] = [
					CURLOPT_CAINFO => \Composer\CaBundle\CaBundle::getSystemCaRootBundlePath(),
				];
			}

			$client   = new \SendGrid( $api_key, $options );
			$response = $client->send( $email );

			do_action(
				'sparxstar_sendgrid/after_send',
				$response->statusCode()
			);

			return $response->statusCode() === 202;
		} catch ( \Throwable $e ) {
			self::sparx_sendgrid_log(
				'ERROR',
				implode( ',', $recipients ) .
				' — ' . $e->getMessage()
			);
			return false;
		}
	}

	/**
	 * Resolve sender domain from site URL.
	 *
	 * @internal
	 * @return string
	 */
	private static function sparx_sendgrid_resolve_domain(): string {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );

		if ( ! $host ) {
			return 'sparxstar.com';
		}

		$host = strtolower( $host );

		$cc_tlds = [
			'.com.gm',
			'.org.gm',
			'.net.gm',
			'.co.uk',
			'.co.za',
			'.org.za',
		];

		foreach ( $cc_tlds as $suffix ) {
			if ( str_ends_with( $host, $suffix ) ) {
				$base  = str_replace( $suffix, '', $host );
				$parts = explode( '.', $base );
				return end( $parts ) . $suffix;
			}
		}

		$parts = explode( '.', $host );
		$count = count( $parts );

		return $count >= 2
			? $parts[ $count - 2 ] . '.' . $parts[ $count - 1 ]
			: 'sparxstar.com';
	}

	/**
	 * Resolve headers (From, Reply-To, CC, BCC).
	 *
	 * Handles standard WP filters for From address.
	 *
	 * @param string|array $headers Headers passed to wp_mail().
	 * @return array{from: array{email:string, name:string}, reply_to: array|null, cc: array, bcc: array}
	 */
	private static function sparx_sendgrid_parse_headers( $headers = [] ): array {
		// 1. Establish Default From (Sparxstar Logic)
		$domain     = self::sparx_sendgrid_resolve_domain();
		$from_email = apply_filters( 'sparxstar_sendgrid/from_email', 'support@' . $domain );
		$from_name  = apply_filters( 'sparxstar_sendgrid/from_name', get_bloginfo( 'name' ) );

		$parsed = [
			'from'         => [
				'email' => $from_email,
				'name'  => $from_name,
			],
			'reply_to'     => null,
			'cc'           => [],
			'bcc'          => [],
			'content_type' => 'text/html',
		];

		// 2. Parse Headers
		if ( ! empty( $headers ) ) {
			if ( ! is_array( $headers ) ) {
				$headers = explode( "\n", str_replace( "\r\n", "\n", $headers ) );
			}

			foreach ( $headers as $header ) {
				if ( ! str_contains( $header, ':' ) ) {
					continue;
				}

				$parts = explode( ':', $header, 2 );
				$key   = trim( strtolower( $parts[0] ) );
				$val   = trim( $parts[1] );

				// Handle Content-Type specifically
				if ( $key === 'content-type' ) {
					$parsed['content_type'] = $val;
					continue;
				}

				// Extract email/name: "Name <email>" or "email"
				$name_extracted  = '';
				$email_extracted = $val;

				if ( preg_match( '/(.*)<(.+)>/', $val, $matches ) ) {
					$name_extracted  = trim( $matches[1] );
					$email_extracted = trim( $matches[2] );
				}

				if ( ! is_email( $email_extracted ) ) {
					continue;
				}

				$addr = [
					'email' => $email_extracted,
					'name'  => $name_extracted,
				];

				switch ( $key ) {
					case 'from':
						$parsed['from'] = $addr;
						break;
					case 'reply-to':
						$parsed['reply_to'] = $addr;
						break;
					case 'cc':
						$parsed['cc'][] = $email_extracted;
						break;
					case 'bcc':
						$parsed['bcc'][] = $email_extracted;
						break;
				}
			}
		}

		// 3. Apply Standard WordPress Filters override for "From" (Last Priority)
		$proposed_email = apply_filters( 'wp_mail_from', $parsed['from']['email'] );
		$proposed_name  = apply_filters( 'wp_mail_from_name', $parsed['from']['name'] );

		// 4. Safety Check: Enforce Authorized Domain
		// If the proposed email does not belong to our infrastructure domain, revert to default.
		// This prevents SendGrid errors when plugins try to send as "admin@gmail.com".
		if ( self::sparx_sendgrid_is_safe_sender( $proposed_email, $domain ) ) {
			// Sender is safe. Accept the proposed change.
			$parsed['from']['email'] = $proposed_email;
			$parsed['from']['name']  = $proposed_name;
		} else {
			// Sender was unsafe. Revert to the infrastructure default (calculated in Step 1).
			self::sparx_sendgrid_log(
				'WARN',
				"Blocked unsafe sender: $proposed_email. Reverting to safe default: $from_email"
			);
			$parsed['from']['email'] = $from_email;
			$parsed['from']['name']  = $from_name;
		}

		return $parsed;
	}

	/**
	 * Check if email belongs to authorized infrastructure.
	 *
	 * @param string $email       Email address to check.
	 * @param string $safe_domain Authorized domain suffix.
	 * @return bool True if email is authorized.
	 */
	private static function sparx_sendgrid_is_safe_sender( string $email, string $safe_domain ): bool {
		// 1. Allow bypass via filter
		if ( apply_filters( 'sparxstar_sendgrid/allow_external_sender', false, $email ) ) {
			return true;
		}

		// 2. Extract domain from email
		$parts        = explode( '@', $email );
		$email_domain = array_pop( $parts );

		// 3. Strict Check: Email domain must end with safe domain
		// This allows 'support@sparxstar.com' and 'sub@site.sparxstar.com'
		return str_ends_with( strtolower( $email_domain ), strtolower( $safe_domain ) );
	}

	/**
	 * Register network admin health page.
	 *
	 * @return void
	 */
	public static function sparx_sendgrid_register_health_page(): void {
		add_menu_page(
			'Sparxstar Mail Health',
			'Sparxstar Mail',
			'manage_network_options',
			'sparxstar-sendgrid-health',
			[ self::class, 'sparx_sendgrid_render_health' ],
			'dashicons-email-alt',
			100
		);
	}

	/**
	 * Render health diagnostics UI.
	 *
	 * @return void
	 */
	public static function sparx_sendgrid_render_health(): void {
		if ( ! current_user_can( 'manage_network_options' ) ) {
			return;
		}

		$api_key = getenv( 'SENDGRID_API_KEY' );
		$domain  = self::sparx_sendgrid_resolve_domain();
		$status  = $api_key ? 'API Key Detected' : 'API Key Missing';
		?>
		<div class="wrap">
			<h1>SPARXSTAR SendGrid Mail Runtime</h1>
			<p><strong>Status:</strong> <?php echo esc_html( $status ); ?></p>
			<p><strong>Sender:</strong> support@<?php echo esc_html( $domain ); ?></p>
		</div>
		<?php
	}

	/**
	 * Register WP-CLI commands.
	 *
	 * @return void
	 */
	public static function sparx_sendgrid_cli(): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		\WP_CLI::add_command(
			'sparxstar sendgrid test',
			static function ( array $args ): void {
				$email = $args[0] ?? null;

				if ( ! $email || ! is_email( $email ) ) {
					\WP_CLI::error( 'Valid email required.' );
				}

				$sent = self::sparx_sendgrid_send(
					[ $email ],
					'SPARXSTAR SendGrid Runtime Test',
					'<p>SPARXSTAR SendGrid is working.</p>',
					'SPARXSTAR SendGrid is working.'
				);

				$sent
					? \WP_CLI::success( 'Test email sent.' )
					: \WP_CLI::error( 'Send failed.' );
			}
		);
	}
	/**
	 * Writes error to log file.
	 *
	 * @internal
	 * @param string $level   Log level (WARN, ERROR).
	 * @param string $message Log message content.
	 * @return void
	 */
	private static function sparx_sendgrid_log( string $level, string $message ): void {
		error_log( "[SPARXSTAR SendGrid {$level}] {$message}" );
	}
}

/**
 * Hooks
 */
add_action(
	'muplugins_loaded',
	[ Sparxstar_SendGrid_Runtime::class, 'sparx_sendgrid_bootstrap' ]
);

add_action(
	'cli_init',
	[ Sparxstar_SendGrid_Runtime::class, 'sparx_sendgrid_cli' ]
);
