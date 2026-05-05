<?php
declare(strict_types=1);

namespace WPME\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WPME\FormInjector;

final class FormInjectorTest extends TestCase
{
    public function test_inject_into_standard_markup_inserts_before_submit_field_group(): void
    {
        $html = '<form><div class="elementor-field-group elementor-column elementor-field-type-text"></div><div class="elementor-field-group elementor-column elementor-field-type-submit"><button type="submit">Send</button></div></form>';
        $out  = FormInjector::inject( $html, 'I consent', 'mautic_consent' );

        $this->assertStringContainsString( 'name="form_fields[mautic_consent]"', $out );
        $this->assertStringContainsString( 'I consent', $out );
        $consent_pos = strpos( $out, 'mautic_consent' );
        $submit_pos  = strpos( $out, 'elementor-field-type-submit' );
        $this->assertNotFalse( $consent_pos );
        $this->assertNotFalse( $submit_pos );
        $this->assertLessThan( $submit_pos, $consent_pos, 'Consent must appear before submit' );
    }

    public function test_inject_returns_unchanged_when_no_submit_marker_found(): void
    {
        $html = '<div>not a form</div>';
        $this->assertSame( $html, FormInjector::inject( $html, 'I consent', 'mautic_consent' ) );
    }

    public function test_inject_only_injects_once_even_if_called_twice(): void
    {
        $html = '<form><div class="elementor-field-group elementor-field-type-submit">btn</div></form>';
        $out1 = FormInjector::inject( $html, 'I consent', 'mautic_consent' );
        $out2 = FormInjector::inject( $out1, 'I consent', 'mautic_consent' );
        $this->assertSame( substr_count( $out1, 'mautic_consent' ), substr_count( $out2, 'mautic_consent' ) );
    }

    public function test_inject_escapes_consent_text_html(): void
    {
        $out = FormInjector::inject(
            '<div class="elementor-field-type-submit"></div>',
            'See <a href="/privacy">policy</a>',
            'mautic_consent'
        );
        $this->assertStringContainsString( '<a href="/privacy">policy</a>', $out );

        $out2 = FormInjector::inject(
            '<div class="elementor-field-type-submit"></div>',
            '<script>alert(1)</script>OK',
            'mautic_consent'
        );
        $this->assertStringNotContainsString( '<script>', $out2 );
    }

    public function test_inject_into_optimized_markup_inserts_before_bare_submit_button(): void
    {
        $html = '<form><input type="text" name="form_fields[email]"><button type="submit">Send</button></form>';
        $out  = FormInjector::inject( $html, 'I consent', 'mautic_consent' );

        $this->assertStringContainsString( 'name="form_fields[mautic_consent]"', $out );
        $consent_pos = strpos( $out, 'mautic_consent' );
        $button_pos  = strpos( $out, '<button' );
        $this->assertNotFalse( $consent_pos );
        $this->assertNotFalse( $button_pos );
        $this->assertLessThan( $button_pos, $consent_pos, 'Consent must appear before submit button' );
    }

    public function test_inject_does_not_corrupt_dollar_digit_in_consent_text(): void
    {
        $html = '<form><div class="elementor-field-group elementor-column elementor-field-type-submit"></div></form>';
        $out  = FormInjector::inject( $html, 'Save $10 today', 'mautic_consent' );

        $this->assertStringContainsString( 'Save $10 today', $out );
        $this->assertStringContainsString( 'elementor-field-type-submit', $out );
    }
}
