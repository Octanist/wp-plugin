# Octanist WordPress Plugin

**Contributors:** octanist
**Tags:** tracking, analytics, forms, leads, conversions
**Requires at least:** 6.0
**Tested up to:** 7.1
**Stable tag:** 4.1.2
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

First-party proxy for the Octanist pixel. Serves the tracking script from your own domain and captures form submissions server-side.

## Description

Octanist is a conversion tracking platform that bridges online marketing with offline sales. This plugin is the official connector for WordPress.

## How It Works

The plugin acts as a first-party proxy for the Octanist pixel:

- **Pixel proxy:** The tracking script is served from your own WordPress site at `/wp-json/oct/p` from a local cache, with refreshes handled in the background.
- **Event proxy:** Events from the pixel POST to `/wp-json/oct/e`; the plugin forwards them immediately with a one-second upstream timeout so onboarding and live reporting stay responsive. Pixel failures are not persisted in WordPress, which prevents an upstream outage from filling the site database.
- **Call tracking proxy:** The pixel can request a dynamic number at `/wp-json/oct/call-tracking/assign`. The plugin forwards that request to Octanist so website numbers stay first-party.
- **Server-side form capture:** Form submissions from supported plugins are captured via server-side action hooks and forwarded synchronously with a longer timeout. Confirmed failures are queued for retry.
- **Custom forms:** Forms built in your own theme or plugin code are sent with the `octanist_track_form` action.

Supported form plugins:

- Gravity Forms
- Contact Form 7
- WPForms
- Ninja Forms
- Elementor Pro Forms
- Fluent Forms
- Formidable Forms
- Forminator
- SureForms
- Divi Contact Form

## Setup

1. Install and activate the plugin.
2. Go to **Settings > Octanist**.
3. Paste your Octanist setup code or measurement ID.
4. Save the settings.

Listener mode and consent mode are optional settings.

## Custom forms

For a form that none of the supported plugins handle, call the `octanist_track_form` action in the code that processes the submission, once it is accepted:

```php
do_action('octanist_track_form', 'whitepaper_download', [
    'name'  => sanitize_text_field(wp_unslash($_POST['name'] ?? '')),
    'email' => sanitize_email(wp_unslash($_POST['email'] ?? '')),
]);
```

- **Form name:** the name the form gets in Octanist. Keep it stable; a new name shows up as a new form to map.
- **Fields:** field name => submitted value. Keys starting with `_` and sensitive keys (passwords, nonces, card numbers) are dropped.
- **Session:** read from the same request, so leads keep their attribution: the `octa_sid` field the pixel adds to every form, or the `X-Octanist-Session-Id` header it adds to same-origin AJAX calls. Call the action while handling the visitor's request, not from a background job, which would send the server's IP and user agent instead of the visitor's.

The action only sends in server-side listener mode. In browser mode the pixel captures the form itself. When the plugin is inactive the action does nothing, so the call is safe to leave in place.

## Changelog

### 4.2.0

- New `octanist_track_form` action to send custom-coded forms to Octanist from your own PHP code, with the Octanist session read from the same request.

### 4.1.2

- Form capture accepts only valid Octanist session and client IDs, so oversized values sent with a form submission can no longer slow down the site.

### 4.1.1

- The pixel endpoint now downloads the tracking script immediately when the local cache is empty, instead of waiting for WP-Cron.
- Pixel downloads use an 8-second timeout so shared hosts can complete TLS and the script fetch.
- The empty-cache stub is no longer publicly cacheable, so CDNs cannot pin the warming comment.
- Failed or invalid upstream responses enter a cooldown and in-flight lock so visitor requests do not stampede.
- Settings save no longer blocks sanitization on a remote request; an empty cache is filled after the option is stored.

### 4.1.0

- Added an optional Call tracking setting, off by default.
- Added a first-party proxy for call tracking assignment (`POST /wp-json/oct/call-tracking/assign`).
- Setup codes can include a call tracking flag (`OCTA1.OCT-XXXXXXXX.s.a.t`).
- Tested up to WordPress 7.1.

### 4.0.1

- Pixel serving now uses the local cache immediately and refreshes upstream in the background.
- Existing v4 caches are migrated and refreshed through a non-blocking runtime upgrade routine.
- Pixel events use an immediate first-party forwarding path with a one-second timeout and are never stored in the WordPress retry queue.
- Server-side form listeners wait for the upstream HTTP response with a longer timeout.
- Only failed form submissions are persisted for retry, with an early retry wake-up in addition to the hourly safety job.
- The form retry queue locks writes to reduce concurrent overwrite risk and no longer silently evicts older submissions when full.

### 4.0.0

- Stable release for the Octanist Pixel rollout.
- Tested up to WordPress 7.0.

### 3.0.0

- Major rewrite for the new Octanist pixel.
- Added first-party pixel and event proxy routes.
- Added server-side form capture for popular form plugins.
- Added failed submission retries through WP-Cron.
- Replaced field mapping setup with one setup code or measurement ID.

### 2.0.1

- Plugin settings are no longer deleted on deactivation.

### 2.0.0

- Refactored the plugin to use the WordPress Settings API.

### 1.0.0

- Initial release.
