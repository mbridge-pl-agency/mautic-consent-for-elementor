<?php
declare(strict_types=1);

namespace WPME\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPME\MauticClient;

final class MauticClientContactsTest extends TestCase
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

    public function test_find_contact_id_returns_id_when_found(): void
    {
        Functions\expect( 'wp_remote_request' )
            ->once()
            ->andReturnUsing( function ( $url, $args ) {
                $this->assertStringContainsString( '/api/contacts?search=email%3A', $url );
                $this->assertSame( 'GET', $args['method'] );
                $this->assertSame( 'Bearer tok-1', $args['headers']['Authorization'] );
                return [
                    'response' => [ 'code' => 200 ],
                    'body'     => json_encode( [ 'total' => 1, 'contacts' => [ '42' => [ 'id' => 42 ] ] ] ),
                ];
            } );

        $client = new MauticClient( 'https://m.example', 'cid', 'csec' );
        $this->assertSame( 42, $client->find_contact_id_by_email( 'a@b.com' ) );
    }

    public function test_find_contact_id_returns_null_when_not_found(): void
    {
        Functions\expect( 'wp_remote_request' )->andReturn( [
            'response' => [ 'code' => 200 ],
            'body'     => json_encode( [ 'total' => 0, 'contacts' => [] ] ),
        ] );

        $client = new MauticClient( 'https://m.example', 'cid', 'csec' );
        $this->assertNull( $client->find_contact_id_by_email( 'nope@b.com' ) );
    }

    public function test_create_contact_posts_to_new_endpoint_and_returns_id(): void
    {
        Functions\expect( 'wp_remote_request' )->andReturnUsing( function ( $url, $args ) {
            $this->assertStringEndsWith( '/api/contacts/new', $url );
            $this->assertSame( 'POST', $args['method'] );
            $body = json_decode( $args['body'], true );
            $this->assertSame( 'a@b.com', $body['email'] );
            return [
                'response' => [ 'code' => 201 ],
                'body'     => json_encode( [ 'contact' => [ 'id' => 99 ] ] ),
            ];
        } );

        $client = new MauticClient( 'https://m.example', 'cid', 'csec' );
        $this->assertSame( 99, $client->create_contact( [ 'email' => 'a@b.com', 'firstname' => 'Anna' ] ) );
    }

    public function test_update_contact_uses_patch(): void
    {
        Functions\expect( 'wp_remote_request' )->andReturnUsing( function ( $url, $args ) {
            $this->assertStringEndsWith( '/api/contacts/42/edit', $url );
            $this->assertSame( 'PATCH', $args['method'] );
            return [ 'response' => [ 'code' => 200 ], 'body' => '{"contact":{"id":42}}' ];
        } );

        $client = new MauticClient( 'https://m.example', 'cid', 'csec' );
        $client->update_contact( 42, [ 'firstname' => 'Anna' ] );
        $this->addToAssertionCount( 1 );
    }

    public function test_add_to_segment_calls_correct_endpoint(): void
    {
        Functions\expect( 'wp_remote_request' )->andReturnUsing( function ( $url, $args ) {
            $this->assertStringEndsWith( '/api/segments/5/contact/42/add', $url );
            $this->assertSame( 'POST', $args['method'] );
            return [ 'response' => [ 'code' => 200 ], 'body' => '{"success":1}' ];
        } );

        $client = new MauticClient( 'https://m.example', 'cid', 'csec' );
        $client->add_to_segment( 42, 5 );
        $this->addToAssertionCount( 1 );
    }

    public function test_upsert_existing_contact_patches_and_adds_tags(): void
    {
        $calls = [];
        Functions\expect( 'wp_remote_request' )->times( 2 )->andReturnUsing( function ( $url, $args ) use ( &$calls ) {
            $calls[] = [ 'url' => $url, 'method' => $args['method'] ];
            if ( str_contains( $url, '/api/contacts?search=' ) ) {
                return [ 'response' => [ 'code' => 200 ], 'body' => json_encode( [ 'total' => 1, 'contacts' => [ '7' => [ 'id' => 7 ] ] ] ) ];
            }
            return [ 'response' => [ 'code' => 200 ], 'body' => '{"contact":{"id":7}}' ];
        } );

        $client = new MauticClient( 'https://m.example', 'cid', 'csec' );
        $id     = $client->upsert_contact( 'a@b.com', [ 'email' => 'a@b.com', 'firstname' => 'Anna' ], [ 'wp-form-contact' ] );

        $this->assertSame( 7, $id );
        $this->assertSame( 'GET', $calls[0]['method'] );
        $this->assertSame( 'PATCH', $calls[1]['method'] );
    }

    public function test_upsert_missing_contact_creates_and_returns_id(): void
    {
        $calls = [];
        Functions\expect( 'wp_remote_request' )->times( 2 )->andReturnUsing( function ( $url, $args ) use ( &$calls ) {
            $calls[] = $args['method'];
            if ( str_contains( $url, '/api/contacts?search=' ) ) {
                return [ 'response' => [ 'code' => 200 ], 'body' => json_encode( [ 'total' => 0, 'contacts' => [] ] ) ];
            }
            return [ 'response' => [ 'code' => 201 ], 'body' => json_encode( [ 'contact' => [ 'id' => 11 ] ] ) ];
        } );

        $client = new MauticClient( 'https://m.example', 'cid', 'csec' );
        $id     = $client->upsert_contact( 'new@b.com', [ 'email' => 'new@b.com' ], [ 'wp-form-x' ] );

        $this->assertSame( 11, $id );
        $this->assertSame( [ 'GET', 'POST' ], $calls );
    }

    public function test_request_retries_once_on_401_with_refreshed_token(): void
    {
        Functions\expect( 'delete_transient' )->once()->with( 'wpme_mautic_token' );
        Functions\expect( 'set_transient' )->never();

        $call_count = 0;
        Functions\expect( 'wp_remote_request' )->times( 2 )->andReturnUsing( function ( $url, $args ) use ( &$call_count ) {
            $call_count++;
            $this->assertSame( 'Bearer tok-1', $args['headers']['Authorization'] );
            if ( $call_count === 1 ) {
                return [ 'response' => [ 'code' => 401 ], 'body' => '{"error":"expired"}' ];
            }
            return [
                'response' => [ 'code' => 200 ],
                'body'     => json_encode( [ 'total' => 1, 'contacts' => [ '7' => [ 'id' => 7 ] ] ] ),
            ];
        } );

        $client = new MauticClient( 'https://m.example', 'cid', 'csec' );
        $this->assertSame( 7, $client->find_contact_id_by_email( 'a@b.com' ) );
        $this->assertSame( 2, $call_count );
    }
}
