# Octanist WordPress Plugin

**Contributors:** octanist
**Tags:** tracking, analytics, forms, leads, conversions
**Requires at least:** 6.0
**Tested up to:** 7.0
**Stable tag:** 4.0.1
**License:** GPLv2 or later
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

First-party proxy for the Octanist pixel. Serves the tracking script from your own domain and captures form submissions server-side.

## Description

Octanist is a conversion tracking platform that bridges online marketing with offline sales. This plugin is the official connector for WordPress.

## How It Works

The plugin acts as a first-party proxy for the Octanist pixel:

- **Pixel proxy:** The tracking script is served from your own WordPress site at `/wp-json/oct/p` from a local cache, with refreshes handled in the background.
- **Event proxy:** Events from the pixel POST to `/wp-json/oct/e`; the plugin forwards them immediately with a one-second upstream timeout so onboarding and live reporting stay responsive. Pixel failures are not persisted in WordPress, which prevents an upstream outage from filling the site database.
- **Server-side form capture:** Form submissions from supported plugins are captured via server-side action hooks and forwarded synchronously with a longer timeout. Confirmed failures are queued for retry.

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

## Changelog

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
