<?php
declare(strict_types=1);

namespace WPME;

class MauticClient
{
    private const TOKEN_TRANSIENT = 'wpme_mautic_token';

    public const REQUIRED_FIELDS = [
        'elementor_consent',
        'elementor_consent_date',
        'elementor_consent_source',
        'elementor_consent_ip',
    ];

    public function __construct(
        private string $base_url,
        private string $client_id,
        private string $client_secret,
        private int $http_timeout = 5
    ) {
        $this->base_url = rtrim( $this->base_url, '/' );
    }

    public function get_token(): string
    {
        $cached = get_transient( self::TOKEN_TRANSIENT );
        if ( is_string( $cached ) && $cached !== '' ) {
            return $cached;
        }

        $response = wp_remote_post(
            $this->base_url . '/oauth/v2/token',
            [
                'timeout' => $this->http_timeout,
                'body'    => [
                    'grant_type'    => 'client_credentials',
                    'client_id'     => $this->client_id,
                    'client_secret' => $this->client_secret,
                ],
            ]
        );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( 'Mautic auth network error: ' . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );

        if ( $code !== 200 ) {
            throw new \RuntimeException( "Mautic auth failed (HTTP {$code}): {$body}" );
        }

        $payload = json_decode( $body, true );
        if ( ! is_array( $payload ) || empty( $payload['access_token'] ) ) {
            throw new \RuntimeException( "Mautic auth returned invalid payload: {$body}" );
        }

        $access_token = (string) $payload['access_token'];
        $expires_in   = (int) ( $payload['expires_in'] ?? 3600 );
        $ttl          = max( 60, $expires_in - 60 );

        set_transient( self::TOKEN_TRANSIENT, $access_token, $ttl );

        return $access_token;
    }

    public function invalidate_token(): void
    {
        delete_transient( self::TOKEN_TRANSIENT );
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     */
    private function request( string $method, string $path, array $body = [] ): array
    {
        $token = $this->get_token();
        $url   = $this->base_url . $path;

        $args = [
            'method'  => $method,
            'timeout' => $this->http_timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
        ];
        if ( $body !== [] && in_array( $method, [ 'POST', 'PATCH', 'PUT' ], true ) ) {
            $args['body'] = wp_json_encode( $body );
        }

        $response = wp_remote_request( $url, $args );

        if ( is_wp_error( $response ) ) {
            throw new \RuntimeException( "Mautic {$method} {$path} network error: " . $response->get_error_message() );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $raw  = wp_remote_retrieve_body( $response );

        if ( $code === 401 ) {
            $this->invalidate_token();
            $args['headers']['Authorization'] = 'Bearer ' . $this->get_token();
            $response = wp_remote_request( $url, $args );
            if ( is_wp_error( $response ) ) {
                throw new \RuntimeException( "Mautic {$method} {$path} retry network error: " . $response->get_error_message() );
            }
            $code = wp_remote_retrieve_response_code( $response );
            $raw  = wp_remote_retrieve_body( $response );
        }

        if ( $code < 200 || $code >= 300 ) {
            throw new \RuntimeException( "Mautic {$method} {$path} failed (HTTP {$code}): {$raw}" );
        }

        $decoded = json_decode( $raw, true );
        return is_array( $decoded ) ? $decoded : [];
    }

    public function find_contact_id_by_email( string $email ): ?int
    {
        $path = '/api/contacts?search=' . rawurlencode( 'email:' . $email ) . '&minimal=true&limit=1';
        $data = $this->request( 'GET', $path );

        if ( empty( $data['total'] ) || empty( $data['contacts'] ) ) {
            return null;
        }
        $first = reset( $data['contacts'] );
        return isset( $first['id'] ) ? (int) $first['id'] : null;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create_contact( array $data ): int
    {
        $resp = $this->request( 'POST', '/api/contacts/new', $data );
        if ( empty( $resp['contact']['id'] ) ) {
            throw new \RuntimeException( 'Mautic create_contact returned no contact id.' );
        }
        return (int) $resp['contact']['id'];
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update_contact( int $id, array $data ): void
    {
        $this->request( 'PATCH', '/api/contacts/' . $id . '/edit', $data );
    }

    public function add_to_segment( int $contact_id, int $segment_id ): void
    {
        $this->request( 'POST', "/api/segments/{$segment_id}/contact/{$contact_id}/add" );
    }

    /**
     * @param array<string, mixed> $contact_data Mapped Mautic fields (must include 'email').
     * @param list<string>         $tags         Tags to append.
     */
    public function upsert_contact( string $email, array $contact_data, array $tags ): int
    {
        $existing = $this->find_contact_id_by_email( $email );
        $payload  = $contact_data;
        if ( $tags !== [] ) {
            $payload['tags'] = $tags;
        }

        if ( $existing !== null ) {
            $this->update_contact( $existing, $payload );
            return $existing;
        }
        return $this->create_contact( $payload );
    }

    /**
     * @return list<string> Missing field aliases. Empty list = setup valid.
     */
    public function validate_setup(): array
    {
        $resp = $this->request( 'GET', '/api/fields/contact?limit=200' );
        $present = [];
        foreach ( $resp['fields'] ?? [] as $field ) {
            if ( isset( $field['alias'] ) ) {
                $present[] = (string) $field['alias'];
            }
        }
        return array_values( array_diff( self::REQUIRED_FIELDS, $present ) );
    }
}
