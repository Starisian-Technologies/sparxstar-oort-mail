<?php
/**
 * SparxStar SendGrid Runtime
 *
 * Network-wide MU-plugin runtime that provides a deterministic SendGrid-based
 * email transport layer for WordPress multisite environments.
 *
 * IMPORTANT:
 * This MU-plugin is infrastructure, not a product.
 * It is always loaded and does not use activation hooks.
 *
 * This plugin is designed to be used in conjunction with the main SparxStar SendGrid plugin,
 * which provides the user interface and configuration options. This runtime plugin should not be
 * activated or deactivated independently, as it is a critical component of the overall system.
 * If you need to disable the SendGrid email transport, please do so through the main plugin's settings
 * rather than deactivating this MU-plugin.
 *
 * This plugin is intended for use in WordPress multisite environments and should be placed in the
 * `wp-content/mu-plugins` directory. It will automatically load and provide the necessary
 * infrastructure for the SendGrid email transport across the entire multisite network.
 *
 * @package   Starisian\Sparxstar\SendGrid
 * @author    Starisian Technologies (Max Barrett) <support@starisian.com>
 * @license   Proprietary
 * @copyright Copyright (c) 2025–2026 Starisian Technologies
 *
 * @wordpress-muplugin
 * Plugin Name:         SparxStar SendGrid Runtime
 * Description:         Infrastructure-level SendGrid email transport for WordPress multisite.
 * Version:             0.8.0
 * Requires PHP:        8.2
 * Requires at least:   6.8
 * Author:              Starisian Technologies (Max Barrett) <support@starisian.com>
 * Author URI:          https://starisian.com
 * License: Proprietary
 * Plugin URI:          https://github.com/Starisian-Technologies/sparxstar-sendgrid-runtime
 * Text Domain:         sparxstar-sendgrid-runtime
 * Domain Path:         /languages
 * Update URI:          sparxstar-sendgrid-runtime
 * GitHub Plugin URI:   https://github.com/Starisian-Technologies/sparxstar-sendgrid-runtime
 */

if ( file_exists( __DIR__ . '/sparxstar-sendgrid-runtime/SparxstarSendGridRuntime.php' ) ) {
	require_once __DIR__ . '/sparxstar-sendgrid-runtime/SparxstarSendGridRuntime.php';
	\Starisian\Sparxstar\SendGrid\SparxstarSendGridRuntime::sparx_sendgrid_get_instance();
} else {
	// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	error_log(
		'SparxStar SendGrid Runtime: Plugin file not found in ' .
		__DIR__ . '/sparxstar-sendgrid-runtime/SparxstarSendGridRuntime.php'
	);

	add_action(
		'network_admin_notices',
		function () {
			if ( ! current_user_can( 'manage_options' ) ) {
				return;
			}
			?>
		<div class="notice notice-error">
			<p>
				<strong>SparxStar SendGrid Runtime Error:</strong>
				The runtime plugin file was not found. Please ensure the directory
				<code>sparxstar-sendgrid-runtime</code> exists internally and contains
				<code>SparxstarSendGridRuntime.php</code>.
			</p>
		</div>
			<?php
		}
	);
}
