<?php

/**
 * SPARXSTAR Oort Mail
 *
 * @package           Starisian\Sparxstar\Oort
 * @author            Starisian Technologies (Max Barrett) <support@starisian.com>
 * @copyright         Copyright (c) 2025 Starisian Technolgoies. All right reserved.
 * @license           Starisan Technolgoes Proprietary License
 *
 * @wordpress-plugin
 * Plugin Name:       SPARXSTAR Oort Mail
 * Plugin URI:        https://starisian.com/sparxstar/sparxstar-oort-mail
 * Description:       SendGrid is a proprietary SaaS, but its official SDKs are MIT-licensed and safe for commercial redistribution and wrapping.
 * Version:           0.5.0
 * Requires at least: 6.8
 * Requires PHP:      8.2
 * Author:            Starisian Technologies (Max Barrett) <support@starisian.com>
 * Author URI:        https://starisian.com
 * Text Domain:       SparxstarOortMail
 * License:           Starisian Technologies Proprietary License
 * License URI:       http://starsian.com/starsian-technolgies-proprietary-license
 * Update URI:        https://starisian.com/sparxstar/sparxstar-oort-mail/update
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    exit;
}

/**
 * ------------------------------------------------------------
 * 1. Composer Autoload Bridge
 * ------------------------------------------------------------
 * MU-plugins load early, so we must explicitly load Composer.
 */
$autoload_paths = [
    ABSPATH . 'vendor/autoload.php',
    WP_CONTENT_DIR . '/vendor/autoload.php',
];

foreach ($autoload_paths as $autoload) {
    if (file_exists($autoload)) {
        require_once $autoload;
        break;
    }
}

use SendGrid\Mail\Mail;

/**
 * ------------------------------------------------------------
 * 2. Core Mailer Class
 * ------------------------------------------------------------
 */
final class SparxstarOortMail
{
    /**
     * Send an email via SendGrid API
     */
    public static function sparx_oort_send(
        string $to,
        string $subject,
        string $html,
        ?string $text = null
    ): bool {

        $api_key = getenv('SENDGRID_API_KEY');

        if (!$api_key) {
            error_log('[SPARXSTAR Oort] Missing SENDGRID_API_KEY');
            return false;
        }

        if (!class_exists(Mail::class)) {
            error_log('[SPARXSTAR Oort] SDK not available (autoload failed)');
            return false;
        }

        $domain      = self::resolve_sender_domain();
        $from_email = 'support@' . $domain;

        $email = new Mail();
        $email->setFrom($from_email, get_bloginfo('name'));
        $email->setSubject($subject);
        $email->addTo($to);

        if ($text) {
            $email->addContent('text/plain', $text);
        }

        $email->addContent('text/html', $html);

        try {
            $sendgrid = new \SendGrid($api_key);
            $response = $sendgrid->send($email);

            return $response->statusCode() === 202;

        } catch (\Throwable $e) {
            error_log('[SPARXSTAR Oort] ' . $e->getMessage());
            return false;
        }
    }

    /**
     * --------------------------------------------------------
     * Sender Domain Resolver
     * --------------------------------------------------------
     * Rules:
     * - Alias domain → use alias
     * - Subdomain → reduce to registrable base
     * - ccTLD-aware (.gm, .za, etc.)
     * - Fallback → sparxstar.com
     */
    private static function sparx_oort_resolve_sender_domain(): string
    {
        $host = wp_parse_url(home_url(), PHP_URL_HOST);

        if (!$host) {
            return 'sparxstar.com';
        }

        $host = strtolower(trim($host));

        // Known ccTLD suffixes used by the network
        $cc_tlds = [
            '.com.gm',
            '.org.gm',
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

        // Standard 2-part TLD fallback (.com, .net, etc.)
        $parts = explode('.', $host);
        $count = count($parts);

        if ($count >= 2) {
            return $parts[$count - 2] . '.' . $parts[$count - 1];
        }

        return 'sparxstar.com';
    }


    public static function sparx_oort_init(): void {
        add_action('network_admin_menu', [self::class, 'sparx_oort_add_health_menu']);
    }
    
    public static function sparx_oort_add_health_menu(): void {
        add_menu_page(
            'Oort Mail Health',
            'Oort Mail Health',
            'manage_network_options',
            'oort-email-health',
            [self::class, 'sparx_oort_render_health_page'],
            'dashicons-email-alt',
            100
        );
    }
    
    public static function sparx_oort_render_health_page(): void {
    
        if (!current_user_can('manage_network_options')) {
            return;
        }
    
        $api_key = getenv('SENDGRID_API_KEY');
        $domain  = self::sparx_oort_resolve_sender_domain();
        $status  = $api_key ? '✅ API Key Found' : '❌ API Key Missing';
    
        $message = '';
    
        if (
            isset($_POST['test_email']) &&
            check_admin_referer('sg_test_action')
        ) {
            $to = sanitize_email(
                wp_unslash($_POST['test_email'])
            );
    
            if (is_email($to)) {
                $success = self::sparx_oort_send(
                    $to,
                    'Network Health Test',
                    '<h1>System Check</h1><p>SendGrid is connected.</p>',
                    'SendGrid is connected.'
                );
    
                $message = $success
                    ? 'Success! Check ' . esc_html($to)
                    : 'Failed. Check error logs.';
            } else {
                $message = 'Invalid email address.';
            }
        }
        ?>
        <div class="wrap">
            <h1>SPARXSTAR Oort Mail Health</h1>
    
            <div class="card" style="max-width:600px;padding:20px;">
                <p><strong>System Status:</strong> <?php echo esc_html($status); ?></p>
                <p><strong>Detected Default Domain:</strong>
                    <code><?php echo esc_html($domain); ?></code>
                </p>
                <p><strong>Active Sending Identity:</strong>
                    <code><?php echo esc_html('support@' . $domain); ?></code>
                </p>
    
                <hr>
    
                <h3>Send a Test Email</h3>
    
                <?php if ($message): ?>
                    <div class="notice notice-info">
                        <p><?php echo esc_html($message); ?></p>
                    </div>
                <?php endif; ?>
    
                <form method="post">
                    <?php wp_nonce_field('sg_test_action'); ?>
                    <input
                        type="email"
                        name="test_email"
                        placeholder="email@example.com"
                        required
                        style="width:250px;"
                    >
                    <button type="submit" class="button button-primary">
                        Send Test
                    </button>
                </form>
            </div>
        </div>
        <?php
    }

}

/**
 * ------------------------------------------------------------
 * 3. WordPress Mail Intercept (Supported & Safe)
 * ------------------------------------------------------------
 * This replaces PHPMailer delivery without overriding wp_mail().
 */
add_filter('pre_wp_mail', function ($null, $atts) {

    $to      = $atts['to'] ?? '';
    $subject = $atts['subject'] ?? '';
    $message = $atts['message'] ?? '';

    if (!$to || !$subject || !$message) {
        return false; // allow WP to handle malformed mail
    }

    $to = is_array($to) ? implode(',', $to) : $to;

    return SparxstarOortMail::sparx_oort_send(
        $to,
        $subject,
        (string) $message,
        wp_strip_all_tags((string) $message)
    );

}, 10, 2);
/**
 * ------------------------------------------------------------
 * 3. MU-plugin initialization
 * ------------------------------------------------------------
 * This initializes the plugin
 */
add_action('muplugins_loaded', ['SparxstarOortMail', 'sparx_oort_init']);

