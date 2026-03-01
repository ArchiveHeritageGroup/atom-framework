# AtoM Heratio Framework — Secrets Scanning Policy

**Version:** 1.0.0
**Author:** The Archive and Heritage Group (Pty) Ltd

---

## What Constitutes a Secret

The following are considered secrets and must NEVER be committed to version control:

| Category | Examples |
|----------|---------|
| **Database credentials** | MySQL passwords, connection strings |
| **API keys** | Third-party service keys, webhook secrets |
| **Authentication tokens** | JWT secrets, session keys, OAuth tokens |
| **Private keys** | SSH keys, SSL/TLS certificates, GPG keys |
| **Cloud credentials** | AWS access keys, Azure service principals |
| **Email credentials** | SMTP passwords |
| **Encryption keys** | Application encryption keys, salts |

## Where Secrets Should Live

| Secret Type | Storage Location |
|-------------|-----------------|
| Database credentials | `apps/qubit/config/config.php` (server-local, .gitignored) |
| Application settings | `config/app.yml` (server-local, .gitignored) |
| Email credentials | `apps/qubit/config/factories.yml` (server-local) |
| Webhook secrets | `ahg_settings` table (database) |
| API keys | `ahg_settings` table or environment variables |
| SSL certificates | `/etc/ssl/` (system) |
| SSH keys | `~/.ssh/` (user) |

## .gitignore Patterns

Ensure these patterns are in `.gitignore`:

```
# Secrets and configuration
*.env
.env.*
config.php
app.yml
factories.yml
databases.yml
credentials.json
*.pem
*.key
*.p12
*.pfx

# IDE and system
.idea/
.vscode/
*.swp
.DS_Store

# Build artifacts
vendor/
node_modules/
cache/
```

## CI/CD Secrets Scanning

### Automated Checks

The CI pipeline includes automated secrets scanning:

1. **Pre-commit hook** (`detect-private-key`): Blocks commits containing private keys
2. **CI security job**: Greps for password/token patterns in source files
3. **Composer audit**: Checks for vulnerable dependencies

### Patterns Scanned

```regex
(password|secret|api_key|private_key|token)\s*[=:]\s*["'][^\s]{8,}
AKIA[0-9A-Z]{16}
```

### Exclusions

The following are excluded from scanning:
- Documentation files (`.md`)
- Configuration templates with placeholder values
- Framework code that reads configuration (e.g., `sfConfig::get()`, `getParameter()`)
- Test fixtures with dummy values

## If a Secret Is Committed

### Immediate Actions

1. **Rotate the secret** immediately — the exposed credential must be considered compromised
2. **Remove from history** if caught before push:
   ```bash
   git reset --soft HEAD~1
   # Remove the secret from the file
   git add .
   git commit -m "Remove accidentally committed secret"
   ```
3. **If already pushed**, contact the repository administrator — a force push or BFG history rewrite may be required
4. **Audit access** — check logs for unauthorized use of the compromised credential

### Prevention

- Use `pre-commit install` to activate the pre-commit hooks
- Review diffs before committing: `git diff --staged`
- Never use real credentials in test code — use environment variables or config files
- Store development credentials in `config.php` / `app.yml` which are .gitignored

## Test Credentials

The following test credentials are authorized for automated testing and appear in CLAUDE.md:
- Admin login credentials for dev/test instances
- These are development-only and must never be used in production

## Contact

For security concerns, contact: johan@theahg.co.za

---

*AtoM Heratio is developed by The Archive and Heritage Group (Pty) Ltd.*
