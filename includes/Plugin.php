<?php
declare(strict_types=1);

namespace WPME;

final class Plugin
{
    private static ?Plugin $instance = null;

    public readonly Settings $settings;
    public readonly Logger $logger;
    public readonly FormScanner $scanner;

    public static function instance(): Plugin
    {
        if ( self::$instance === null ) {
            self::$instance = new self();
            self::$instance->boot();
        }
        return self::$instance;
    }

    private function __construct() {}

    private function boot(): void
    {
        load_plugin_textdomain( 'mautic-consent-for-elementor', false, dirname( plugin_basename( WPME_PLUGIN_FILE ) ) . '/languages' );

        global $wpdb;

        $this->settings = new Settings();
        $this->logger   = new Logger( $wpdb );
        $this->scanner  = new FormScanner();

        add_filter( 'elementor/widget/render_content', [ $this, 'inject_consent' ], 20, 2 );
        add_action( 'elementor_pro/forms/new_record', [ $this, 'handle_submission' ], 10, 2 );
        add_action( 'wp_head', [ $this, 'output_custom_css' ], 100 );

        add_action( 'save_post', function (): void {
            try {
                $this->scanner->invalidate();
            } catch ( \Throwable $e ) {
                // Cache invalidation must not break post saving.
            }
        } );

        if ( is_admin() ) {
            ( new \WPME\Admin\Admin( $this->settings, $this->logger, $this->scanner ) )->register();
        }
    }

    public function inject_consent( string $content, object $widget ): string
    {
        try {
            if ( ! method_exists( $widget, 'get_name' ) || $widget->get_name() !== 'form' ) {
                return $content;
            }
            if ( ! $this->settings->is_configured() ) {
                return $content;
            }
            $form_name = (string) $widget->get_settings_for_display( 'form_name' );
            if ( $form_name === '' ) {
                $form_name = (string) $widget->get_id();
            }
            if ( ! $this->settings->is_form_enabled( $form_name ) ) {
                return $content;
            }
            return FormInjector::inject( $content, $this->settings->consent_text() );
        } catch ( \Throwable $e ) {
            return $content;
        }
    }

    public function output_custom_css(): void
    {
        $css = $this->settings->custom_css();
        if ( $css === '' ) {
            return;
        }
        echo '<style id="wpme-custom-css">' . wp_strip_all_tags( $css ) . '</style>';
    }

    public function handle_submission( object $record, ?object $ajax_handler ): void
    {
        try {
            if ( ! $this->settings->is_configured() ) {
                return;
            }
            $creds  = $this->settings->credentials();
            $client = new MauticClient( $creds['mautic_url'], $creds['client_id'], $creds['client_secret'], $creds['http_timeout'] );
            ( new SubmissionHandler( $client, $this->logger, $this->settings ) )->handle( $record, $ajax_handler );
        } catch ( \Throwable $outer ) {
            try {
                $this->logger->log( '', '', 'error', 'Plugin::handle_submission: ' . $outer->getMessage() );
            } catch ( \Throwable $inner ) {
                // Logger itself failed — nothing more to do; do NOT propagate.
            }
        }
    }
}
