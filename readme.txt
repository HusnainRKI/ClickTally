=== ClickTally - Element Event Tracker ===
Contributors: clicktallyteam
Tags: analytics, tracking, privacy, clicks, events
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPL v2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight, privacy-first WordPress plugin that tracks clicks and events on elements without requiring GTM/GA.

== Description ==

ClickTally is a privacy-first analytics plugin that lets you track clicks and events on your WordPress site without relying on Google Analytics or Google Tag Manager. Perfect for site owners who want to understand user behavior while respecting privacy.

**Key Features:**

* **Privacy-First**: No cookies by default, hashed IPs, respects Do Not Track
* **Lightweight**: Front-end tracker is less than 2KB gzipped
* **Easy Setup**: 3-field rule builder with DOM picker
* **Auto-Tracking**: Automatically tracks buttons, outbound links, downloads, and contact links
* **Powerful Dashboard**: View click analytics with filtering by date, device, and user type
* **Custom Elements**: Track any element using ID, Class, CSS Selector, XPath, or Data Attributes

**What You Can Track:**

* Button clicks
* Link clicks (internal and external)
* Form submissions
* File downloads
* Element views (using Intersection Observer)
* Custom events on any element

**Privacy Features:**

* No cookies stored by default
* IP addresses are hashed server-side
* User agents are hashed for privacy
* Respects Do Not Track headers
* Optional session tracking (can be disabled)
* GDPR and privacy-compliant

**Dashboard Features:**

* Click analytics for last 7 or 30 days
* Top clicked elements with percentages
* Top pages by clicks
* Filter by device type (desktop/mobile/tablet)
* Filter by user type (guests/logged-in users)
* Export data to CSV

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/clicktally` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Use the ClickTally admin menu to configure tracking rules and view analytics.

== Frequently Asked Questions ==

= Does this plugin use cookies? =

No, ClickTally does not use cookies by default. There's an optional session tracking feature that uses localStorage, but this can be disabled in settings.

= Is this GDPR compliant? =

Yes, ClickTally is designed with privacy in mind. It hashes IP addresses, respects Do Not Track headers, and provides data export/erasure functionality for privacy compliance.

= Can I track custom elements? =

Yes! You can create tracking rules for any element using various selector types including ID, Class, CSS Selector, XPath, or Data Attributes.

= Does this affect site performance? =

ClickTally is designed to be lightweight. The front-end tracking script is less than 2KB gzipped and uses efficient event delegation. Data processing happens asynchronously to avoid impacting page load times.

= Can I export my data? =

Yes, you can export your analytics data to CSV format with respect to any filters you've applied (date range, device type, user type).

== Screenshots ==

1. Dashboard overview showing click analytics and top elements
2. Tracking rules management interface
3. Rule creation modal with 3-field builder
4. Settings page with privacy and auto-tracking options
5. Test mode for previewing tracking rules

== Changelog ==

= 1.0.0 =
* Initial release
* Privacy-first click tracking
* 3-field rule builder
* Auto-tracking for common elements
* Dashboard with analytics
* CSV export functionality
* Test mode for rule validation

== Upgrade Notice ==

= 1.0.0 =
Initial release of ClickTally. A privacy-first alternative to Google Analytics for tracking clicks and events.

== Privacy Policy ==

ClickTally collects and processes the following data for analytics purposes:

* Page URLs (for understanding where clicks occur)
* Click timestamps (for time-based analytics)
* Hashed IP addresses (for basic analytics, not for identification)
* Hashed user agents (for device detection)
* User login status (logged in vs guest)
* Referrer information (where users came from)
* UTM parameters (for campaign tracking)

All personal data is hashed and cannot be used to identify individual users. No cookies are stored by default. The plugin respects Do Not Track headers and provides data export/erasure functionality for privacy compliance.

== External Services ==

ClickTally does not connect to any external services by default. All data processing and storage occurs locally on your WordPress installation. No data is transmitted to third-party services, CDNs, or external analytics platforms.

== Support ==

For support, feature requests, and bug reports, please visit the plugin's GitHub repository or contact the development team through the WordPress.org support forums.

== Development ==

ClickTally is open source and welcomes contributions. The plugin follows WordPress coding standards and includes comprehensive documentation for developers.

Filter hooks available:
* `clicktally_should_track` - Control whether tracking should occur
* `clicktally_event_payload` - Modify event data before storage
* `clicktally_autotrack_selectors` - Customize auto-tracking rules

Action hooks available:
* `clicktally_event_tracked` - Fired when an event is successfully tracked
* `clicktally_rollup_complete` - Fired when data rollup is complete