<?php
declare(strict_types=1);

namespace WPME\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPME\FormScanner;

final class FormScannerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_extracts_forms_from_elementor_data_recursively(): void
    {
        $json = file_get_contents( __DIR__ . '/../fixtures/elementor-data-sample.json' );

        $forms = FormScanner::extract_forms_from_data( $json, 42, 'Kontakt page', 'https://example.com/kontakt' );

        $this->assertCount( 1, $forms );
        $this->assertSame( 'form_abc',                  $forms[0]['form_id'] );
        $this->assertSame( 'Kontakt',                   $forms[0]['form_name'] );
        $this->assertSame( 42,                          $forms[0]['post_id'] );
        $this->assertSame( 'Kontakt page',              $forms[0]['post_title'] );
        $this->assertSame( 'https://example.com/kontakt', $forms[0]['post_url'] );
        $this->assertSame( [ 'name', 'email', 'message' ], $forms[0]['fields'] );
    }

    public function test_returns_empty_when_no_forms_present(): void
    {
        $json = json_encode( [ [ 'id' => 's', 'elType' => 'section', 'elements' => [] ] ] );
        $this->assertSame( [], FormScanner::extract_forms_from_data( $json, 1, '', '' ) );
    }

    public function test_scan_uses_transient_cache_when_set(): void
    {
        Functions\expect( 'get_transient' )->once()->with( 'wpme_forms_index' )->andReturn( [ [ 'form_id' => 'cached' ] ] );
        Functions\expect( 'get_posts' )->never();

        $scanner = new FormScanner();
        $this->assertSame( [ [ 'form_id' => 'cached' ] ], $scanner->scan() );
    }

    public function test_scan_walks_posts_when_cache_miss(): void
    {
        Functions\expect( 'get_transient' )->andReturn( false );
        Functions\expect( 'get_posts' )->once()->andReturn( [
            (object) [ 'ID' => 42, 'post_title' => 'Kontakt' ],
        ] );
        Functions\expect( 'get_post_meta' )->once()->with( 42, '_elementor_data', true )->andReturn( file_get_contents( __DIR__ . '/../fixtures/elementor-data-sample.json' ) );
        Functions\expect( 'get_permalink' )->once()->with( 42 )->andReturn( 'https://example.com/kontakt' );
        Functions\expect( 'set_transient' )
            ->once()
            ->with( 'wpme_forms_index', \Mockery::type( 'array' ), 3600 );

        $scanner = new FormScanner();
        $forms   = $scanner->scan();

        $this->assertCount( 1, $forms );
        $this->assertSame( 'Kontakt', $forms[0]['form_name'] );
    }

    public function test_scan_accumulates_forms_from_multiple_posts(): void
    {
        Functions\expect( 'get_transient' )->andReturn( false );

        $fixture = file_get_contents( __DIR__ . '/../fixtures/elementor-data-sample.json' );

        Functions\expect( 'get_posts' )->once()->andReturn( [
            (object) [ 'ID' => 10, 'post_title' => 'Page A' ],
            (object) [ 'ID' => 20, 'post_title' => 'Page B' ],
        ] );
        Functions\expect( 'get_post_meta' )->times( 2 )->andReturnUsing( static function ( int $id, string $key, bool $single ) use ( $fixture ): string {
            return $fixture;
        } );
        Functions\expect( 'get_permalink' )->times( 2 )->andReturnUsing( static function ( int $id ): string {
            return 'https://example.com/' . $id;
        } );
        Functions\expect( 'set_transient' )->once();

        $scanner = new FormScanner();
        $forms   = $scanner->scan();

        $this->assertCount( 2, $forms );
        $this->assertSame( 10, $forms[0]['post_id'] );
        $this->assertSame( 20, $forms[1]['post_id'] );
        $this->assertSame( 'Page A', $forms[0]['post_title'] );
        $this->assertSame( 'Page B', $forms[1]['post_title'] );
    }
}
