<?php
declare(strict_types=1);

namespace WPME;

final class FormInjector
{
    public const FIELD_NAME = 'mautic_consent';
    public const FIELD_ID   = 'wpme-mautic-consent';

    private const ALLOWED_HTML = [
        'a'      => [ 'href' => true, 'target' => true, 'rel' => true, 'title' => true ],
        'strong' => [],
        'em'     => [],
        'br'     => [],
    ];

    public static function inject( string $html, string $consent_text, string $field_name = self::FIELD_NAME ): string
    {
        if ( str_contains( $html, 'name="form_fields[' . $field_name . ']"' ) ) {
            return $html;
        }

        $clean_text = function_exists( 'wp_kses' )
            ? wp_kses( $consent_text, self::ALLOWED_HTML )
            : strip_tags( $consent_text, '<a><strong><em><br>' );

        $checkbox = sprintf(
            '<div class="elementor-field-group elementor-column elementor-col-100 elementor-field-subgroup wpme-consent-group"><span class="elementor-field-option"><input type="checkbox" name="form_fields[%2$s]" id="%1$s" class="elementor-field elementor-size-md elementor-acceptance-field wpme-consent-input" value="1"><label for="%1$s" class="wpme-consent-label">%3$s</label></span></div>',
            self::FIELD_ID,
            $field_name,
            $clean_text
        );

        $pattern_standard  = '#(<div[^>]*elementor-field-type-submit(?![\w-])[^>]*>)#';
        $pattern_optimized = '#(<button[^>]*type="submit"[^>]*>)#';

        $count   = 0;
        $result  = preg_replace_callback(
            $pattern_standard,
            static fn( array $m ): string => $checkbox . $m[1],
            $html,
            1,
            $count
        );
        if ( $count > 0 && is_string( $result ) ) {
            return $result;
        }

        $result = preg_replace_callback(
            $pattern_optimized,
            static fn( array $m ): string => $checkbox . $m[1],
            $html,
            1,
            $count
        );
        if ( $count > 0 && is_string( $result ) ) {
            return $result;
        }

        return $html;
    }
}
