# Encryption Architecture — atom-framework v2

> **Issue:** #145 — Encryption Layers for GLAM/DAM Data Protection
> **Version:** V2 (libsodium, chunked streaming, HKDF subkeys)
> **Compliance:** POPIA, GDPR, CCPA, NARSSA, PAIA

---

## Overview

AtoM Heratio provides two encryption layers to protect sensitive data at rest:

- **Layer 1 — File Encryption**: Digital objects (masters + derivatives) encrypted on disk
- **Layer 2 — Field Encryption**: Sensitive database columns encrypted transparently

```
┌────────────────────────────────────────────────────────────────┐
│                     Master Key (/etc/atom/encryption.key)      │
│                     256-bit, HKDF-SHA256 subkey derivation     │
├─────────────────┬─────────────────┬────────────────────────────┤
│  PURPOSE_FILE   │  PURPOSE_FIELD  │  PURPOSE_HMAC              │
│  file-encryption│  field-encryption│  hmac-index (future)      │
├─────────────────┼─────────────────┼────────────────────────────┤
│  Layer 1        │  Layer 2        │  Blind Index (planned)     │
│  FileEncryption │  EncryptableField│                           │
│  Service        │  Service        │                            │
└─────────────────┴─────────────────┴────────────────────────────┘
```

---

## Algorithms

### V2 (Current — libsodium)

| Component | Algorithm | Library |
|-----------|-----------|---------|
| File encryption | XChaCha20-Poly1305 secretstream | libsodium |
| String/field encryption | XChaCha20-Poly1305 AEAD | libsodium |
| Subkey derivation | HKDF-SHA256 | PHP hash_hkdf / manual |
| Key storage | hex-encoded + key_id | filesystem |

### V1 (Legacy — OpenSSL, backward-compatible)

| Component | Algorithm | Library |
|-----------|-----------|---------|
| File encryption | AES-256-GCM (whole-file) | OpenSSL |
| String/field encryption | AES-256-GCM | OpenSSL |
| Key storage | hex-encoded | filesystem |

V2 is used automatically when `ext-sodium` is available. V1 decryption is always supported for backward compatibility.

---

## File Formats

### V2 Encrypted File

```
Offset  Size   Field
──────  ─────  ─────────────────────────────────────────
0       10     Magic header: "AHG-ENC-V2"
10      4      Key ID (uint32 LE) — rotation tracking
14      4      Chunk size (uint32 LE) — default 65536
18      24     Secretstream header (sodium)
42      var    Encrypted chunks...
               Each chunk = plaintext_size + 17 bytes (ABYTES)
               Last chunk tagged with TAG_FINAL
```

**Properties:**
- Constant-memory streaming: never loads full file into RAM
- Per-chunk AEAD authentication: detects truncation, reordering, corruption
- Overhead: 42 bytes header + 17 bytes per chunk

### V1 Encrypted File (Legacy)

```
Offset  Size   Field
──────  ─────  ─────────────────────────────────────────
0       10     Magic header: "AHG-ENC-V1"
10      12     IV (GCM nonce)
22      16     Tag (GCM auth tag)
38      var    Ciphertext (single block)
```

### V2 Encrypted String (Field Encryption)

```
Offset  Size   Field
──────  ─────  ─────────────────────────────────────────
0       4      Marker: "AHG2"
4       4      Key ID (uint32 LE)
8       24     Nonce (XChaCha20)
32      var    Ciphertext + 16-byte Poly1305 tag
```

AAD (Additional Authenticated Data): `"AHG2" + key_id_bytes` — binds the version marker and key ID to the ciphertext, preventing downgrade attacks.

### V1 Encrypted String (Legacy)

```
Offset  Size   Field
──────  ─────  ─────────────────────────────────────────
0       12     IV (GCM nonce)
12      16     Tag (GCM auth tag)
28      var    Ciphertext
```

### Database Storage

Encrypted field values are stored as: `{AHG-ENC}` + base64(binary_ciphertext)

The `{AHG-ENC}` prefix allows detection of encrypted values without parsing binary data.

---

## Key Management

### Key File Location

```
/etc/atom/encryption.key    (outside web root, chmod 0600)
```

### Key File Format (V2)

```
Line 1: 64 hex characters (32-byte master key)
Line 2: key_id (decimal integer, e.g. "1")
```

V1 format (just hex key, no key_id) is auto-detected and defaults to key_id=1.

### HKDF Subkey Derivation

The master key is never used directly for encryption. Purpose-specific subkeys are derived via HKDF-SHA256:

```php
KeyManager::deriveKey(KeyManager::PURPOSE_FILE)   // → 32-byte file encryption key
KeyManager::deriveKey(KeyManager::PURPOSE_FIELD)  // → 32-byte field encryption key
KeyManager::deriveKey(KeyManager::PURPOSE_HMAC)   // → 32-byte HMAC key (future)
```

This limits blast radius: compromising one subkey doesn't expose data protected by another.

### Key Rotation

1. Generate new key with incremented key_id: `php bin/atom encryption:key --generate --force`
2. Re-encrypt files: `php bin/atom encryption:encrypt-files --upgrade-v2`
3. Re-encrypt fields: `php bin/atom encryption:encrypt-fields --all --reverse` then `--all`
4. The key_id embedded in encrypted data identifies which key was used

---

## Service Architecture

### Core Services (`atom-framework/src/Core/Security/`)

| File | Class | Purpose |
|------|-------|---------|
| `KeyManager.php` | `KeyManager` | Master key loading, HKDF derivation, validation |
| `EncryptionService.php` | `EncryptionService` | String + file encrypt/decrypt, V1/V2 dispatch |
| `FileEncryptionService.php` | `FileEncryptionService` | Layer 1: digital object encryption |
| `EncryptableFieldService.php` | `EncryptableFieldService` | Layer 2: database field encryption |

### CLI Commands (`atom-framework/src/Console/Commands/Security/`)

| Command | Description |
|---------|-------------|
| `encryption:key --generate` | Generate new master key |
| `encryption:key --validate` | Validate key + round-trip test |
| `encryption:status` | Full encryption dashboard |
| `encryption:encrypt-files --limit=N` | Batch-encrypt digital objects |
| `encryption:encrypt-files --id=123` | Encrypt specific digital object |
| `encryption:encrypt-files --upgrade-v2` | Re-encrypt V1 files as V2 |
| `encryption:encrypt-files --dry-run` | Preview what would be encrypted |
| `encryption:encrypt-fields --category=X` | Encrypt a field category |
| `encryption:encrypt-fields --reverse --category=X` | Decrypt a field category |
| `encryption:encrypt-fields --list` | List available categories |
| `encryption:encrypt-fields --all` | Encrypt all categories |

### Database Tables (`atom-framework/database/encryption_tables.sql`)

| Table | Purpose |
|-------|---------|
| `ahg_encrypted_fields` | Tracks which fields are currently encrypted |
| `ahg_encryption_audit` | Audit log of all encryption operations |

### Settings (`ahg_settings` table)

| Key | Purpose |
|-----|---------|
| `encryption_enabled` | Master toggle (Layer 1) |
| `encryption_encrypt_derivatives` | Encrypt thumbnails/reference images |
| `encryption_field_contact_details` | Layer 2: contact info category |
| `encryption_field_financial_data` | Layer 2: financial data category |
| `encryption_field_donor_information` | Layer 2: donor info category |
| `encryption_field_personal_notes` | Layer 2: personal notes category |
| `encryption_field_access_restrictions` | Layer 2: access restrictions category |

---

## Field Encryption Categories

| Category | Table | Column |
|----------|-------|--------|
| contact_details | `contact_information` | `email` |
| contact_details | `contact_information` | `contact_person` |
| contact_details | `contact_information` | `street_address` |
| contact_details | `contact_information_i18n` | `city` |
| contact_details | `contact_information` | `telephone` |
| contact_details | `contact_information` | `fax` |
| financial_data | `accession_i18n` | `appraisal` |
| donor_information | `actor_i18n` | `history` |
| personal_notes | `note_i18n` | `content` |
| access_restrictions | `rights_i18n` | `rights_note` |

When a category is encrypted, values are stored as `{AHG-ENC}base64(ciphertext)` and decrypted transparently on read. Encrypted fields are **not searchable** via Elasticsearch.

---

## Integration Hooks

### Upload Encryption (Layer 1)

Files are encrypted after upload in these action classes:

- `ahgDisplayPlugin/.../addDigitalObjectAction.class.php`
- `ahgDisplayPlugin/.../multiFileUploadAction.class.php`
- `ahgThemeB5Plugin/.../addDigitalObjectAction.class.php`

Pattern:
```php
if (class_exists('\\AtomFramework\\Core\\Security\\FileEncryptionService')
    && \AtomFramework\Core\Security\FileEncryptionService::isEnabled()) {
    \AtomFramework\Core\Security\FileEncryptionService::encryptDigitalObject($do->id);
    \AtomFramework\Core\Security\FileEncryptionService::encryptDerivatives($do->id);
}
```

### Media Decryption

Transparent decryption in `ahgIiifPlugin/modules/media/actions/actions.class.php`:

```php
if (EncryptionService::isEncryptedFile($fullPath)) {
    return FileEncryptionService::decryptToTemp($fullPath);
}
```

### Settings UI

Admin > AHG Settings > Encryption — managed by `ahgSettingsPlugin`:
- `sectionAction.class.php` — handler
- `section.blade.php` / `sectionSuccess.php` — templates
- `menuComponent.class.php` — menu entry

---

## Security Properties

| Property | V2 | V1 |
|----------|----|----|
| Algorithm | XChaCha20-Poly1305 | AES-256-GCM |
| Nonce size | 24 bytes (safe random) | 12 bytes (collision risk at scale) |
| Streaming | Yes (64KB chunks) | No (whole file in memory) |
| Per-chunk auth | Yes (TAG_MESSAGE/TAG_FINAL) | No (single auth tag) |
| Key derivation | HKDF-SHA256 per purpose | Raw master key |
| Key ID tracking | Yes (embedded in ciphertext) | No |
| AAD binding | Version marker + key_id | None |
| Memory usage | O(chunk_size) = 64KB | O(file_size) |

---

## Operational Notes

### Prerequisites

- PHP 8.1+ with `ext-sodium` (for V2)
- `/etc/atom/` directory writable (for key generation)
- Key file permissions: `chmod 0600 /etc/atom/encryption.key`

### Backup

Always back up the encryption key before rotation:
```bash
cp /etc/atom/encryption.key /secure-backup/encryption.key.$(date +%Y%m%d)
```

If the key is lost, **all encrypted data is permanently unrecoverable**.

### Performance

- File encryption overhead: 42 bytes header + 17 bytes per 64KB chunk
- String encryption overhead: 48 bytes (marker + key_id + nonce + tag)
- 75MB TIFF file: ~20KB overhead, streaming encrypt/decrypt
- No measurable impact on page load (decrypt on demand)

### Monitoring

```bash
php bin/atom encryption:status    # Full dashboard
```

Check the `ahg_encryption_audit` table for operation history.
