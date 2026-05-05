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
        $referer = '';
        if ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
            $url = (string) $_SERVER['HTTP_REFERER'];
            if ( filter_var( $url, FILTER_VALIDATE_URL ) !== false ) {
                $referer = $url;
            }
        }
        return $referer !== '' ? $form_name . ' — ' . $referer : $form_name;
    }
}
