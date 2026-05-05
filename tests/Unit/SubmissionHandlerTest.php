<?php
declare(strict_types=1);

namespace WPME\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPME\Logger;
use WPME\MauticClient;
use WPME\Settings;
use WPME\SubmissionHandler;

final class FakeFormRecord
{
    public function __construct( private array $fields, private array $settings ) {}
    public function get( string $key ): mixed { return $key === 'fields' ? $this->fields : null; }
    public function get_form_settings( string $key ): string { return (string) ( $this->settings[ $key ] ?? '' ); }
}

final class SubmissionHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        $_POST = [];
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        $_POST = [];
        unset( $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_X_FORWARDED_FOR'], $_SERVER['HTTP_REFERER'] );
        parent::tearDown();
    }

    public function test_skips_when_consent_checkbox_not_checked(): void
    {
        $client   = $this->createMock( MauticClient::class );
        $logger   = $this->createMock( Logger::class );
        $settings = $this->createMock( Settings::class );

        $client->expects( $this->never() )->method( 'upsert_contact' );

        $record = new FakeFormRecord(
            [ 'email' => [ 'value' => 'a@b.com' ] ],
            [ 'form_name' => 'Kontakt' ]
        );
        $_POST['form_fields'] = [];

        ( new SubmissionHandler( $client, $logger, $settings ) )->handle( $record, null );
    }

    public function test_skips_when_form_disabled(): void
    {
        $client   = $this->createMock( MauticClient::class );
        $logger   = $this->createMock( Logger::class );
        $settings = $this->createMock( Settings::class );
        $settings->method( 'is_form_enabled' )->with( 'Kontakt' )->willReturn( false );

        $client->expects( $this->never() )->method( 'upsert_contact' );

        $record = new FakeFormRecord(
            [ 'email' => [ 'value' => 'a@b.com' ] ],
            [ 'form_name' => 'Kontakt' ]
        );
        $_POST['form_fields'] = [ 'mautic_consent' => '1' ];

        ( new SubmissionHandler( $client, $logger, $settings ) )->handle( $record, null );
    }

    public function test_skips_when_email_missing(): void
    {
        $client   = $this->createMock( MauticClient::class );
        $logger   = $this->createMock( Logger::class );
        $settings = $this->createMock( Settings::class );
        $settings->method( 'is_form_enabled' )->willReturn( true );

        $client->expects( $this->never() )->method( 'upsert_contact' );

        $record = new FakeFormRecord( [], [ 'form_name' => 'Kontakt' ] );
        $_POST['form_fields'] = [ 'mautic_consent' => '1' ];

        ( new SubmissionHandler( $client, $logger, $settings ) )->handle( $record, null );
    }

    public function test_happy_path_calls_upsert_and_add_to_segment_and_logs_success(): void
    {
        Functions\stubs( [
            'sanitize_title' => fn( $s ) => strtolower( str_replace( ' ', '-', $s ) ),
        ] );

        $client = $this->createMock( MauticClient::class );
        $client->expects( $this->once() )
            ->method( 'upsert_contact' )
            ->with(
                'a@b.com',
                $this->callback( fn( $payload ) =>
                    $payload['email'] === 'a@b.com'
                    && $payload['firstname'] === 'Anna'
                    && $payload['elementor_consent'] === 1
                    && ! empty( $payload['elementor_consent_date'] )
                ),
                $this->equalTo( [ 'wp-form-kontakt' ] )
            )
            ->willReturn( 99 );
        $client->expects( $this->once() )->method( 'add_to_segment' )->with( 99, 5 );

        $logger = $this->createMock( Logger::class );
        $logger->expects( $this->once() )->method( 'log' )->with( 'a@b.com', 'Kontakt', 'success', null );

        $settings = $this->createMock( Settings::class );
        $settings->method( 'is_form_enabled' )->willReturn( true );
        $settings->method( 'credentials' )->willReturn( [
            'mautic_url' => 'x', 'client_id' => 'x', 'client_secret' => 'x', 'segment_id' => 5, 'tag_prefix' => 'wp-form-',
        ] );

        $_SERVER['REMOTE_ADDR']  = '1.2.3.4';
        $_POST['form_fields']    = [ 'mautic_consent' => '1' ];
        $record = new FakeFormRecord(
            [ 'email' => [ 'value' => 'a@b.com' ], 'firstname' => [ 'value' => 'Anna' ] ],
            [ 'form_name' => 'Kontakt' ]
        );

        ( new SubmissionHandler( $client, $logger, $settings ) )->handle( $record, null );
    }

    public function test_mautic_failure_is_logged_and_swallowed(): void
    {
        Functions\stubs( [ 'sanitize_title' => fn( $s ) => $s ] );

        $client = $this->createMock( MauticClient::class );
        $client->method( 'upsert_contact' )->willThrowException( new \RuntimeException( 'boom' ) );

        $logger = $this->createMock( Logger::class );
        $logger->expects( $this->once() )->method( 'log' )->with( 'a@b.com', 'Kontakt', 'error', $this->stringContains( 'boom' ) );

        $settings = $this->createMock( Settings::class );
        $settings->method( 'is_form_enabled' )->willReturn( true );
        $settings->method( 'credentials' )->willReturn( [
            'mautic_url' => 'x', 'client_id' => 'x', 'client_secret' => 'x', 'segment_id' => 5, 'tag_prefix' => 'wp-form-',
        ] );

        $_POST['form_fields'] = [ 'mautic_consent' => '1' ];
        $record = new FakeFormRecord(
            [ 'email' => [ 'value' => 'a@b.com' ] ],
            [ 'form_name' => 'Kontakt' ]
        );

        ( new SubmissionHandler( $client, $logger, $settings ) )->handle( $record, null );
    }
}
