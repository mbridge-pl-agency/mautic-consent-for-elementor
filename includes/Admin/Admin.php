<?php
declare(strict_types=1);

namespace WPME\Admin;

use WPME\FormScanner;
use WPME\Logger;
use WPME\Settings;

final class Admin
{
    private SettingsPage $page;

    public function __construct(
        private Settings $settings,
        private Logger $logger,
        private FormScanner $scanner
    ) {
        $this->page = new SettingsPage( $settings, $logger, $scanner );
    }

    public function register(): void
    {
        add_action( 'admin_menu', [ $this, 'menu' ] );
        add_action( 'admin_init', [ $this, 'register_settings' ] );
        add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
        add_action( 'wp_ajax_wpme_test_connection', [ $this->page, 'ajax_test_connection' ] );
    }

    public function menu(): void
    {
        add_options_page(
            __( 'Mautic Consent', 'mautic-consent-for-elementor' ),
            __( 'Mautic Consent', 'mautic-consent-for-elementor' ),
            'manage_options',
            'wpme-settings',
            [ $this->page, 'render' ]
        );
    }

    public function register_settings(): void
    {
        register_setting(
            'wpme_settings_group',
            Settings::OPTION_SETTINGS,
            [
                'type'              => 'array',
                'sanitize_callback' => [ $this, 'sanitize_settings' ],
                'default'           => Settings::DEFAULTS,
            ]
        );
        register_setting(
            'wpme_forms_group',
            Settings::OPTION_ENABLED_FORMS,
            [
                'type'              => 'array',
                'sanitize_callback' => [ $this, 'sanitize_forms_map' ],
                'default'           => [],
            ]
        );
    }

    /**
     * @param mixed $input
     * @return array<string, string>
     */
    public function sanitize_settings( $input ): array
    {
        $clean = Settings::DEFAULTS;
        if ( ! is_array( $input ) ) {
            return $clean;
        }
        $clean['mautic_url']          = esc_url_raw( (string) ( $input['mautic_url'] ?? '' ) );
        $clean['oauth_client_id']     = sanitize_text_field( (string) ( $input['oauth_client_id'] ?? '' ) );
        $clean['oauth_client_secret'] = sanitize_text_field( (string) ( $input['oauth_client_secret'] ?? '' ) );
        $clean['segment_id']          = (string) absint( (int) ( $input['segment_id'] ?? 0 ) );
        $clean['tag_prefix']          = sanitize_text_field( (string) ( $input['tag_prefix'] ?? 'wp-form-' ) );
        $timeout_raw                  = (int) ( $input['http_timeout'] ?? 5 );
        if ( $timeout_raw < 1 ) { $timeout_raw = 1; }
        if ( $timeout_raw > 30 ) { $timeout_raw = 30; }
        $clean['http_timeout']        = (string) $timeout_raw;
        $clean['consent_text']        = wp_kses_post( (string) ( $input['consent_text'] ?? '' ) );
        $clean['custom_css']          = wp_strip_all_tags( (string) ( $input['custom_css'] ?? '' ) );
        return $clean;
    }

    /**
     * @param mixed $input
     * @return array<string, bool>
     */
    public function sanitize_forms_map( $input ): array
    {
        if ( ! is_array( $input ) ) {
            return [];
        }
        $out = [];
        foreach ( $input as $key => $value ) {
            $out[ sanitize_text_field( (string) $key ) ] = (bool) $value;
        }
        return $out;
    }

    public function enqueue( string $hook ): void
    {
        if ( $hook !== 'settings_page_wpme-settings' ) {
            return;
        }
        wp_enqueue_style( 'wpme-admin', WPME_PLUGIN_URL . 'assets/admin.css', [], WPME_VERSION );
        wp_enqueue_script( 'wpme-admin', WPME_PLUGIN_URL . 'assets/admin.js', [ 'jquery' ], WPME_VERSION, true );
        wp_localize_script( 'wpme-admin', 'WPME_ADMIN', [
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wpme_test_connection' ),
        ] );
    }
}
