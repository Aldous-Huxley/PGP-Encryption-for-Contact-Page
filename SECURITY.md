# Security Policy

## Supported versions

| Version | Supported |
|---|---|
| 2.1.x | ✅ |
| < 2.1 | ❌ |

## Reporting a vulnerability

**Please do not open a public issue for security problems.**

Report vulnerabilities privately through GitHub's private vulnerability reporting:
**[Report a vulnerability](https://github.com/Aldous-Huxley/PGP-Encryption-for-Contact-Page/security/advisories/new)** (Security tab → Report a vulnerability).

Please include:
- the affected version and environment (WordPress, PHP, gnupg versions);
- steps to reproduce or a proof of concept;
- the impact you expect.

Please don't include real private keys, Turnstile secret keys or personal data.

You can expect an acknowledgement as soon as possible. Fixes are released as a new plugin version, with credit if you'd like it.

## Scope notes

- Messages are encrypted on the server with the PHP `gnupg` extension and the site owner's **public** key. The private key never belongs on the website.
- The optional debug log is stored in `wp-content/uploads/pgp-ecp-logs/`:
  - email addresses are redacted;
  - the folder is protected by `.htaccess` and `index.php`;
  - on Nginx you must add a deny rule (see README).
