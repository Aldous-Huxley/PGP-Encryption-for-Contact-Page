## Summary
<!-- What does this PR change and why? Link related issues (e.g. Fixes #123). -->

## Checklist
- [ ] `php -l` passes on all PHP files
- [ ] PHPCS with the `WordPress` standard reports 0 errors / 0 warnings (see CONTRIBUTING.md)
- [ ] Official Plugin Check reports no errors
- [ ] Every source file keeps its `File:` / `Path:` header comment
- [ ] Output is escaped, input is sanitized, nonces/capabilities are checked
- [ ] User-facing strings use the `pgp-encryption-for-contact-page` text domain
- [ ] No email addresses or message contents are logged (use `pgp_ecp_log()`)
- [ ] No remote scripts, styles or fonts are added
- [ ] `readme.txt` / `CHANGELOG.md` are updated if behaviour changes

## Testing
<!-- How did you test this? WordPress / PHP / gnupg versions. -->
