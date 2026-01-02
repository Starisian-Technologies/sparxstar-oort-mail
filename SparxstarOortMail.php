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
 * Plugin Name:       SPARXXSTAR Oort Mail
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
    public static function send(
        string $to,
        string $subject,
        string $html,
        ?string $text = null
    ): bool {

        $api_key = getenv('SENDGRID_API_KEY');

        if (!$api_key) {
            error_log('[SendGrid] Missing SENDGRID_API_KEY');
            return false;
        }

        if (!class_exists(Mail::class)) {
            error_log('[SendGrid] SDK not available (autoload failed)');
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
            error_log('[SendGrid] ' . $e->getMessage());
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
    private static function resolve_sender_domain(): string
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

    return SG_Transactional_Mailer::send(
        $to,
        $subject,
        (string) $message,
        wp_strip_all_tags((string) $message)
    );

}, 10, 2);
