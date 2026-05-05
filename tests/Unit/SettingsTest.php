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
}
