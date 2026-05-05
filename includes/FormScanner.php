<?php
declare(strict_types=1);

namespace WPME;

final class FormScanner
{
    private const TRANSIENT        = 'wpme_forms_index';
    private const TRANSIENT_TTL    = 3600;
    private const FORM_WIDGET_TYPE = 'form';

    /**
     * @return list<array{form_id: string, form_name: string, post_id: int, post_title: string, post_url: string, fields: list<string>}>
     */
    public function scan(): array
    {
        $cached = get_transient( self::TRANSIENT );
        if ( is_array( $cached ) ) {
            return $cached;
        }

        $posts = get_posts( [
            'post_type'      => [ 'page', 'post' ],
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_key'       => '_elementor_data',
            'fields'         => 'all',
        ] );

        $forms = [];
        foreach ( $posts as $post ) {
            $data = get_post_meta( (int) $post->ID, '_elementor_data', true );
            if ( ! is_string( $data ) || $data === '' ) {
                continue;
            }
            $url   = (string) get_permalink( (int) $post->ID );
            $found = self::extract_forms_from_data( $data, (int) $post->ID, (string) $post->post_title, $url );
            foreach ( $found as $f ) {
                $forms[] = $f;
            }
        }

        set_transient( self::TRANSIENT, $forms, self::TRANSIENT_TTL );
        return $forms;
    }

    public function invalidate(): void
    {
        delete_transient( self::TRANSIENT );
    }

    /**
     * @return list<array{form_id: string, form_name: string, post_id: int, post_title: string, post_url: string, fields: list<string>}>
     */
    public static function extract_forms_from_data( string $json, int $post_id, string $post_title, string $post_url ): array
    {
        $tree = json_decode( $json, true );
        if ( ! is_array( $tree ) ) {
            return [];
        }

        $forms = [];
        self::walk( $tree, $forms, $post_id, $post_title, $post_url );
        return $forms;
    }

    /**
     * @param array<int|string, mixed> $node
     * @param list<array{form_id: string, form_name: string, post_id: int, post_title: string, post_url: string, fields: list<string>}> $forms
     */
    private static function walk( array $node, array &$forms, int $post_id, string $post_title, string $post_url ): void
    {
        if ( isset( $node['elType'] ) || isset( $node['widgetType'] ) ) {
            self::maybe_capture( $node, $forms, $post_id, $post_title, $post_url );
            if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
                self::walk( $node['elements'], $forms, $post_id, $post_title, $post_url );
            }
            return;
        }

        foreach ( $node as $child ) {
            if ( is_array( $child ) ) {
                self::walk( $child, $forms, $post_id, $post_title, $post_url );
            }
        }
    }

    /**
     * @param array<string, mixed> $node
     * @param list<array{form_id: string, form_name: string, post_id: int, post_title: string, post_url: string, fields: list<string>}> $forms
     */
    private static function maybe_capture( array $node, array &$forms, int $post_id, string $post_title, string $post_url ): void
    {
        if ( ( $node['widgetType'] ?? null ) !== self::FORM_WIDGET_TYPE ) {
            return;
        }
        $settings    = $node['settings'] ?? [];
        $form_fields = is_array( $settings['form_fields'] ?? null ) ? $settings['form_fields'] : [];
        $field_ids   = [];
        foreach ( $form_fields as $field ) {
            if ( isset( $field['custom_id'] ) ) {
                $field_ids[] = (string) $field['custom_id'];
            }
        }

        $forms[] = [
            'form_id'    => (string) ( $node['id'] ?? '' ),
            'form_name'  => (string) ( $settings['form_name'] ?? '' ),
            'post_id'    => $post_id,
            'post_title' => $post_title,
            'post_url'   => $post_url,
            'fields'     => $field_ids,
        ];
    }
}
