# AtoM Heratio Framework — Release Checklist

**Version:** 2.8.2+
**Author:** The Archive and Heritage Group (Pty) Ltd

---

## Pre-Release

### Code Quality
- [ ] All PHP files pass syntax check: `find src lib -name "*.php" -exec php -l {} \;`
- [ ] Plugin PHP files pass syntax check: `find atom-ahg-plugins -name "*.php" -exec php -l {} \;`
- [ ] No PHP CS Fixer violations in changed files
- [ ] No `var_dump()`, `print_r()`, or `die()` left in code
- [ ] No hardcoded credentials, API keys, or tokens in source files

### Security
- [ ] `composer audit` reports no critical vulnerabilities
- [ ] Secrets pattern scan clean (no passwords/tokens in source)
- [ ] DAST smoke test passes: `bash tests/security/smoke-routes.sh`
- [ ] CSP nonce present on all `<script>` and `<style>` tags in new/modified templates
- [ ] No `'unsafe-inline'` added to CSP configuration
- [ ] SQL injection review: all user input parameterized (no string concatenation in queries)
- [ ] XSS review: all output properly escaped in templates

### Database
- [ ] All new tables use `CREATE TABLE IF NOT EXISTS`
- [ ] No `ENUM` columns used (use `VARCHAR` with `COMMENT` instead)
- [ ] No modifications to core AtoM tables
- [ ] Schema migrations use INFORMATION_SCHEMA checks (no `ADD COLUMN IF NOT EXISTS`)
- [ ] No `INSERT INTO atom_plugin` statements in plugin install.sql files

### Testing
- [ ] Manual testing on dev instance (192.168.0.112)
- [ ] Playwright tests pass: `cd testing/playwright && npx playwright test`
- [ ] Fresh install tested (install.sql runs cleanly on empty database)
- [ ] Upgrade path tested (existing data preserved after migration)

### Documentation
- [ ] CLAUDE.md updated if architectural changes made
- [ ] Feature Overview documents updated (.md + .docx)
- [ ] User Manual updated (.md + .docx)
- [ ] CHANGELOG updated with version entry

---

## Release

### Version Bump
```bash
# Choose the appropriate level:
cd /usr/share/nginx/archive/atom-framework
./bin/release patch "Short description of changes"
# or
./bin/release minor "Short description of changes"
# or
./bin/release major "Short description of changes"

# For plugins:
cd /usr/share/nginx/archive/atom-ahg-plugins
git add -A
git commit -m "Description of changes"
./bin/release patch "Short description of changes"
```

### Release Steps
- [ ] Version bumped via `./bin/release` (never manual push)
- [ ] Git tag created automatically by release script
- [ ] GitHub release notes generated (if applicable)
- [ ] .docx documentation regenerated from .md sources

---

## Post-Release

### Deployment
- [ ] Pull changes on dev instance:
  ```bash
  cd /usr/share/nginx/archive/atom-framework && git pull origin main
  cd /usr/share/nginx/archive/atom-ahg-plugins && git pull origin main
  ```
- [ ] Run database migrations if needed
- [ ] Clear caches: `rm -rf cache/* && php symfony cc`
- [ ] Restart PHP-FPM: `sudo systemctl restart php8.3-fpm`

### Verification
- [ ] DAST smoke test on deployed instance
- [ ] Admin dashboard loads without errors
- [ ] Key plugin features functional
- [ ] No JavaScript console errors (CSP violations)
- [ ] Queue worker running: `sudo systemctl status atom-queue-worker@default`
- [ ] Queue status healthy: `php atom-framework/bin/atom queue:status`

### ANC Instance (if applicable)
- [ ] Pull to ANC instance: `cd /usr/share/nginx/atom/atom-framework && git pull origin main`
- [ ] Run migrations
- [ ] Clear caches
- [ ] Verify functionality

### Monitoring
- [ ] Check PHP error logs for 24 hours post-deploy
- [ ] Verify cron jobs running normally
- [ ] Check Elasticsearch indexing status

---

*AtoM Heratio is developed by The Archive and Heritage Group (Pty) Ltd.*
