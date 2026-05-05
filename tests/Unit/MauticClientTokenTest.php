<?php
declare(strict_types=1);

namespace WPME\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPME\MauticClient;

final class MauticClientTokenTest extends TestCase
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

    public function test_get_token_returns_cached_token_when_transient_set(): void
    {
        Functions\expect( 'get_transient' )->once()->with( 'wpme_mautic_token' )->andReturn( 'cached-abc' );
        Functions\expect( 'wp_remote_post' )->never();

        $client = new MauticClient( 'https://mautic.example.com', 'cid', 'csecret' );
        $this->assertSame( 'cached-abc', $client->get_token() );
    }

    public function test_get_token_fetches_new_when_cache_miss_and_caches_with_ttl(): void
    {
        Functions\expect( 'get_transient' )->once()->andReturn( false );
        Functions\expect( 'wp_remote_post' )
            ->once()
            ->with(
                'https://mautic.example.com/oauth/v2/token',
                \Mockery::on( static function ( array $args ): bool {
                    return ( $args['body']['grant_type']    ?? '' ) === 'client_credentials'
                        && ( $args['body']['client_id']     ?? '' ) === 'cid'
                        && ( $args['body']['client_secret'] ?? '' ) === 'csecret'
                        && ( $args['timeout']               ?? null ) === 5;
                } )
            )
            ->andReturn( [ 'response' => [ 'code' => 200 ], 'body' => json_encode( [ 'access_token' => 'fresh-xyz', 'expires_in' => 3600 ] ) ] );
        Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 200 );
        Functions\expect( 'wp_remote_retrieve_body' )->andReturn( json_encode( [ 'access_token' => 'fresh-xyz', 'expires_in' => 3600 ] ) );
        Functions\expect( 'set_transient' )
            ->once()
            ->with( 'wpme_mautic_token', 'fresh-xyz', 3540 );
        Functions\expect( 'is_wp_error' )->andReturn( false );

        $client = new MauticClient( 'https://mautic.example.com', 'cid', 'csecret' );
        $this->assertSame( 'fresh-xyz', $client->get_token() );
    }

    public function test_get_token_throws_on_non_200_response(): void
    {
        Functions\expect( 'get_transient' )->andReturn( false );
        Functions\expect( 'wp_remote_post' )->andReturn( [ 'response' => [ 'code' => 401 ], 'body' => '{"error":"invalid_client"}' ] );
        Functions\expect( 'wp_remote_retrieve_response_code' )->andReturn( 401 );
        Functions\expect( 'wp_remote_retrieve_body' )->andReturn( '{"error":"invalid_client"}' );
        Functions\expect( 'is_wp_error' )->andReturn( false );

        $client = new MauticClient( 'https://mautic.example.com', 'cid', 'csecret' );

        $this->expectException( \RuntimeException::class );
        $this->expectExceptionMessageMatches( '/auth.*401/i' );
        $client->get_token();
    }

    public function test_invalidate_token_deletes_transient(): void
    {
        Functions\expect( 'delete_transient' )->once()->with( 'wpme_mautic_token' );

        $client = new MauticClient( 'https://mautic.example.com', 'cid', 'csecret' );
        $client->invalidate_token();
        $this->addToAssertionCount( 1 );
    }
}
