=== PGP Encryption for Contact Page ===
Contributors: jwebber74
Donate link: https://buymeacoffee.com/stanghellini
Tags: pgp, encryption, contact form, security, turnstile
Requires at least: 6.0
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.1.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

PGP-encrypted contact form with optional Cloudflare Turnstile CAPTCHA, for secure, spam-protected email from your WordPress site.

== Description ==
This plugin enables PGP encryption for a built-in contact form on your WordPress site, ensuring that messages are encrypted using your public key before being emailed. It now includes optional Cloudflare Turnstile CAPTCHA to prevent spam, along with debugging controls for troubleshooting. Ideal for maintaining privacy and security in user communications.

Requires PHP 7.4 or later (tested up to PHP 8.4) and the PHP gnupg extension. See Requirements below.

**Key Features:**
* Simple shortcode-based contact form: `[pgp_contact_form]`
* Display your public key: `[pgp_public_key]`
* Admin settings for configuring PGP public key, recipient email, and optional Cloudflare Turnstile
* Enable/disable Turnstile CAPTCHA via a checkbox in Settings > PGP Encryption
* Enable/disable detailed debugging logs (no `WP_DEBUG` needed); logs go to a private, protected plugin log file with email addresses redacted, and can be viewed or deleted from the settings page
* Optional custom admin URL for WordPress admin login obfuscation
* AJAX-powered form submission for seamless user experience
* Responsive, styled form that integrates with your theme
* Uses the secure GnuPG PHP extension for encryption (requires server support)

Once installed, configure the plugin in **Settings > PGP Encryption**, then add the shortcodes to any page or post.

== Requirements ==
* WordPress 6.0 or later (tested up to 7.1).
* PHP 7.4 or later. Tested up to PHP 8.4 (8.4.26).
* The PHP `gnupg` extension (PECL gnupg), which uses the GnuPG library on the server. Tested with gnupg extension 1.5.2 (GPGME 1.24.2) and GnuPG 2.4.7.

== Installation ==
1. Upload the `pgp-encryption-for-contact-page` folder to the `/wp-content/plugins/` directory.
2. Activate the plugin through the 'Plugins' menu in WordPress.
3. Go to **Settings > PGP Encryption** and:
   - Paste your armored PGP public key (starting with "-----BEGIN PGP PUBLIC KEY BLOCK-----").
   - Set the recipient email.
   - Optionally enable Cloudflare Turnstile and enter your Site Key and Secret Key (obtain from [Cloudflare Dashboard](https://dash.cloudflare.com/turnstile)).
   - Optionally enable debugging logs for troubleshooting.
4. Add the shortcode `[pgp_contact_form]` to a page or post to display the encrypted contact form.
5. Optionally, add `[pgp_public_key]` to show your public key for users.

**Note:** Your server must have the GnuPG PHP extension enabled. Check with your host if encryption fails.

== Frequently Asked Questions ==

= What are the server requirements for this plugin to run? =
This plugin requires the following PHP extensions to be enabled on your webserver: `gnupg`, `json`, `mbstring`, `openssl`.

= How do I implement this contact form? =
1. Configure the plugin in **Settings > PGP Encryption** in your WordPress admin panel.
2. Use the shortcode `[pgp_contact_form]` to embed a responsive, styled form on any page or post.

= How do I enable Cloudflare Turnstile CAPTCHA? =
1. Go to **Settings > PGP Encryption**.
2. Check the **Enable Cloudflare Turnstile** checkbox.
3. Enter your Site Key and Secret Key from the [Cloudflare Dashboard](https://dash.cloudflare.com/turnstile).
4. Save settings. The CAPTCHA will appear on the `[pgp_contact_form]` shortcode.

= Does this plugin work with other contact form plugins? =
Maybe, but unsupported. This is a standalone contact form. It has been tested with Contact Form 7 for encryption but is not guaranteed to work with other plugins. Advanced users can extend the `PGP_ECP_Frontend` class for custom integrations.

= What if the GnuPG extension is not available? =
The plugin will log errors and disable encryption. Contact your hosting provider to enable the `gnupg` PHP extension.

= How do I get a PGP public key? =
Generate one using tools like GnuPG (`gpg --gen-key`) or services like ProtonMail, then export it in armored format (`gpg --armor --export your-email dot com`) and paste it into the plugin’s admin settings.

= How do I enable debugging logs? =
**Important:** "Enable Debugging Logs" only enables the plugin's own private log in `wp-content/uploads/pgp-ecp-logs/`. It does NOT enable WordPress's built-in debugging (`WP_DEBUG` in `wp-config.php`), does not change `WP_DEBUG`, and does not write to `wp-content/debug.log`. Email addresses are redacted in the log. Turn the option off when you have finished troubleshooting.

1. Go to **Settings > PGP Encryption** and check **Enable Debugging Logs**.
2. Logs are written to the plugin's own file in `wp-content/uploads/pgp-ecp-logs/` (random file name, protected by `index.php` and a deny-all `.htaccess`; on Nginx, also deny that folder in your server config). The path and size are shown on the settings page.
3. Email addresses are always redacted in logs (e.g. `A********@*****.com`) and message contents are never logged.
4. Use **View debug log** (opens the most recent 500 KB in a new tab, administrators only) or **Delete debug log** on the settings page. If the log folder cannot be written, the settings page shows the reason. Neither button touches `wp-content/debug.log`. Front-end debug messages also appear in the browser console.

= Is the form secure? =
Yes, it uses WordPress nonces, sanitization, PGP encryption, and optional Cloudflare Turnstile CAPTCHA. Always keep WordPress and plugins updated for maximum security.

== External services ==

This plugin can connect to Cloudflare Turnstile, a CAPTCHA service, to protect the contact form from spam. It is disabled by default and is only used when an administrator enables "Enable Cloudflare Turnstile" and enters Turnstile keys in Settings > PGP Encryption.

* When enabled, the Turnstile script (https://challenges.cloudflare.com/turnstile/v0/api.js) is loaded on front-end pages. Cloudflare requires this script to be loaded from its servers; it cannot be bundled with the plugin. The visitor's browser sends data to Cloudflare (such as IP address, browser characteristics and interaction signals) so Cloudflare can decide whether the visitor is human.
* When the form is submitted, the server sends the Turnstile token and your Turnstile secret key to https://challenges.cloudflare.com/turnstile/v0/siteverify to verify it.

Service provided by Cloudflare, Inc.: [Terms of Service](https://www.cloudflare.com/website-terms/), [Privacy Policy](https://www.cloudflare.com/privacypolicy/), [Turnstile privacy addendum](https://www.cloudflare.com/turnstile-privacy-policy/).

== Credits ==

* The "Buy me a coffee" button on the settings page is plain local HTML/CSS. No Buy Me a Coffee script or remote font is loaded; it is only a link to https://buymeacoffee.com/stanghellini.
* Cookie font by Ania Kruk, licensed under the SIL Open Font License 1.1. It is bundled unmodified as `assets/fonts/Cookie-Regular.ttf`, with the license in `assets/fonts/Cookie-OFL.txt`. Source: https://github.com/google/fonts/tree/main/ofl/cookie (commit 90abd17b4f97671435798b6147b698aa9087612f).

== Screenshots ==
1. Admin settings page for configuring PGP key, Turnstile, and debugging.
2. Frontend contact form with optional Cloudflare Turnstile CAPTCHA.

== Changelog ==
= 2.1.0 =
* WordPress.org Plugin Check compliance: GPL-2.0-or-later license, readme header fixes, escaping, sanitization and i18n (text domain pgp-encryption-for-contact-page).
* Debug logging (PHP and browser console) only runs when "Enable Debugging Logs" is checked; WP_DEBUG is no longer required.
* Removed duplicate inline admin script; admin stylesheet is now loaded on the settings page.
* Documented the optional Cloudflare Turnstile external service.
* Privacy: email addresses are redacted in all debug output (pgp_ecp_redact_email() plus automatic scrubbing of every log line).
* Debug output now goes to a private plugin log file in wp-content/uploads/pgp-ecp-logs/ instead of the shared debug.log.
* Added a local "Buy me a coffee" button to the settings page (no remote script; bundled Cookie font, SIL OFL 1.1).
* Added "View debug log" and "Delete debug log" buttons to the settings page. Uninstall removes the log folder.

= 2.0.0 =
* Added Cloudflare Turnstile CAPTCHA integration with enable/disable checkbox.
* Added debugging control with **Enable Debugging Logs** checkbox.
* Updated version to 2.0.0.
* Improved form submission reliability and error handling.

= 1.0.0 =
* Initial release with PGP encryption support for contact forms.

== Upgrade Notice ==
= 2.1.0 =
Compliance and security hardening release (GPL-2.0-or-later, escaping, sanitization, i18n). Email addresses are redacted in debug logs, which move to a private plugin log file with View and Delete buttons; WP_DEBUG is no longer required. Requires PHP 7.4+ and gnupg.

= 2.0.0 =
Adds optional Cloudflare Turnstile CAPTCHA and debugging controls in Settings > PGP Encryption. Requires the GnuPG PHP extension.

= 1.0.0 =
Initial version. No upgrades needed.