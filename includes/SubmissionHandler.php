<?php
declare(strict_types=1);

namespace WPME;

final class SubmissionHandler
{
    public function __construct(
        private MauticClient $mautic,
        private Logger $logger,
        private Settings $settings
    ) {}

    public function handle( object $record, ?object $ajax_handler ): void
    {
        $consent = ! empty( $_POST['form_fields']['mautic_consent'] ?? null );
        if ( ! $consent ) {
            return;
        }

        $form_name = (string) $record->get_form_settings( 'form_name' );
        if ( ! $this->settings->is_form_enabled( $form_name ) ) {
            return;
        }

        $fields = $record->get( 'fields' );
        if ( ! is_array( $fields ) ) {
            return;
        }

        $email = FieldMapper::extract_email( $fields );
        if ( $email === null ) {
            return;
        }

        $contact_data = FieldMapper::map_to_mautic( $fields );

        $creds = $this->settings->credentials();
        $tag   = $creds['tag_prefix'] . sanitize_title( $form_name );
        $contact_data['elementor_consent']        = 1;
        $contact_data['elementor_consent_date']   = gmdate( 'Y-m-d H:i:s' );
        $contact_data['elementor_consent_source'] = $this->source_label( $form_name );
        $contact_data['elementor_consent_ip']     = $this->client_ip();

        try {
            $contact_id = $this->mautic->upsert_contact( $email, $contact_data, [ $tag ] );
            if ( $creds['segment_id'] > 0 && $contact_id > 0 ) {
                $this->mautic->add_to_segment( $contact_id, $creds['segment_id'] );
            }
            $this->logger->log( $email, $form_name, 'success', null );
        } catch ( \Throwable $e ) {
            $this->logger->log( $email, $form_name, 'error', $e->getMessage() );
        }
    }

    private function client_ip(): string
    {
        $candidates = [];
        if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
            $forwarded   = (string) $_SERVER['HTTP_X_FORWARDED_FOR'];
            $candidates  = array_map( 'trim', explode( ',', $forwarded ) );
        }
        if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
            $candidates[] = (string) $_SERVER['REMOTE_ADDR'];
        }
        foreach ( $candidates as $ip ) {
            if ( filter_var( $ip, FILTER_VALIDATE_IP ) !== false ) {
                return $ip;
            }
        }
        return '';
    }

    private function source_label( string $form_name ): string
    {
        $url = $this->detect_page_url();
        return $url !== '' ? $form_name . ' — ' . $url : $form_name;
    }

    private function detect_page_url(): string
    {
        // 1. Elementor includes post_id in form submission — most reliable.
        $post_id = (int) ( $_REQUEST['post_id'] ?? 0 );
        if ( $post_id > 0 && function_exists( 'get_permalink' ) ) {
            $permalink = get_permalink( $post_id );
            if ( is_string( $permalink ) && $permalink !== '' && filter_var( $permalink, FILTER_VALIDATE_URL ) !== false ) {
                return $permalink;
            }
        }

        // 2. WordPress wp_get_referer — handles _wp_http_referer and HTTP_REFERER fallback.
        if ( function_exists( 'wp_get_referer' ) ) {
            $referer = wp_get_referer();
            if ( is_string( $referer ) && $referer !== '' && filter_var( $referer, FILTER_VALIDATE_URL ) !== false ) {
                return $referer;
            }
        }

        // 3. Direct HTTP_REFERER fallback.
        if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
            $url = (string) $_SERVER['HTTP_REFERER'];
            if ( filter_var( $url, FILTER_VALIDATE_URL ) !== false ) {
                return $url;
            }
        }

        return '';
    }
}
