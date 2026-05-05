<?php
/**
 * Fires when admin clicks "Delete" in WordPress plugins screen.
 * Drops table, deletes options, deletes transients.
 */
declare(strict_types=1);

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}wpme_logs" );

delete_option( 'wpme_settings' );
delete_option( 'wpme_enabled_forms' );
delete_option( 'wpme_schema_version' );

delete_transient( 'wpme_mautic_token' );
delete_transient( 'wpme_forms_index' );
