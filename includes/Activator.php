<?php
declare(strict_types=1);

namespace WPME;

final class Activator
{
    public const TABLE_LOGS = 'wpme_logs';
    public const SCHEMA_VERSION = '1';
    public const OPTION_SCHEMA_VERSION = 'wpme_schema_version';

    public static function activate(): void
    {
        global $wpdb;

        $table   = $wpdb->prefix . self::TABLE_LOGS;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            email_partial VARCHAR(255) NOT NULL DEFAULT '',
            form_name VARCHAR(255) NOT NULL DEFAULT '',
            status VARCHAR(32) NOT NULL DEFAULT '',
            error_message TEXT NULL,
            PRIMARY KEY (id),
            KEY status (status)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( self::OPTION_SCHEMA_VERSION, self::SCHEMA_VERSION );
    }
}
