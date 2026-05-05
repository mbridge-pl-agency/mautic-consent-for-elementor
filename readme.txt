=== Mautic Consent for Elementor ===
Contributors: wilczynskiwm
Tags: mautic, elementor, gdpr, consent, newsletter
Requires at least: 6.4
Tested up to: 6.7
Requires PHP: 8.1
Stable tag: 0.1.0
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

= 0.1.0 =
* Initial release.
