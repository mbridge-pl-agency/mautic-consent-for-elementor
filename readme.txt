=== Mautic Consent for Elementor ===
Contributors: wilczynskiwm
Tags: mautic, elementor, gdpr, consent, newsletter
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.1.6
License: GPLv2 or later

Auto-injects a marketing-consent checkbox into all Elementor Pro forms and syncs opt-ins to Mautic via OAuth2 API.

== Description ==

Adds a configurable marketing-consent checkbox to every Elementor Pro form on your site. When the checkbox is ticked at submit time, the plugin creates or updates a contact in Mautic, adds them to a configured segment, and tags them with the form's name.

Features:
* Auto-injection — no per-form setup required
* Per-form on/off toggle in admin UI
* GDPR-aware — stores consent timestamp, source, and IP in Mautic
* Fail-open — Mautic outage never blocks form submission

== Setup ==

1. In Mautic, enable API and create OAuth 2 credentials.
2. Create a segment for newsletter subscribers; note its ID.
3. Create four custom contact fields: elementor_consent (Boolean), elementor_consent_date (DateTime), elementor_consent_source (Text 255), elementor_consent_ip (Text).
4. In WP admin → Settings → Mautic Consent, fill the form and click Test Connection.

== Changelog ==

= 0.1.6 =
* Inject the consent checkbox before ANY Elementor reCAPTCHA field type (v2 visible, v3 invisible widget), not just the reCAPTCHA info text. Now the visual order is: form fields → consent → reCAPTCHA elements → submit.

= 0.1.5 =
* Improved page URL detection in `elementor_consent_source` field — now uses Elementor's post_id (most reliable) with fallback to wp_get_referer() and HTTP_REFERER. Pages where browsers strip the referer (due to referrer-policy or AJAX context) now correctly capture the URL.

= 0.1.4 =
* Fixed checkbox HTML structure to truly match Elementor's native acceptance field — added the `elementor-field-type-acceptance` class on the outer wrapper and properly separated the `elementor-field-subgroup` into its own inner div. Without these changes, Elementor's native acceptance-field CSS didn't fully apply.

= 0.1.3 =
* Inject the consent checkbox BEFORE Elementor's reCAPTCHA info field (when present), so the visual order is: form fields → consent → reCAPTCHA notice → submit. Falls back to existing pre-submit injection when no reCAPTCHA info is detected.

= 0.1.2 =
* Refactored injected checkbox HTML to match Elementor's native acceptance field structure (uses elementor-field-subgroup, elementor-field-option, elementor-acceptance-field classes). The checkbox now inherits Elementor's native styling automatically — no inline styles needed. Custom CSS may need adjustment if it targets the old structure.

= 0.1.1 =
* Polylang integration: consent text is now translatable via Languages → Strings translations.
* Added `wpme_consent_text` filter for custom multilingual logic / other plugins.

= 0.1.0 =
* Initial release.
