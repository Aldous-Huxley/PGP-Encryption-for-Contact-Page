# Contributing

Thanks for helping improve **PGP Encryption for Contact Page**!

## Ground rules

- **Code style:** follow the [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/php/).
- **Security:**
  - escape all output and sanitize all input;
  - check nonces and capabilities for every action.
- **Translation:** use the text domain `pgp-encryption-for-contact-page` for user-facing strings.
- **Logging:** never log email addresses or message contents. Always log through `pgp_ecp_log()`, which only writes when the admin enables debugging and redacts emails.
- **No remote assets:** don't load remote scripts, styles or fonts. The only exception is the opt-in Cloudflare Turnstile script.
- **Header comments:** keep the `File:` / `Path:` header comment at the top of every source file, for example:
  ```php
  // File: class-pgp-ecp-frontend.php
  // Path: pgp-encryption-for-contact-page/includes/class-pgp-ecp-frontend.php
  ```
- **Docs:** update `readme.txt` and `CHANGELOG.md` when behaviour changes.

## Local checks

```bash
# PHP syntax
find . -name '*.php' -not -path './vendor/*' -print0 | xargs -0 -n1 php -l

# WordPress Coding Standards 3 + PHPCompatibilityWP (ruleset: phpcs.xml.dist)
composer install
vendor/bin/phpcs      # or: composer lint
vendor/bin/phpcbf     # auto-fix, or: composer lint:fix
```

### Plugin Check

Install the official [Plugin Check](https://wordpress.org/plugins/plugin-check/) plugin on a test site, then run:

```bash
wp plugin check pgp-encryption-for-contact-page
```

The PHP checks should report 0 issues and Plugin Check should report no errors.

CI (`.github/workflows/ci.yml`) runs the same checks on every pull request: PHP lint on 7.4 / 8.0 / 8.2 / 8.4, PHPCS, and the official Plugin Check.

## Pull requests

1. Fork the repository and create a branch from `main`.
2. Make focused changes, then run the checks above.
3. Open a pull request and complete the checklist in the template.
