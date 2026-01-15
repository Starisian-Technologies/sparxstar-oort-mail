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
use function sanitize_email;
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

if (!defined('ABSPATH')) {
	exit;
}

/**
 * Class Sparxstar_SendGrid_Runtime
 *
 * Provides a shared SendGrid-backed mail transport
 * for the Sparxstar multisite ecosystem.
 */
final class Sparxstar_SendGrid_Runtime
{
	/**
	 * Bootstrap runtime.
	 *
	 * @return void
	 */
	public static function sparx_sendgrid_bootstrap(): void
	{
		self::sparx_sendgrid_bootstrap_autoload();

		add_filter(
			'pre_wp_mail',
			[self::class, 'sparx_sendgrid_intercept'],
			10,
			2
		);

		add_action(
			'network_admin_menu',
			[self::class, 'sparx_sendgrid_register_health_page']
		);
	}

	/**
	 * Attempt to load Composer autoloader if available.
	 *
	 * @internal
	 * @return void
	 */
	private static function sparx_sendgrid_bootstrap_autoload(): void
	{
		$autoload_paths = [
			ABSPATH . 'vendor/autoload.php',
			WP_CONTENT_DIR . '/vendor/autoload.php',
		];

		foreach ($autoload_paths as $path) {
			if (file_exists($path)) {
				require_once $path;
				break;
			}
		}
	}

	/**
	 * Intercept wp_mail() and route through SendGrid.
	 *
	 * @param mixed $null Short-circuit value.
	 * @param array $atts wp_mail arguments.
	 * @return bool|null
	 */
	public static function sparx_sendgrid_intercept($null, array $atts): bool|null
	{
		$to      = $atts['to'] ?? null;
		$subject = $atts['subject'] ?? null;
		$message = $atts['message'] ?? null;

		if (!$to || !$subject || !$message) {
			return null;
		}

		$recipients = is_array($to) ? $to : [$to];
		$recipients = array_filter(
			$recipients,
			static fn ($email) => is_email($email)
		);

		if ($recipients === []) {
			return null;
		}

		return self::sparx_sendgrid_send(
			$recipients,
			(string) $subject,
			(string) $message,
			wp_strip_all_tags((string) $message)
		);
	}

	/**
	 * Send email via SendGrid API.
	 *
	 * @param array<string> $recipients
	 * @param string        $subject
	 * @param string        $html
	 * @param string|null   $text
	 * @return bool
	 */
	public static function sparx_sendgrid_send(
		array $recipients,
		string $subject,
		string $html,
		?string $text = null
	): bool {
		$api_key = getenv('SENDGRID_API_KEY');

		if (!$api_key) {
			self::sparx_sendgrid_log('WARN', 'Missing SENDGRID_API_KEY');
			return false;
		}

		if (!class_exists(\SendGrid\Mail\Mail::class)) {
			self::sparx_sendgrid_log('ERROR', 'SendGrid SDK not loaded (autoload missing)');
			return false;
		}

		$domain = self::sparx_sendgrid_resolve_domain();

		$from_email = apply_filters(
			'sparxstar_sendgrid/from_email',
			'support@' . $domain
		);

		$from_name = apply_filters(
			'sparxstar_sendgrid/from_name',
			get_bloginfo('name')
		);

		do_action('sparxstar_sendgrid/before_send', $recipients, $subject);

		$email = new Mail();
		$email->setFrom($from_email, $from_name);
		$email->setSubject($subject);

		foreach ($recipients as $recipient) {
			$email->addTo($recipient);
		}

		if ($text) {
			$email->addContent('text/plain', $text);
		}

		$email->addContent('text/html', $html);

		try {
			$client   = new \SendGrid($api_key);
			$response = $client->send($email);

			do_action(
				'sparxstar_sendgrid/after_send',
				$response->statusCode()
			);

			return $response->statusCode() === 202;

		} catch (\Throwable $e) {
			self::sparx_sendgrid_log(
				'ERROR',
				implode(',', $recipients) .
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
	private static function sparx_sendgrid_resolve_domain(): string
	{
		$host = wp_parse_url(home_url(), PHP_URL_HOST);

		if (!$host) {
			return 'sparxstar.com';
		}

		$host = strtolower($host);

		$cc_tlds = [
			'.com.gm',
            '.org.gm',
            '.net.gm',
            '.co.uk',
            '.co.za',
            '.org.za',
		];

		foreach ($cc_tlds as $suffix) {
			if (str_ends_with($host, $suffix)) {
				$base = str_replace($suffix, '', $host);
				$parts = explode('.', $base);
				return end($parts) . $suffix;
			}
		}

		$parts = explode('.', $host);
		$count = count($parts);

		return $count >= 2
			? $parts[$count - 2] . '.' . $parts[$count - 1]
			: 'sparxstar.com';
	}

	/**
	 * Register network admin health page.
	 *
	 * @return void
	 */
	public static function sparx_sendgrid_register_health_page(): void
	{
		add_menu_page(
			'Sparxstar Mail Health',
			'Sparxstar Mail',
			'manage_network_options',
			'sparxstar-sendgrid-health',
			[self::class, 'sparx_sendgrid_render_health'],
			'dashicons-email-alt',
			100
		);
	}

	/**
	 * Render health diagnostics UI.
	 *
	 * @return void
	 */
	public static function sparx_sendgrid_render_health(): void
	{
		if (!current_user_can('manage_network_options')) {
			return;
		}

		$api_key = getenv('SENDGRID_API_KEY');
		$domain  = self::sparx_sendgrid_resolve_domain();
		$status  = $api_key ? 'API Key Detected' : 'API Key Missing';
		?>
		<div class="wrap">
			<h1>SPARXSTAR SendGrid Mail Runtime</h1>
			<p><strong>Status:</strong> <?php echo esc_html($status); ?></p>
			<p><strong>Sender:</strong> support@<?php echo esc_html($domain); ?></p>
		</div>
		<?php
	}

	/**
	 * Register WP-CLI commands.
	 *
	 * @return void
	 */
	public static function sparx_sendgrid_cli(): void
	{
		if (!defined('WP_CLI') || !WP_CLI) {
			return;
		}

		\WP_CLI::add_command(
			'sparxstar sendgrid test',
			static function (array $args): void {
				$email = $args[0] ?? null;

				if (!$email || !is_email($email)) {
					\WP_CLI::error('Valid email required.');
				}

				$sent = self::sparx_sendgrid_send(
					[$email],
					'SPARXSTAR SendGrid Runtime Test',
					'<p>SPARXSTAR SendGrid is working.</p>',
					'SPARXSTAR SendGrid is working.'
				);

				$sent
					? \WP_CLI::success('Test email sent.')
					: \WP_CLI::error('Send failed.');
			}
		);
	}
    /**
     * Writes error to log file.
     *
     * @internal
     * @param string $level
     * @param string $message
     * @return void
     */
    private static function sparx_sendgrid_log(string $level, string $message): void {
        error_log("[SPARXSTAR SendGrid {$level}] {$message}");
    }
}

/**
 * Hooks
 */
add_action(
	'muplugins_loaded',
	[Sparxstar_SendGrid_Runtime::class, 'sparx_sendgrid_bootstrap']
);

add_action(
	'cli_init',
	[Sparxstar_SendGrid_Runtime::class, 'sparx_sendgrid_cli']
);
