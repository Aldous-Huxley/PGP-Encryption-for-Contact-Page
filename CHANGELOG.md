# Changelog

All notable changes to **PGP Encryption for Contact Page** are documented here.
The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses [Semantic Versioning](https://semver.org/).

## [2.1.0] - 2026-10-10

2.1.0 is a compliance, security and privacy release. The plugin passes the official WordPress Plugin Check with no errors, and PHPCS with the WordPress Coding Standards with no errors or warnings.
- The license is now GPL-2.0-or-later and the readme was corrected.
- All output is escaped, all input is unslashed and sanitized, and all strings are translatable.
- Debug logging was rebuilt:
  - it is controlled only by the admin option;
  - it writes to a private, protected plugin log file;
  - email addresses are always redacted;
  - it can be viewed and deleted from the settings page.
- A local "Buy me a coffee" button was added, and the donation link moved from PayPal to Buy Me a Coffee.
- The PGP encryption, contact form, Turnstile CAPTCHA and settings work as before. Option names are unchanged.

### Added
- **Private debug log:** written to `wp-content/uploads/pgp-ecp-logs/debug-<random>.log`.
  - The file name comes from the site's secret salts via `wp_hash()`, so it can't be guessed.
  - The folder is protected by `index.php` and a deny-all `.htaccess`.
- **View debug log** button on the settings page. It opens the log in a new tab and needs the `manage_options` capability and a nonce.
  - It shows at most the most recent 500 KB, with a note when the log was cut.
  - Content is HTML-escaped and sent with no-cache, nosniff, noindex and a strict Content-Security-Policy.
- **Delete debug log** button (admin-post handler, `manage_options` + nonce). It deletes only the plugin's own log with `wp_delete_file()` and shows a success, info or error notice.
- **Log status notice:** if debug is on but the log can't be written, the settings page shows the path and the reason (folder can't be created, not writable, or protection files missing).
- **Email redaction:** `pgp_ecp_redact_email()` turns `alice@example.com` into `a********@*****.com`.
  - The asterisk counts are fixed, and only the last domain label is shown.
  - Invalid input becomes `[redacted-email]`.
- **Log scrubbing:** `pgp_ecp_scrub_emails()` redacts anything that looks like an email in every log line.
- **More log detail on a successful submission:** received → nonce OK → validated → key imported → encrypted → send attempt → send result. Recipients are redacted.
- **"Buy me a coffee" button** on the settings page header. It is pure HTML/CSS with an inline SVG cup, links to https://buymeacoffee.com/stanghellini, and loads no remote script or fonts.
- **Bundled Cookie font:** `assets/fonts/Cookie-Regular.ttf`, unmodified, SIL OFL 1.1, from google/fonts commit 90abd17, with its license in `Cookie-OFL.txt`.
- **Readme sections:** `== Requirements ==`, `== External services ==` (Cloudflare Turnstile disclosure with terms and privacy links), `== Credits ==`.
- **Readme `Donate link:` header** and a `Text Domain` / `Requires at least` / `Requires PHP` / `License URI` plugin header.
- **Admin stylesheet:** `assets/css/admin.css` is now actually loaded on the settings page. It existed before but was never enqueued.

### Changed
- **License:** MIT → **GPL-2.0-or-later**, in both the plugin header and the readme.
- **Text domain:** `pgp-encryption` → **`pgp-encryption-for-contact-page`**. All user-visible PHP strings and the JS alert strings are now translatable.
- **Readme headers:**
  - `Tested up to: 7.1`, the latest WordPress release as of 2026-10-10. The invalid duplicate `Tested up to: 8.4` was removed.
  - `Requires PHP: 7.4` (was 8.2).
  - `Contributors: jwebber74`.
  - Tags cut from 7 to 5.
  - Short description shortened to 129 characters.
  - Upgrade notices are each under 300 characters.
- **Debug logging rules:**
  - Logging now depends **only** on the "Enable Debugging Logs" option. WP_DEBUG is no longer needed or used.
  - Browser console messages (frontend and admin JS) also appear only when the option is on.
- **Donate link:** PayPal → `https://buymeacoffee.com/stanghellini`. The link title no longer names the wrong plugin.
- **Turnstile script handle:** `cf-turnstile` → `pgp-ecp-cf-turnstile`. Cloudflare URLs are now class constants.
- **Code style:** every PHP file was reformatted to WordPress Coding Standards (tabs, Yoda conditions, `array()`, docblocks). Logic was kept the same, apart from the fixes listed below.
- **File header comments:** now `File:` / `Path: pgp-encryption-for-contact-page/...` (lowercase folder name). JS headers use `/* */`.
- **Settings page description text** under "Enable Debugging Logs": it explains that the option only enables the plugin's private log, does not enable or change WP_DEBUG, never writes to `debug.log`, and redacts emails.

### Fixed
- **Unescaped output on the settings page:**
  - textarea and input values are escaped;
  - checkbox states use `checked()` and `disabled()`;
  - the gnupg-missing admin notice uses `esc_html_e()`.
- **Form data:** `$_POST` values were sanitized but not unslashed. They now use `wp_unslash()` before sanitizing.
- **Nonce:** the nonce value is now sanitized before `wp_verify_nonce()`.
- **Turnstile response check:** a malformed Turnstile response no longer triggers a PHP notice (`empty( $body['success'] )`).
- **Duplicate admin script:** an inline `<script>` duplicated `admin.js` on the settings page. It was removed.
- **Settings page capability check:** the page now checks `current_user_can( 'manage_options' )` before rendering.
- **Activation error message:** the error shown when gnupg is missing is now escaped and translatable.
- **Readme:**
  - the duplicated FAQ line was removed;
  - the installation folder name was corrected;
  - the Installation and FAQ debug instructions were updated.
- **Bugs found during this work, never released:**
  - A fatal error when debug logging ran before WordPress's pluggable functions were loaded.
  - Debug logging silently doing nothing on sites with WP_DEBUG off, which was the user's live-site report.
  - Logging silently disabled on hosts whose `FS_METHOD` isn't `direct`.

### Removed
- Unconditional `error_log()` calls: error messages used to be logged even with debugging off.
- Logging to the shared PHP/WordPress error log (`wp-content/debug.log`).
- The inline Turnstile-toggle `<script>` in the settings page (it duplicated `assets/js/admin.js`).
- The duplicate `Tested up to: 8.4` readme line, the MIT license, and the PayPal donation URL.
- The per-class `is_debug_enabled()` helper methods, replaced by the shared `pgp_ecp_is_debug_enabled()` / `pgp_ecp_log()`.
- The uninstall-time log message, since the log folder is deleted during uninstall.

### Security
- **Emails never appear in full in the log.** This covers sender, recipient, and GnuPG error messages that include a key's email address, and it also applies to `print_r` dumps.
- **Message contents are never logged:** no name, subject, message text, plaintext or ciphertext. That was already true before and has been verified.
- **The log moved out of the shared `wp-content/debug.log`** into a protected, plugin-only file. If the protection files can't be created, nothing is logged.
- **View and Delete are protected:** both require `manage_options` plus a nonce, and both return 403 otherwise. The log is never reachable through a public URL.
- **Uninstall removes the log folder** as well as the option and the transient.
- **No offloaded assets:** no remote Buy Me a Coffee script and no Google Fonts. The only remote resource is the opt-in Cloudflare Turnstile CAPTCHA, which Cloudflare requires to be loaded from its servers and which is disclosed in the readme.

### Compliance (Plugin Check / WPCS)
| Check | 2.0.1 (original) | 2.1.0 |
|---|---|---|
| PHPCS `--standard=WordPress` (WPCS 3) | **1,783 errors, 111 warnings** (7 files) | **0 errors, 0 warnings** |
| Official Plugin Check 2.1.0 (test WordPress 7.1.3) | readme: 3 errors + 4 warnings; offloading: 4 errors; plus escaping, nonce/unslash and text-domain findings; **74** debug-log (`error_log`) warnings | **"No errors found"** (also with experimental + low-severity checks) |
| User-supplied report | 11 items excluding authorized debug (7 readme, 4 offloading), 93 `error_log` warnings | all fixed; the authorized `error_log` uses carry justified `phpcs:ignore` comments |
| PHPCompatibilityWP (PHP 7.4+) | not run | 0 issues |

The "debug code" (`error_log`) warning was authorized by the plugin owner, because the plugin has an admin-controlled debug feature. A single `error_log()` call remains, with a justified `phpcs:ignore` comment. It sits in `pgp_ecp_append_file()`, the only code that writes the log and its protection files.

### Upgrade notes for administrators
1. **Error messages are only logged when "Enable Debugging Logs" is on.** Before, some errors were always written to the PHP/WordPress error log.
2. **WP_DEBUG is no longer involved.** The checkbox alone turns the plugin's log on or off. It doesn't change WP_DEBUG and never writes to `wp-content/debug.log`.
3. **Logs now live in `wp-content/uploads/pgp-ecp-logs/`** with email addresses redacted. Use View/Delete on the settings page, and turn debugging off when finished. While debugging is on, a new log starts on the next request after a delete.
4. **On Nginx,** `.htaccess` is ignored. Add a server rule denying `/wp-content/uploads/pgp-ecp-logs/`.
5. **The text domain changed** to `pgp-encryption-for-contact-page`. Any existing translation files under the old `pgp-encryption` domain won't apply.
6. **Requires PHP 7.4** or later (tested up to PHP 8.4.26), plus the PHP `gnupg` extension (tested with ext 1.5.2, GPGME 1.24.2, GnuPG 2.4.7).
7. **The license is now GPL-2.0-or-later** (was MIT).
8. **The Turnstile script handle** is now `pgp-ecp-cf-turnstile`. This only matters if custom code dequeued `cf-turnstile`.
9. **The settings page now loads `admin.css`,** so its layout looks slightly different, and it shows the Buy me a coffee button.
10. **Settings and option names are unchanged** (`pgp_ecp_options`). No reconfiguration is needed after upgrading.

### Testing performed
- `php -l` on all 7 PHP files: no syntax errors (PHP 8.4.26).
- PHPCS WordPress (WPCS 3): 0/0. PHPCompatibilityWP 7.4+: 0. `node --check` on both JS files: OK.
- Official Plugin Check 2.1.0 via WP-CLI on WordPress 7.1.3 (SQLite): "No errors found", default and with experimental + low-severity checks.
- Runtime, in the test site with php-gnupg installed:
  - activation and default options;
  - shortcode rendering with a nonce;
  - key import and encryption;
  - a full AJAX submission (mail intercepted) returning success, with a PGP-encrypted body.
- Redaction unit tests: 19/19 passed (short local part, subdomains, multi-part TLD, UTF-8, invalid/empty/non-string input, scrubbing of key IDs and `print_r` dumps).
- Logging, with WP_DEBUG false and the option on:
  - the log is created;
  - a full-email search finds nothing, and plaintext appears 0 times;
  - `debug.log` is untouched;
  - logging also works with `FS_METHOD=ftpext`.
- Failure notices: shown for an unwritable log folder and for an unwritable uploads folder.
- View:

  | Case | Result |
  |---|---|
  | Admin | OK |
  | Subscriber | 403 |
  | Forged nonce | 403 |
  | Injected `<script>` line | shown escaped, raw count 0 |
  | 1 MB log | truncated to the last 500 KB, with the note |

- Delete:

  | Case | Result |
  |---|---|
  | Subscriber | 403, file kept |
  | Bad / missing nonce | 403, file kept |
  | Admin | file removed; repeat says "missing" |

  A forged-nonce notice prints nothing.
- Uninstall: the option and the log folder were removed.
- Buy me a coffee button (headless Chrome, as admin): correct colours, border, corners, link, new tab and rel; the Cookie font loaded; no remote requests from the plugin. Screenshot: `bmc-button.png`.
- **Live site (by the plugin owner):** debug logging, View debug log and Delete debug log confirmed working.

### Known limitations
- Not tested end to end: real email delivery through a mail server, and Cloudflare Turnstile with real keys.
- **Report vs zip:** the user's Plugin Check report didn't exactly match the 2.0.1 zip. Line numbers were shifted and it mentioned two upgrade-notice sections that weren't there. The zip was treated as authoritative.
- **Unchanged from before:**
  - every front-end page load makes a `wp_remote_head()` request to probe the custom admin URL, which adds latency;
  - the plugin's assets and the Turnstile script load on every front-end page, not only where the shortcode is used;
  - `frontend.css` styles `input[type=submit]`, but the form uses `<button>`.
- **Nginx:** log folder protection relies on `.htaccess` (Apache). On Nginx, add a deny rule.
- **Debug logging is disabled** (with an explanation on the settings page) on hosts where PHP can't write to the uploads folder or where `error_log` is disabled.
- The Turnstile script must stay remote (Cloudflare requirement). It is opt-in and disclosed in the readme.

<details>
<summary>Per-file changes (2.0.1 → 2.1.0)</summary>

| File | Status | Changes |
|---|---|---|
| `pgp-encryption-for-contact-page.php` | Modified | Full plugin header (version 2.1.0, Requires at least 6.0, Requires PHP 7.4, GPL-2.0-or-later, License URI, Text Domain, Buy Me a Coffee donate link). Shared debug helpers: `pgp_ecp_is_debug_enabled`, `pgp_ecp_redact_email`, `pgp_ecp_scrub_emails`, `pgp_ecp_log_dir/_file`, `pgp_ecp_append_file`, `pgp_ecp_ensure_log_dir`, `pgp_ecp_log_status`, `pgp_ecp_log`. Named, escaped gnupg-missing notice. Init log moved into `pgp_ecp_init()`. |
| `uninstall.php` | Modified | Deletes the `pgp_ecp_options` option, the `pgp_ecp_temp_data` transient and the `uploads/pgp-ecp-logs/` folder. Prefixed globals. No logging. |
| `readme.txt` | Modified | Headers (Contributors, Donate link, Tags, Tested up to 7.1, Requires PHP 7.4, Stable tag 2.1.0, GPL-2.0-or-later). New short description. Requirements, External services and Credits sections. Debug FAQ rewritten. 2.1.0 changelog and upgrade notice. Duplicate FAQ line removed. |
| `includes/class-pgp-ecp-admin.php` | Modified | Escaping and i18n throughout. Sanitize callback unslashes and sanitizes. Capability check. Admin CSS enqueued. Admin JS gets a debug flag. Inline script removed. Debug Log box (status, size, View/Delete). `handle_view_log()` and `handle_delete_log()` handlers. Result notices. Log-write failure notice. Local Buy me a coffee button in the page header. Updated debug-field description. |
| `includes/class-pgp-ecp-frontend.php` | Modified | Unslash and sanitize, sanitized nonce, i18n'd messages and form labels. Turnstile URLs as constants (opt-in, justified phpcs:ignore). Handle `pgp-ecp-cf-turnstile`. Safe Turnstile response check. Redacted recipient. Extra success-path log lines. Localized JS strings. |
| `includes/class-pgp-ecp-encryption.php` | Modified | WPCS formatting and docblocks. Shared logger. Behaviour unchanged. |
| `includes/class-pgp-ecp-activator.php` | Modified | Escaped, translatable `wp_die()` message. Shared logger. Default options unchanged. |
| `includes/class-pgp-ecp-deactivator.php` | Modified | WPCS formatting. Shared logger. Behaviour unchanged. |
| `assets/css/admin.css` | Modified | Header path fixed. Header flex layout, local `@font-face` and Buy me a coffee button styles added. |
| `assets/css/frontend.css` | Modified | Header path only. |
| `assets/js/admin.js` | Modified | `/* */` header with lowercase path. Console output only when debug is on (`pgp_ecp_admin_vars.debug_enabled`). |
| `assets/js/frontend.js` | Modified | `/* */` header with lowercase path. Error/AJAX console output only when debug is on. Localized alert strings. |
| `assets/fonts/Cookie-Regular.ttf` | **Added** | Cookie font by Ania Kruk, unmodified, SIL OFL 1.1 (sha256 9daf3cc9…080e). |
| `assets/fonts/Cookie-OFL.txt` | **Added** | SIL Open Font License 1.1 for Cookie. |

No files were removed.

</details>

## [2.0.1] - 2025-09-17

### Changed
- Fixed Plugin Check warnings and errors (per the GitHub release notes).
- Changed the license from MIT to GPL-2.0-or-later (per the GitHub release notes; the files in this release still declared MIT, which 2.1.0 corrects).
- This release was submitted to the WordPress.org Plugin Directory as 2.0.0, so its files still declare version 2.0.0.

## [2.0.0] - 2025-09-16

### Added
- Cloudflare Turnstile CAPTCHA integration with an enable/disable checkbox.
- Debugging control with an **Enable Debugging Logs** checkbox.

### Changed
- Improved form submission reliability and error handling.

## [1.0.0] - 2025-09-13

### Added
- Initial release with PGP encryption support for contact forms.

[2.1.0]: https://github.com/Aldous-Huxley/PGP-Encryptio-for-Contact-Page/compare/v2.0.1...release/2.1.0
[2.0.1]: https://github.com/Aldous-Huxley/PGP-Encryptio-for-Contact-Page/releases/tag/v2.0.1
[2.0.0]: https://github.com/Aldous-Huxley/PGP-Encryptio-for-Contact-Page/releases/tag/V2.0.0
[1.0.0]: https://github.com/Aldous-Huxley/PGP-Encryptio-for-Contact-Page/releases/tag/v1.0.0
