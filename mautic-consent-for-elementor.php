<?php
/**
 * Plugin Name: Mautic Consent for Elementor
 * Plugin URI:  https://github.com/mbridge-pl-agency/mautic-consent-for-elementor
 * Description: Automatically adds a marketing consent checkbox to all Elementor Pro forms and syncs opt-ins to Mautic.
 * Version:     0.1.3
 * Requires PHP: 8.1
 * Requires at least: 6.4
 * Author:      Marcin Wilczyński
 * Author URI:  https://github.com/mbridge-pl-agency
 * License:     GPL-2.0-or-later
 * Text Domain: mautic-consent-for-elementor
 * Domain Path: /languages
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'WPME_VERSION', '0.1.3' );
define( 'WPME_PLUGIN_FILE', __FILE__ );
define( 'WPME_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPME_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Composer autoload (required for namespaced classes under WPME\).
$autoload = WPME_PLUGIN_DIR . 'vendor/autoload.php';
if ( ! file_exists( $autoload ) ) {
    add_action( 'admin_notices', static function (): void {
        echo '<div class="notice notice-error"><p><strong>Mautic Consent for Elementor:</strong> Composer dependencies missing. Run <code>composer install</code> in the plugin directory.</p></div>';
    } );
    return;
}
require_once $autoload;

register_activation_hook( __FILE__, [ '\\WPME\\Activator', 'activate' ] );

// Bootstrap on plugins_loaded so Elementor Pro is available.
add_action( 'plugins_loaded', static function (): void {
    if ( ! class_exists( '\\WPME\\Plugin' ) ) {
        return;
    }
    \WPME\Plugin::instance();
} );
