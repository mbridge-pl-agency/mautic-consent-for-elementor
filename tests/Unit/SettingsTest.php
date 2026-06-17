<?php
declare(strict_types=1);

namespace WPME\Tests\Unit;

use Brain\Monkey;
use Brain\Monkey\Functions;
use PHPUnit\Framework\TestCase;
use WPME\Settings;

final class SettingsTest extends TestCase
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

    public function test_get_credentials_uses_option_when_no_constants(): void
    {
        Functions\expect( 'get_option' )->with( 'wpme_settings', \Mockery::any() )->andReturn( [
            'mautic_url'       => 'https://m.example',
            'oauth_client_id'  => 'cid',
            'oauth_client_secret' => 'csec',
            'segment_id'       => '7',
            'tag_prefix'       => 'wp-form-',
            'consent_text'     => 'I agree',
        ] );

        $s = new Settings();
        $c = $s->credentials();
        $this->assertSame( 'https://m.example', $c['mautic_url'] );
        $this->assertSame( 'cid',                $c['client_id'] );
        $this->assertSame( 'csec',               $c['client_secret'] );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_get_credentials_prefers_constants_when_defined(): void
    {
        define( 'WPME_MAUTIC_CLIENT_ID', 'CONST_CID' );
        define( 'WPME_MAUTIC_CLIENT_SECRET', 'CONST_SEC' );

        Functions\expect( 'get_option' )->andReturn( [
            'mautic_url'       => 'https://m.example',
            'oauth_client_id'  => 'option_cid',
            'oauth_client_secret' => 'option_sec',
        ] );

        $s = new Settings();
        $c = $s->credentials();
        $this->assertSame( 'CONST_CID', $c['client_id'] );
        $this->assertSame( 'CONST_SEC', $c['client_secret'] );
    }

    public function test_is_form_enabled_defaults_to_true_for_unknown_form(): void
    {
        Functions\expect( 'get_option' )->with( 'wpme_enabled_forms', \Mockery::any() )->andReturn( [] );

        $s = new Settings();
        $this->assertTrue( $s->is_form_enabled( 'Brand new form' ) );
    }

    public function test_is_form_enabled_returns_stored_value(): void
    {
        Functions\expect( 'get_option' )->with( 'wpme_enabled_forms', \Mockery::any() )->andReturn( [
            'Brand new form' => false,
            'Kontakt'        => true,
        ] );

        $s = new Settings();
        $this->assertFalse( $s->is_form_enabled( 'Brand new form' ) );
        $this->assertTrue( $s->is_form_enabled( 'Kontakt' ) );
    }

    public function test_consent_text_returns_stored_value_when_set(): void
    {
        Functions\expect( 'get_option' )->andReturn( [
            'consent_text' => 'My custom text',
        ] );

        $this->assertSame( 'My custom text', ( new Settings() )->consent_text() );
    }

    public function test_consent_text_falls_back_to_translated_default_when_empty(): void
    {
        Functions\expect( 'get_option' )->andReturn( [ 'consent_text' => '' ] );

        $text = ( new Settings() )->consent_text();
        $this->assertStringContainsString( 'consent', $text );
    }

    public function test_is_configured_returns_false_when_any_credential_missing(): void
    {
        Functions\expect( 'get_option' )->andReturn( [
            'mautic_url'          => 'https://m.example',
            'oauth_client_id'     => 'cid',
            'oauth_client_secret' => '',
            'segment_id'          => '7',
        ] );

        $this->assertFalse( ( new Settings() )->is_configured() );
    }

    public function test_is_configured_returns_true_when_all_set(): void
    {
        Functions\expect( 'get_option' )->andReturn( [
            'mautic_url'          => 'https://m.example',
            'oauth_client_id'     => 'cid',
            'oauth_client_secret' => 'csec',
            'segment_id'          => '7',
        ] );

        $this->assertTrue( ( new Settings() )->is_configured() );
    }

    public function test_custom_css_returns_stored_value(): void
    {
        Functions\expect( 'get_option' )->andReturn( [
            'custom_css' => '.foo { color: red; }',
        ] );

        $this->assertSame( '.foo { color: red; }', ( new Settings() )->custom_css() );
    }

    public function test_custom_css_returns_empty_string_when_unset(): void
    {
        Functions\expect( 'get_option' )->andReturn( [] );

        $this->assertSame( '', ( new Settings() )->custom_css() );
    }

    public function test_consent_text_raw_returns_stored_value_without_translation(): void
    {
        Functions\expect( 'get_option' )->andReturn( [
            'consent_text' => 'Wyrażam zgodę na newsletter',
        ] );

        $this->assertSame( 'Wyrażam zgodę na newsletter', ( new Settings() )->consent_text_raw() );
    }

    /**
     * @param array<string, mixed> $map
     */
    private function stub_options( string $segment_id, array $map ): void
    {
        Functions\when( 'get_option' )->alias( function ( $name, $default = false ) use ( $segment_id, $map ) {
            if ( $name === 'wpme_segment_map' ) {
                return $map;
            }
            return [ 'segment_id' => $segment_id ];
        } );
    }

    public function test_segment_for_language_returns_default_when_lang_null(): void
    {
        $this->stub_options( '5', [ 'pl' => 5, 'en' => 7 ] );

        $this->assertSame( 5, ( new Settings() )->segment_for_language( null ) );
    }

    public function test_segment_for_language_returns_mapped_override(): void
    {
        $this->stub_options( '5', [ 'pl' => 5, 'en' => 7 ] );

        $this->assertSame( 7, ( new Settings() )->segment_for_language( 'en' ) );
    }

    public function test_segment_for_language_falls_back_to_default_when_lang_not_mapped(): void
    {
        $this->stub_options( '5', [ 'en' => 7 ] );

        $this->assertSame( 5, ( new Settings() )->segment_for_language( 'de' ) );
    }

    public function test_segment_for_language_falls_back_when_map_has_zero(): void
    {
        $this->stub_options( '5', [ 'en' => 0 ] );

        $this->assertSame( 5, ( new Settings() )->segment_for_language( 'en' ) );
    }
}
