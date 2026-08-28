=== Octanist ===
Contributors: octanist
Tags: tracking, analytics, forms, leads, conversions
Requires at least: 6.0
Tested up to: 7.0
Stable tag: 4.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

First-party proxy for the Octanist pixel. Serves the tracking script from your own domain and captures form submissions server-side.

== Description ==

Octanist is a conversion tracking platform that bridges online marketing with offline sales. This plugin is the official connector for WordPress.

### How it works

The plugin acts as a first-party proxy for the Octanist pixel:

*   **Pixel proxy.** The tracking script is served from your own WordPress site (`/wp-json/oct/p`) from a local cache and refreshed from Octanist in the background. No third-party domain, no DNS setup, no ad-blocker signal to match on.
*   **Event proxy.** Events from the pixel POST to `/wp-json/oct/e`; the plugin forwards them immediately with a one-second upstream timeout so onboarding and live reporting stay responsive. Pixel failures are not persisted in WordPress, which prevents an upstream outage from filling the site database.
*   **Call tracking proxy.** The pixel can request a dynamic number at `/wp-json/oct/call-tracking/assign`. The plugin forwards that request to Octanist so website phone numbers stay first-party.
*   **Server-side form capture.** Form submissions from Gravity Forms, Contact Form 7, WPForms, Ninja Forms, Elementor Pro, Fluent Forms, Formidable Forms, Forminator, SureForms, and Divi Contact Form are captured server-side via action hooks and forwarded synchronously with a longer timeout. Confirmed failures are queued for retry.

### Setup

1. Install and activate the plugin.
2. Go to **Settings → Octanist**.
3. Paste your measurement ID.
4. Done.

That's it. No field mapping. No DNS configuration. Listener mode and consent mode are optional toggles.

### Data privacy

*   **Octanist account required.** You need an active account at octanist.com.
*   **You are the data controller.** Consent capture and privacy-policy disclosure are your responsibility. A consent management platform (CMP) is recommended.

== Installation ==

1. Upload the `octanist` folder to `/wp-content/plugins/`.
2. Activate the plugin via the Plugins screen.
3. Go to **Settings → Octanist** and paste your measurement ID.

== Frequently Asked Questions ==

= Do I need a custom subdomain or DNS setup? =

No. The plugin proxies the pixel through your WordPress site itself, so everything is first-party automatically.

= Which form plugins are supported? =

Gravity Forms, Contact Form 7, WPForms, Ninja Forms, Elementor Pro Forms, Fluent Forms, Formidable Forms, Forminator, SureForms, and Divi Contact Form.

= How are form submissions captured? =

Via server-side action hooks on each supported plugin, not by intercepting the form in the browser. This works with AJAX forms and is resilient to theme/plugin conflicts.

= What happens if the Octanist service is temporarily unreachable? =

Pixel events are forwarded immediately through the first-party WordPress endpoint and are not persisted locally when upstream is unavailable. Server-side form listeners wait for the upstream HTTP response. Failed form submissions are queued separately and retried with backoff through WP-Cron. A health panel in settings shows the last activity and queued form count.

== Changelog ==

= 4.1.0 =
*   **NEW:** Optional call tracking setting (off by default). When enabled, the pixel can replace website phone numbers.
*   **NEW:** First-party proxy for call tracking assignment (`POST /wp-json/oct/call-tracking/assign`).
*   **NEW:** Setup codes can include a call tracking flag (`OCTA1.OCT-XXXXXXXX.s.a.t`).

= 4.0.1 =
*   **PERFORMANCE:** The pixel endpoint serves its local cache immediately and refreshes Octanist upstream in the background.
*   **UPGRADE:** Existing v4 caches are migrated and refreshed through a non-blocking runtime upgrade routine.
*   **PERFORMANCE:** Pixel events use an immediate first-party forwarding path with a one-second timeout and are never stored in the WordPress retry queue.
*   **RELIABILITY:** Server-side form listeners wait for the upstream HTTP response with a longer timeout.
*   **RELIABILITY:** Only failed form submissions are persisted for retry, with an early retry wake-up in addition to the hourly safety job.
*   **RELIABILITY:** The form retry queue locks writes to reduce concurrent overwrite risk and no longer silently evicts older submissions when full.

= 4.0.0 =
*   **RELEASE:** Stable release for the Octanist Pixel rollout.
*   **COMPATIBILITY:** Tested up to WordPress 7.0.

= 3.0.0 =
*   **MAJOR REWRITE.** The plugin is now a first-party proxy for the new Octanist pixel.
*   **NEW:** Pixel and event traffic proxied through WordPress (`/wp-json/oct/p` and `/wp-json/oct/e`), fully first-party, no custom domain required.
*   **NEW:** Server-side form capture via action hooks for Gravity Forms, Contact Form 7, WPForms, Ninja Forms, Elementor Pro, Fluent Forms, Formidable Forms, Forminator, SureForms, and Divi Contact Form.
*   **NEW:** Failed server-side form forwards are queued and retried with WP-Cron.
*   **NEW:** One-field setup. Paste your measurement ID and you're done.
*   **NEW:** Client-signal forwarding (IP, country, UA, referer, accept-language) so upstream attribution stays accurate even when the request is proxied.
*   **REMOVED:** Field mapping, AJAX/standard submission mode toggle, debug mode, datalayer option. All gone.
*   **MIGRATION:** v2 field-mapping settings are preserved as `octanist_settings_legacy` for one release; a one-time admin notice explains the new setup.

= 2.0.1 =
*   **FIX:** Plugin settings are no longer deleted on deactivation.
*   **IMPROVEMENT:** Tested up to WordPress 6.9.4.

= 2.0.0 =
*   **MAJOR REFACTOR:** WordPress Settings API, new UI, dynamic field mappings, debug mode.

= 1.0.0 =
*   Initial release.
