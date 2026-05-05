<?php
declare(strict_types=1);

namespace WPME\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPME\Logger;

final class FakeWpdb
{
    public string $prefix = 'wp_';
    /** @var list<array{table: string, data: array, formats: array}> */
    public array $inserts = [];
    /** @var list<string> */
    public array $queries = [];

    public function insert( string $table, array $data, array $formats ): int
    {
        $this->inserts[] = compact( 'table', 'data', 'formats' );
        return 1;
    }

    public function query( string $sql ): int
    {
        $this->queries[] = $sql;
        return 1;
    }
}

final class LoggerTest extends TestCase
{
    public function test_mask_email_keeps_first_four_chars_and_full_domain(): void
    {
        $this->assertSame( 'mart***@example.com', Logger::mask_email( 'martin.smith@example.com' ) );
        $this->assertSame( 'jo@example.com',       Logger::mask_email( 'jo@example.com' ) );
        $this->assertSame( '',                     Logger::mask_email( 'not-an-email' ) );
    }

    public function test_log_inserts_row_via_wpdb(): void
    {
        $wpdb   = new FakeWpdb();
        $logger = new Logger( $wpdb );
        $logger->log( 'martin@example.com', 'Contact', 'success', null );

        $this->assertCount( 1, $wpdb->inserts );
        $this->assertSame( 'wp_wpme_logs',          $wpdb->inserts[0]['table'] );
        $this->assertSame( 'mart***@example.com',   $wpdb->inserts[0]['data']['email_partial'] );
        $this->assertSame( 'Contact',               $wpdb->inserts[0]['data']['form_name'] );
        $this->assertSame( 'success',               $wpdb->inserts[0]['data']['status'] );
        $this->assertNull( $wpdb->inserts[0]['data']['error_message'] );
    }

    public function test_log_prunes_to_last_100_rows(): void
    {
        $wpdb   = new FakeWpdb();
        $logger = new Logger( $wpdb );
        $logger->log( 'a@b.com', 'X', 'success', null );

        $this->assertNotEmpty( $wpdb->queries );
        $this->assertStringContainsString( 'DELETE FROM wp_wpme_logs', $wpdb->queries[0] );
        $this->assertStringContainsString( 'OFFSET 100', $wpdb->queries[0] );
    }
}
