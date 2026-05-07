<?php
declare(strict_types=1);

namespace WPME;

class Settings
{
    public const OPTION_SETTINGS       = 'wpme_settings';
    public const OPTION_ENABLED_FORMS  = 'wpme_enabled_forms';

    public const DEFAULTS = [
        'mautic_url'          => '',
        'oauth_client_id'     => '',
        'oauth_client_secret' => '',
        'segment_id'          => '',
        'tag_prefix'          => 'wp-form-',
        'http_timeout'        => '5',
        'consent_text'        => '',
        'custom_css'          => '',
    ];

    /**
     * @return array{mautic_url: string, client_id: string, client_secret: string, segment_id: int, tag_prefix: string, http_timeout: int}
     */
    public function credentials(): array
    {
        $options = get_option( self::OPTION_SETTINGS, self::DEFAULTS );
        $options = is_array( $options ) ? array_merge( self::DEFAULTS, $options ) : self::DEFAULTS;

        $client_id     = defined( 'WPME_MAUTIC_CLIENT_ID' )     ? (string) constant( 'WPME_MAUTIC_CLIENT_ID' )     : (string) $options['oauth_client_id'];
        $client_secret = defined( 'WPME_MAUTIC_CLIENT_SECRET' ) ? (string) constant( 'WPME_MAUTIC_CLIENT_SECRET' ) : (string) $options['oauth_client_secret'];

        $timeout = (int) ( $options['http_timeout'] ?? 5 );
        if ( $timeout < 1 ) { $timeout = 1; }
        if ( $timeout > 30 ) { $timeout = 30; }

        return [
            'mautic_url'    => rtrim( (string) $options['mautic_url'], '/' ),
            'client_id'     => $client_id,
            'client_secret' => $client_secret,
            'segment_id'    => (int) $options['segment_id'],
            'tag_prefix'    => (string) $options['tag_prefix'],
            'http_timeout'  => $timeout,
        ];
    }

    /**
     * Returns the stored consent text without translation/filtering.
     * Used by admin UI and Polylang registration. For frontend rendering use consent_text().
     */
    public function consent_text_raw(): string
    {
        $options = get_option( self::OPTION_SETTINGS, self::DEFAULTS );
        $options = is_array( $options ) ? array_merge( self::DEFAULTS, $options ) : self::DEFAULTS;
        return (string) $options['consent_text'];
    }

    public function consent_text(): string
    {
        $text = $this->consent_text_raw();
        if ( $text === '' ) {
            $text = function_exists( '__' )
                ? __( 'I consent to receive marketing communications.', 'mautic-consent-for-elementor' )
                : 'I consent to receive marketing communications.';
        }
        // Polylang string translation: switches text to the current language if a translation exists.
        if ( function_exists( 'pll__' ) ) {
            $text = (string) pll__( $text );
        }
        // Generic filter for any other multilingual plugin or custom logic.
        if ( function_exists( 'apply_filters' ) ) {
            $text = (string) apply_filters( 'wpme_consent_text', $text );
        }
        return $text;
    }

    public function custom_css(): string
    {
        $options = get_option( self::OPTION_SETTINGS, self::DEFAULTS );
        $options = is_array( $options ) ? array_merge( self::DEFAULTS, $options ) : self::DEFAULTS;
        return (string) $options['custom_css'];
    }

    public function http_timeout(): int
    {
        $options = get_option( self::OPTION_SETTINGS, self::DEFAULTS );
        $options = is_array( $options ) ? array_merge( self::DEFAULTS, $options ) : self::DEFAULTS;
        $timeout = (int) ( $options['http_timeout'] ?? 5 );
        if ( $timeout < 1 ) {
            return 1;
        }
        if ( $timeout > 30 ) {
            return 30;
        }
        return $timeout;
    }

    public function is_form_enabled( string $form_name ): bool
    {
        $map = get_option( self::OPTION_ENABLED_FORMS, [] );
        if ( ! is_array( $map ) ) {
            return true;
        }
        if ( ! array_key_exists( $form_name, $map ) ) {
            return true;
        }
        return (bool) $map[ $form_name ];
    }

    public function is_configured(): bool
    {
        $c = $this->credentials();
        return $c['mautic_url'] !== '' && $c['client_id'] !== '' && $c['client_secret'] !== '' && $c['segment_id'] > 0;
    }
}
