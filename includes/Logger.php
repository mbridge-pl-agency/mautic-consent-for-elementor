<?php
declare(strict_types=1);

namespace WPME;

class Logger
{
    private const TABLE = 'wpme_logs';
    private const MAX_ROWS = 100;

    public function __construct( private object $wpdb ) {}

    public static function mask_email( string $email ): string
    {
        if ( ! str_contains( $email, '@' ) ) {
            return '';
        }
        [ $local, $domain ] = explode( '@', $email, 2 );
        if ( strlen( $local ) <= 4 ) {
            return $local . '@' . $domain;
        }
        return substr( $local, 0, 4 ) . '***@' . $domain;
    }

    public function log( string $email, string $form_name, string $status, ?string $error_message ): void
    {
        $table = $this->wpdb->prefix . self::TABLE;

        $this->wpdb->insert(
            $table,
            [
                'email_partial' => self::mask_email( $email ),
                'form_name'     => $form_name,
                'status'        => $status,
                'error_message' => $error_message,
            ],
            [ '%s', '%s', '%s', '%s' ]
        );

        $this->prune();
    }

    private function prune(): void
    {
        $table = $this->wpdb->prefix . self::TABLE;
        $sql   = "DELETE FROM {$table} WHERE id <= (SELECT id FROM (SELECT id FROM {$table} ORDER BY id DESC LIMIT 1 OFFSET " . self::MAX_ROWS . ") AS cutoff)";
        $this->wpdb->query( $sql );
    }
}
