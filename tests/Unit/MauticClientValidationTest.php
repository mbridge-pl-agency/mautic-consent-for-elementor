<?php
declare(strict_types=1);

namespace WPME\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPME\MauticClient;

final class MauticClientValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\stubs( [
            'get_transient'                  => 'tok-1',
            'is_wp_error'                    => false,
            'wp_remote_retrieve_response_code' => fn( $r ) => $r['response']['code'],
            'wp_remote_retrieve_body'        => fn( $r ) => $r['body'],
        ] );
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function test_validate_setup_returns_empty_when_all_required_fields_present(): void
    {
        Functions\expect( 'wp_remote_request' )->andReturn( [
            'response' => [ 'code' => 200 ],
            'body'     => json_encode( [ 'fields' => [
                [ 'alias' => 'email' ],
                [ 'alias' => 'elementor_consent' ],
                [ 'alias' => 'elementor_consent_date' ],
                [ 'alias' => 'elementor_consent_source' ],
                [ 'alias' => 'elementor_consent_ip' ],
            ] ] ),
        ] );

        $client = new MauticClient( 'https://m.example', 'cid', 'csec' );
        $this->assertSame( [], $client->validate_setup() );
    }

    public function test_validate_setup_returns_missing_aliases(): void
    {
        Functions\expect( 'wp_remote_request' )->andReturn( [
            'response' => [ 'code' => 200 ],
            'body'     => json_encode( [ 'fields' => [
                [ 'alias' => 'email' ],
                [ 'alias' => 'elementor_consent' ],
            ] ] ),
        ] );

        $client = new MauticClient( 'https://m.example', 'cid', 'csec' );
        $this->assertSame(
            [ 'elementor_consent_date', 'elementor_consent_source', 'elementor_consent_ip' ],
            $client->validate_setup()
        );
    }
}
