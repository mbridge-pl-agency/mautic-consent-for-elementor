<?php
declare(strict_types=1);

namespace WPME\Admin;

use WPME\FormScanner;
use WPME\Logger;
use WPME\MauticClient;
use WPME\Settings;

final class SettingsPage
{
    public function __construct(
        private Settings $settings,
        private Logger $logger,
        private FormScanner $scanner
    ) {}

    /**
     * @return array<string, string>
     */
    private function tabs(): array
    {
        return [
            'mautic'  => __( 'Mautic', 'mautic-consent-for-elementor' ),
            'consent' => __( 'Consent text', 'mautic-consent-for-elementor' ),
            'style'   => __( 'Style', 'mautic-consent-for-elementor' ),
            'forms'   => __( 'Forms', 'mautic-consent-for-elementor' ),
            'logs'    => __( 'Logs', 'mautic-consent-for-elementor' ),
            'setup'   => __( 'Help / Setup', 'mautic-consent-for-elementor' ),
        ];
    }

    public function render(): void
    {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $current = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( (string) $_GET['tab'] ) ) : 'mautic';
        if ( ! array_key_exists( $current, $this->tabs() ) ) {
            $current = 'mautic';
        }

        echo '<div class="wrap"><h1>' . esc_html__( 'Mautic Consent for Elementor', 'mautic-consent-for-elementor' ) . '</h1>';
        $this->render_tabs( $current );

        echo '<div class="wpme-tab-content">';
        match ( $current ) {
            'mautic'  => $this->render_mautic_tab(),
            'consent' => $this->render_consent_tab(),
            'style'   => $this->render_style_tab(),
            'forms'   => $this->render_forms_tab(),
            'logs'    => $this->render_logs_tab(),
            'setup'   => $this->render_setup_tab(),
        };
        echo '</div></div>';
    }

    private function render_tabs( string $current ): void
    {
        echo '<h2 class="nav-tab-wrapper">';
        foreach ( $this->tabs() as $slug => $label ) {
            $url   = admin_url( 'options-general.php?page=wpme-settings&tab=' . $slug );
            $class = 'nav-tab' . ( $slug === $current ? ' nav-tab-active' : '' );
            printf( '<a href="%s" class="%s">%s</a>', esc_url( $url ), esc_attr( $class ), esc_html( $label ) );
        }
        echo '</h2>';
    }

    private function render_mautic_tab(): void
    {
        $opts             = get_option( Settings::OPTION_SETTINGS, Settings::DEFAULTS );
        $secret_overridden = defined( 'WPME_MAUTIC_CLIENT_SECRET' );
        $id_overridden     = defined( 'WPME_MAUTIC_CLIENT_ID' );

        echo '<form method="post" action="options.php">';
        settings_fields( 'wpme_settings_group' );

        foreach ( [ 'consent_text', 'custom_css' ] as $key ) {
            printf(
                '<input type="hidden" name="%s[%s]" value="%s">',
                esc_attr( Settings::OPTION_SETTINGS ),
                esc_attr( $key ),
                esc_attr( (string) ( $opts[ $key ] ?? '' ) )
            );
        }

        echo '<table class="form-table">';
        $this->row( __( 'Mautic URL', 'mautic-consent-for-elementor' ), sprintf( '<input type="url" name="%s[mautic_url]" value="%s" class="regular-text" required>', esc_attr( Settings::OPTION_SETTINGS ), esc_attr( $opts['mautic_url'] ?? '' ) ) );

        $cid_input = sprintf(
            '<input type="text" name="%s[oauth_client_id]" value="%s" class="regular-text" %s>',
            esc_attr( Settings::OPTION_SETTINGS ),
            $id_overridden ? '' : esc_attr( $opts['oauth_client_id'] ?? '' ),
            $id_overridden ? 'disabled' : ''
        );
        $cid_note = $id_overridden ? '<p class="description">' . sprintf( esc_html__( 'Overridden by %s constant in wp-config.php.', 'mautic-consent-for-elementor' ), '<code>WPME_MAUTIC_CLIENT_ID</code>' ) . '</p>' : '';
        $this->row( __( 'OAuth Client ID', 'mautic-consent-for-elementor' ), $cid_input . $cid_note );

        $sec_input = sprintf(
            '<input type="password" name="%s[oauth_client_secret]" value="%s" class="regular-text" %s autocomplete="off">',
            esc_attr( Settings::OPTION_SETTINGS ),
            $secret_overridden ? '' : esc_attr( $opts['oauth_client_secret'] ?? '' ),
            $secret_overridden ? 'disabled' : ''
        );
        $sec_note = $secret_overridden ? '<p class="description">' . sprintf( esc_html__( 'Overridden by %s constant in wp-config.php.', 'mautic-consent-for-elementor' ), '<code>WPME_MAUTIC_CLIENT_SECRET</code>' ) . '</p>' : '';
        $this->row( __( 'OAuth Client Secret', 'mautic-consent-for-elementor' ), $sec_input . $sec_note );

        $this->row( __( 'Segment ID', 'mautic-consent-for-elementor' ), sprintf( '<input type="number" name="%s[segment_id]" value="%s" class="small-text" min="1">', esc_attr( Settings::OPTION_SETTINGS ), esc_attr( (string) ( $opts['segment_id'] ?? '' ) ) ) );
        $this->row( __( 'Tag prefix', 'mautic-consent-for-elementor' ), sprintf( '<input type="text" name="%s[tag_prefix]" value="%s" class="regular-text">', esc_attr( Settings::OPTION_SETTINGS ), esc_attr( $opts['tag_prefix'] ?? 'wp-form-' ) ) );
        $this->row(
            __( 'HTTP timeout (seconds)', 'mautic-consent-for-elementor' ),
            sprintf(
                '<input type="number" name="%s[http_timeout]" value="%s" class="small-text" min="1" max="30">'
                . '<p class="description">%s</p>',
                esc_attr( Settings::OPTION_SETTINGS ),
                esc_attr( (string) ( $opts['http_timeout'] ?? '5' ) ),
                esc_html__( 'How long to wait for each Mautic API call before giving up. Default 5. Lower = faster recovery from a dead Mautic; higher = tolerates slow networks. Each form submission can make up to 4 API calls.', 'mautic-consent-for-elementor' )
            )
        );
        echo '</table>';

        submit_button();
        echo '</form>';

        echo '<hr><h2>' . esc_html__( 'Test connection', 'mautic-consent-for-elementor' ) . '</h2>';
        echo '<p><button class="button" id="wpme-test-connection">' . esc_html__( 'Test connection and Mautic fields', 'mautic-consent-for-elementor' ) . '</button> <span id="wpme-test-result"></span></p>';
    }

    private function render_consent_tab(): void
    {
        $opts = get_option( Settings::OPTION_SETTINGS, Settings::DEFAULTS );
        echo '<form method="post" action="options.php">';
        settings_fields( 'wpme_settings_group' );
        echo '<table class="form-table">';
        foreach ( [ 'mautic_url', 'oauth_client_id', 'oauth_client_secret', 'segment_id', 'tag_prefix', 'http_timeout', 'custom_css' ] as $key ) {
            $value = (string) ( $opts[ $key ] ?? '' );
            if ( $key === 'oauth_client_id' && defined( 'WPME_MAUTIC_CLIENT_ID' ) ) {
                $value = '';
            }
            if ( $key === 'oauth_client_secret' && defined( 'WPME_MAUTIC_CLIENT_SECRET' ) ) {
                $value = '';
            }
            printf(
                '<input type="hidden" name="%s[%s]" value="%s">',
                esc_attr( Settings::OPTION_SETTINGS ),
                esc_attr( $key ),
                esc_attr( $value )
            );
        }
        $this->row(
            __( 'Checkbox text', 'mautic-consent-for-elementor' ),
            sprintf(
                '<textarea name="%s[consent_text]" rows="4" cols="80" class="large-text">%s</textarea><p class="description">%s</p>',
                esc_attr( Settings::OPTION_SETTINGS ),
                esc_textarea( $opts['consent_text'] ?? '' ),
                esc_html__( 'Allowed tags: a, strong, em, br.', 'mautic-consent-for-elementor' )
            )
        );
        echo '</table>';
        submit_button();
        echo '</form>';
    }

    private function render_style_tab(): void
    {
        $opts = get_option( Settings::OPTION_SETTINGS, Settings::DEFAULTS );
        echo '<form method="post" action="options.php">';
        settings_fields( 'wpme_settings_group' );
        echo '<table class="form-table">';

        foreach ( [ 'mautic_url', 'oauth_client_id', 'oauth_client_secret', 'segment_id', 'tag_prefix', 'http_timeout', 'consent_text' ] as $key ) {
            $value = (string) ( $opts[ $key ] ?? '' );
            if ( $key === 'oauth_client_id' && defined( 'WPME_MAUTIC_CLIENT_ID' ) ) {
                $value = '';
            }
            if ( $key === 'oauth_client_secret' && defined( 'WPME_MAUTIC_CLIENT_SECRET' ) ) {
                $value = '';
            }
            printf(
                '<input type="hidden" name="%s[%s]" value="%s">',
                esc_attr( Settings::OPTION_SETTINGS ),
                esc_attr( $key ),
                esc_attr( $value )
            );
        }

        $this->row(
            __( 'Custom CSS', 'mautic-consent-for-elementor' ),
            sprintf(
                '<textarea name="%s[custom_css]" rows="14" cols="80" class="large-text code" spellcheck="false" placeholder="%s">%s</textarea>'
                . '<p class="description">%s</p>'
                . '<details style="margin-top:8px"><summary style="cursor:pointer">%s</summary><pre style="background:#f0f0f0;padding:10px;font-size:12px;margin-top:6px"><code>%s</code></pre></details>',
                esc_attr( Settings::OPTION_SETTINGS ),
                esc_attr__( '/* your CSS here */', 'mautic-consent-for-elementor' ),
                esc_textarea( $opts['custom_css'] ?? '' ),
                esc_html__( 'CSS is injected into the frontend <head> only when non-empty. Targets these classes: .wpme-consent-group (the wrapper div), .wpme-consent-label (the label), .wpme-consent-input (the checkbox).', 'mautic-consent-for-elementor' ),
                esc_html__( 'Show example CSS', 'mautic-consent-for-elementor' ),
                esc_html(
                    "/* Bigger checkbox */\n"
                    . ".wpme-consent-input { transform: scale(1.2); }\n\n"
                    . "/* Smaller text */\n"
                    . ".wpme-consent-label { font-size: 12px; color: #666; }\n\n"
                    . "/* Spacing around the field */\n"
                    . ".wpme-consent-group { margin: 16px 0; }\n\n"
                    . "/* Force override stubborn theme rules — use !important */\n"
                    . ".wpme-consent-label { color: #333 !important; }"
                )
            )
        );

        echo '</table>';
        submit_button();
        echo '</form>';
    }

    private function render_forms_tab(): void
    {
        if ( isset( $_GET['rescan'] ) && check_admin_referer( 'wpme_rescan' ) ) {
            $this->scanner->invalidate();
            wp_safe_redirect( admin_url( 'options-general.php?page=wpme-settings&tab=forms' ) );
            exit;
        }

        echo '<form method="post" action="options.php">';
        settings_fields( 'wpme_forms_group' );

        $forms   = $this->scanner->scan();
        $enabled = get_option( Settings::OPTION_ENABLED_FORMS, [] );

        $rescan_url = wp_nonce_url(
            admin_url( 'options-general.php?page=wpme-settings&tab=forms&rescan=1' ),
            'wpme_rescan'
        );
        echo '<p><a href="' . esc_url( $rescan_url ) . '" class="button">' . esc_html__( 'Rescan', 'mautic-consent-for-elementor' ) . '</a></p>';

        if ( $forms === [] ) {
            echo '<p>' . esc_html__( 'No Elementor Pro forms detected.', 'mautic-consent-for-elementor' ) . '</p>';
        } else {
            echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Form name', 'mautic-consent-for-elementor' ) . '</th><th>' . esc_html__( 'Page', 'mautic-consent-for-elementor' ) . '</th><th>' . esc_html__( 'Fields', 'mautic-consent-for-elementor' ) . '</th><th>' . esc_html__( 'Enabled', 'mautic-consent-for-elementor' ) . '</th></tr></thead><tbody>';
            foreach ( $forms as $form ) {
                $name    = $form['form_name'] !== '' ? $form['form_name'] : '(' . $form['form_id'] . ')';
                $checked = ( $enabled[ $name ] ?? true ) ? 'checked' : '';
                printf(
                    '<tr><td><strong>%s</strong></td><td><a href="%s" target="_blank">%s</a></td><td><code>%s</code></td><td><input type="hidden" name="%s[%s]" value="0"><input type="checkbox" name="%s[%s]" value="1" %s></td></tr>',
                    esc_html( $name ),
                    esc_url( $form['post_url'] ),
                    esc_html( $form['post_title'] ),
                    esc_html( implode( ', ', $form['fields'] ) ),
                    esc_attr( Settings::OPTION_ENABLED_FORMS ),
                    esc_attr( $name ),
                    esc_attr( Settings::OPTION_ENABLED_FORMS ),
                    esc_attr( $name ),
                    $checked
                );
            }
            echo '</tbody></table>';
            submit_button( __( 'Save selection', 'mautic-consent-for-elementor' ) );
        }
        echo '</form>';
    }

    private function render_logs_tab(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'wpme_logs';
        $rows  = $wpdb->get_results( "SELECT * FROM {$table} ORDER BY id DESC LIMIT 100", ARRAY_A );

        if ( empty( $rows ) ) {
            echo '<p>' . esc_html__( 'No log entries.', 'mautic-consent-for-elementor' ) . '</p>';
            return;
        }
        echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Date', 'mautic-consent-for-elementor' ) . '</th><th>' . esc_html__( 'Email (masked)', 'mautic-consent-for-elementor' ) . '</th><th>' . esc_html__( 'Form', 'mautic-consent-for-elementor' ) . '</th><th>' . esc_html__( 'Status', 'mautic-consent-for-elementor' ) . '</th><th>' . esc_html__( 'Error', 'mautic-consent-for-elementor' ) . '</th></tr></thead><tbody>';
        foreach ( $rows as $row ) {
            printf(
                '<tr><td>%s</td><td>%s</td><td>%s</td><td><span class="wpme-status wpme-status-%s">%s</span></td><td>%s</td></tr>',
                esc_html( (string) $row['created_at'] ),
                esc_html( (string) $row['email_partial'] ),
                esc_html( (string) $row['form_name'] ),
                esc_attr( (string) $row['status'] ),
                esc_html( (string) $row['status'] ),
                esc_html( (string) ( $row['error_message'] ?? '' ) )
            );
        }
        echo '</tbody></table>';
    }

    private function render_setup_tab(): void
    {
        $is_configured = $this->settings->is_configured();
        $required_fields = MauticClient::REQUIRED_FIELDS;

        echo '<div class="wpme-setup-guide" style="max-width:800px">';

        if ( $is_configured ) {
            echo '<div class="notice notice-success inline" style="margin:0 0 20px"><p><strong>'
                . esc_html__( '✓ Plugin is configured.', 'mautic-consent-for-elementor' )
                . '</strong> '
                . esc_html__( 'The guide below is for first-time configuration or for your admin team.', 'mautic-consent-for-elementor' )
                . '</p></div>';
        } else {
            echo '<div class="notice notice-warning inline" style="margin:0 0 20px"><p><strong>'
                . esc_html__( '⚠ Plugin is not yet fully configured.', 'mautic-consent-for-elementor' )
                . '</strong> '
                . esc_html__( 'Walk through the Mautic steps below, then return to the Mautic tab to fill in your credentials.', 'mautic-consent-for-elementor' )
                . '</p></div>';
        }

        echo '<h2>' . esc_html__( 'In Mautic', 'mautic-consent-for-elementor' ) . '</h2>';

        echo '<h3>' . esc_html__( '1. Enable the API', 'mautic-consent-for-elementor' ) . '</h3>';
        echo '<p>' . esc_html__( 'Settings (gear icon at top) → Configuration → API Settings → "API enabled?" = Yes. Save.', 'mautic-consent-for-elementor' ) . '</p>';

        echo '<h3>' . esc_html__( '2. Create OAuth2 credentials', 'mautic-consent-for-elementor' ) . '</h3>';
        echo '<p>' . esc_html__( 'Settings → API Credentials → + New → Authorization Protocol: OAuth 2 → Name: any descriptive name → Redirect URI: any valid URL (e.g. your WP admin URL; not functionally used in server-to-server flow). After Save you will see Public Key (= Client ID) and Secret Key (= Client Secret) — the secret is shown only once, copy it.', 'mautic-consent-for-elementor' ) . '</p>';

        echo '<h3>' . esc_html__( '3. Create a segment', 'mautic-consent-for-elementor' ) . '</h3>';
        echo '<p>' . esc_html__( 'Left menu → Segments → + New → give it a name. After saving, open the segment for edit — the segment ID is in the browser URL (e.g. /segments/edit/3 → ID = 3).', 'mautic-consent-for-elementor' ) . '</p>';

        echo '<h3>' . esc_html__( '4. Create the 4 required custom contact fields', 'mautic-consent-for-elementor' ) . '</h3>';
        echo '<p>' . esc_html__( 'Settings → Custom Fields → + New (four times). Object: Contact. Publicly updatable: check for all.', 'mautic-consent-for-elementor' ) . '</p>';
        echo '<p><strong>'
            . esc_html__( 'Alias must match EXACTLY as below. Mautic auto-generates the alias from the label but sometimes truncates — verify before saving.', 'mautic-consent-for-elementor' )
            . '</strong></p>';

        echo '<p><strong>'
            . esc_html__( 'Note: the "Elementor Consent Source" field must have Length = 255. The default 64 chars is too short — "value too long" error when the page URL exceeds the limit.', 'mautic-consent-for-elementor' )
            . '</strong></p>';

        echo '<table class="widefat striped" style="max-width:700px">';
        echo '<thead><tr>';
        echo '<th>' . esc_html__( 'Label (suggested)', 'mautic-consent-for-elementor' ) . '</th>';
        echo '<th>' . esc_html__( 'Alias (required exactly)', 'mautic-consent-for-elementor' ) . '</th>';
        echo '<th>' . esc_html__( 'Data Type', 'mautic-consent-for-elementor' ) . '</th>';
        echo '<th>' . esc_html__( 'Length', 'mautic-consent-for-elementor' ) . '</th>';
        echo '</tr></thead><tbody>';
        $rows = [
            [ 'Elementor Consent',        'elementor_consent',        'Yes/No (Boolean)', '—' ],
            [ 'Elementor Consent Date',   'elementor_consent_date',   'Datetime',         '—' ],
            [ 'Elementor Consent Source', 'elementor_consent_source', 'Text',             '255 (default 64 is too short)' ],
            [ 'Elementor Consent IP',     'elementor_consent_ip',     'Text',             '64 (sufficient; IPv6 max is 45)' ],
        ];
        foreach ( $rows as $r ) {
            printf(
                '<tr><td>%s</td><td><code>%s</code></td><td>%s</td><td>%s</td></tr>',
                esc_html( $r[0] ),
                esc_html( $r[1] ),
                esc_html( $r[2] ),
                esc_html( $r[3] )
            );
        }
        echo '</tbody></table>';

        echo '<h2 style="margin-top:30px">' . esc_html__( 'In WordPress', 'mautic-consent-for-elementor' ) . '</h2>';

        echo '<h3>' . esc_html__( '5. Fill in the Mautic tab', 'mautic-consent-for-elementor' ) . '</h3>';
        echo '<p>' . esc_html__( 'Mautic URL, Client ID, Client Secret, Segment ID. Save Changes.', 'mautic-consent-for-elementor' ) . '</p>';

        echo '<h3>' . esc_html__( '6. Click "Test connection and Mautic fields"', 'mautic-consent-for-elementor' ) . '</h3>';
        echo '<p>' . esc_html__( 'The plugin pings the Mautic API: verifies credentials and that all 4 custom fields exist. ✓ = everything OK. ✗ with a list of missing aliases = go back to step 4 and check the spelling.', 'mautic-consent-for-elementor' ) . '</p>';

        echo '<h3>' . esc_html__( '7. Consent text and forms list', 'mautic-consent-for-elementor' ) . '</h3>';
        echo '<p>' . esc_html__( 'In the Consent text tab, enter the final checkbox copy (with a link to your privacy policy). In the Forms tab, disable the checkbox for forms where it does not belong (e.g. HR, complaints).', 'mautic-consent-for-elementor' ) . '</p>';

        echo '<h2 style="margin-top:30px">' . esc_html__( 'Optional: secret from wp-config.php', 'mautic-consent-for-elementor' ) . '</h2>';
        echo '<p>' . esc_html__( 'To avoid storing the Client Secret in the WP database, define constants in wp-config.php:', 'mautic-consent-for-elementor' ) . '</p>';
        echo '<pre style="background:#f0f0f0;padding:10px;max-width:500px"><code>'
            . "define( 'WPME_MAUTIC_CLIENT_ID', 'your_public_key' );\n"
            . "define( 'WPME_MAUTIC_CLIENT_SECRET', 'your_secret_key' );"
            . '</code></pre>';
        echo '<p>' . esc_html__( 'Constant values take precedence over the database; UI fields are then disabled with a notice.', 'mautic-consent-for-elementor' ) . '</p>';

        echo '<h2 style="margin-top:30px">' . esc_html__( 'Multilingual sites (Polylang)', 'mautic-consent-for-elementor' ) . '</h2>';
        echo '<p>' . esc_html__( 'If Polylang is active, the consent text is automatically registered as a translatable string. To translate it:', 'mautic-consent-for-elementor' ) . '</p>';
        echo '<ol>';
        echo '<li>' . esc_html__( 'Save the consent text on the Consent text tab.', 'mautic-consent-for-elementor' ) . '</li>';
        echo '<li>' . esc_html__( 'In the WordPress sidebar, go to Languages → Strings translations.', 'mautic-consent-for-elementor' ) . '</li>';
        echo '<li>' . esc_html__( 'Filter by group "Mautic Consent for Elementor" to find the consent_text string.', 'mautic-consent-for-elementor' ) . '</li>';
        echo '<li>' . esc_html__( 'Enter translations for each language. The frontend will pick the right one based on the page language.', 'mautic-consent-for-elementor' ) . '</li>';
        echo '</ol>';
        echo '<p>' . esc_html__( 'For other multilingual plugins or custom switching logic, use the wpme_consent_text filter — it receives the rendered text and lets you replace it with anything.', 'mautic-consent-for-elementor' ) . '</p>';

        echo '<p style="margin-top:30px;font-size:12px;color:#666">'
            . esc_html__( 'Required custom fields detected by the plugin:', 'mautic-consent-for-elementor' )
            . ' <code>' . esc_html( implode( '</code>, <code>', $required_fields ) ) . '</code>'
            . '</p>';

        echo '</div>';
    }

    private function row( string $label, string $field ): void
    {
        printf( '<tr><th scope="row">%s</th><td>%s</td></tr>', esc_html( $label ), $field );
    }

    public function ajax_test_connection(): void
    {
        check_ajax_referer( 'wpme_test_connection', 'nonce' );
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_send_json_error( [ 'message' => __( 'Permission denied', 'mautic-consent-for-elementor' ) ], 403 );
        }

        $creds = $this->settings->credentials();
        if ( $creds['mautic_url'] === '' || $creds['client_id'] === '' || $creds['client_secret'] === '' ) {
            wp_send_json_error( [ 'message' => __( 'Credentials are missing in settings.', 'mautic-consent-for-elementor' ) ] );
        }

        try {
            $client  = new MauticClient( $creds['mautic_url'], $creds['client_id'], $creds['client_secret'], $creds['http_timeout'] );
            $client->invalidate_token();
            $token = $client->get_token();
            $missing = $client->validate_setup();
            if ( $missing !== [] ) {
                wp_send_json_error( [ 'message' => sprintf( __( 'Missing fields in Mautic: %s', 'mautic-consent-for-elementor' ), implode( ', ', $missing ) ) ] );
            }
            wp_send_json_success( [ 'message' => __( 'Connection OK. Token obtained, all required fields present.', 'mautic-consent-for-elementor' ) ] );
        } catch ( \Throwable $e ) {
            wp_send_json_error( [ 'message' => $e->getMessage() ] );
        }
    }
}
