# Mautic Consent for Elementor

A WordPress plugin that automatically injects a marketing-consent checkbox into every Elementor Pro form on your site and syncs opt-ins to Mautic via OAuth2 API.

## What it does

When a visitor submits an Elementor form with the consent checkbox ticked, the plugin:

- **Creates or updates a contact in Mautic** — search-then-PATCH-or-POST flow, no duplicates by email
- **Adds the contact to a configured segment** — your "Newsletter Subscribers" or whatever you set up
- **Tags them with the source form name** — e.g. `wp-form-contact`, so marketers can segment by entry point
- **Records GDPR consent fields** — timestamp, source (form name + page URL), IP — stored as custom contact fields in Mautic for audit purposes

If Mautic is offline, slow, or returns an error, the form submission **proceeds normally**. Fail-open by design — Mautic outage never blocks user from sending the form.

## Why this plugin

Elementor Pro forms have no built-in marketing-consent checkbox. Adding one manually to each form is tedious, error-prone, and easy to forget on new forms. This plugin:

- Adds the checkbox **automatically to every form** — no per-form configuration
- Lets you **disable** it on specific forms (e.g. HR contact, complaint forms) from the admin
- Lets you **edit the consent text** in one place (with a `<a>` link to your privacy policy)
- Lets you **add custom CSS** for visual tweaks
- **Validates your Mautic setup** with a one-click "Test connection" button (verifies OAuth + that all 4 required custom fields exist)
- Runs in **English** by default, **i18n-ready** for translations (textdomain `mautic-consent-for-elementor`)

## Status

**v0.1.0** — initial release. 49 unit tests passing. Production-hardened: defensive try/catch on every WordPress hook so any internal failure cannot break Elementor form rendering or submission.

Tested against PHP 8.1+, WordPress 6.4+, Elementor Pro 3.20+, Mautic 4.x and 5.x.

## Setup overview

**On the Mautic side:**

1. Enable the API: Settings → Configuration → API Settings
2. Create OAuth2 credentials: Settings → API Credentials → New
3. Create a segment for newsletter subscribers (note its ID)
4. Create 4 required custom contact fields:
   - `elementor_consent` (Yes/No)
   - `elementor_consent_date` (Datetime)
   - `elementor_consent_source` (Text, **length 255** — default 64 is too short)
   - `elementor_consent_ip` (Text, length 64)

**On the WordPress side:**

1. Upload the plugin zip via Plugins → Add New → Upload Plugin
2. Activate
3. Settings → Mautic Consent → fill in URL, credentials, segment ID
4. Click "Test connection and Mautic fields" — verifies OAuth and that the 4 custom fields exist
5. Edit the consent checkbox text in the **Consent text** tab
6. Optional: add custom CSS in the **Style** tab
7. Optional: disable the checkbox on specific forms in the **Forms** tab

The plugin's **Help / Setup** tab walks admins through this in-app — useful for handing the plugin off to non-developers.

## Double Opt-In

Handled entirely on the Mautic side via Campaigns. The plugin writes the contact + initial consent record; Mautic Campaigns handle the confirmation email, tracking the click, and updating contact state. No plugin code changes needed for DOI.

## Building a distribution zip

For deployment to production WordPress sites without Composer:

```bash
composer install --no-dev --optimize-autoloader
cd ..
zip -r mautic-consent-for-elementor.zip mautic-consent-for-elementor \
    -x 'mautic-consent-for-elementor/.phpunit.cache/*' \
    -x 'mautic-consent-for-elementor/tests/*' \
    -x 'mautic-consent-for-elementor/composer.lock' \
    -x 'mautic-consent-for-elementor/phpunit.xml.dist'
```

Note: clone the repo into a folder named `mautic-consent-for-elementor/` for the zip command to produce the correct paths. WordPress expects the plugin folder name to match the slug.

## Development

```bash
composer install
vendor/bin/phpunit
```

Requires PHP 8.1+, Composer 2.

Tests use PHPUnit 10 with [brain/monkey](https://github.com/Brain-WP/BrainMonkey) for mocking WordPress functions — no full WordPress install needed for the test suite.

## Manual E2E checklist

Run through this on a real WP+Elementor+Mautic stack before each release:

1. **Happy path:** Submit a form with the consent checkbox ticked → verify in Mautic: contact created, in segment, tagged `wp-form-<slug>`, all 4 consent fields populated.
2. **Update path:** Submit again with same email → verify: contact updated (PATCH), tag NOT duplicated, fields refreshed, segment membership unchanged.
3. **No consent:** Submit without ticking → verify: NO API call to Mautic (check Logs tab — no row).
4. **Mautic offline:** Stop Mautic, submit with consent → verify: form submission still succeeds for the user, error logged in admin → Logs tab.
5. **Optimized Markup:** Toggle Elementor Settings → Features → Optimized DOM. Re-test happy path.
6. **Per-form toggle:** Disable a specific form in the Forms tab → reload page with that form → verify checkbox absent. Other forms still show checkbox.
7. **Constants override:** Add `define('WPME_MAUTIC_CLIENT_SECRET', '...')` to `wp-config.php` → verify: secret field in admin is disabled with a notice; Test Connection still works.
8. **Test Connection — missing fields:** Delete one of the four custom fields in Mautic → click Test Connection → verify error message lists the missing alias.

## Architecture

Single plugin folder, PHP 8.1+, namespaced under `WPME\` (PSR-4 autoload from `includes/`). Component classes:

- `Plugin` — singleton bootstrap; registers WP hooks; defensive try/catch on every callback
- `Activator` — creates the `wpme_logs` table on activation
- `Logger` — masks email + writes to `wpme_logs` + prunes to 100 rows
- `FieldMapper` — pure conversion of Elementor fields → Mautic contact body (id-convention aliases)
- `MauticClient` — OAuth2 client_credentials with transient token cache, search/create/update/segment add, validate setup
- `FormScanner` — walks `_elementor_data` JSON to discover form widgets, transient cached
- `FormInjector` — regex injection of consent checkbox HTML; handles standard markup and Elementor's "Optimized Markup" mode; uses `preg_replace_callback` to avoid PCRE `$N` corruption
- `Settings` — read-only access to `wp_options` with `WPME_MAUTIC_CLIENT_ID/SECRET` constants override
- `SubmissionHandler` — orchestrator running in `elementor_pro/forms/new_record`; reads consent from `$_POST`, builds payload with 4 RODO fields, fail-open via try/catch
- `Admin\Admin` + `Admin\SettingsPage` — 6-tab admin UI (Mautic / Consent text / Style / Forms / Logs / Help) + Test Connection AJAX

## License

GPL-2.0-or-later
