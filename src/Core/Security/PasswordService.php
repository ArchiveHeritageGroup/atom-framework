<?php

namespace AtomFramework\Core\Security;

/**
 * PasswordService — single source of truth for password hashing + verification
 * (security audit 2026-06-15, migration plan).
 *
 * Target scheme: Argon2id over the plaintext, with NO inner sha1 layer and NO
 * application-managed salt (Argon2 carries its own per-hash random salt). New
 * and rehashed hashes store an EMPTY `user.salt`.
 *
 * Legacy scheme (pre-migration): password_hash(sha1(salt . plaintext)) with a
 * non-empty `user.salt`. Both are supported transparently so existing users keep
 * logging in; verify() picks the scheme from whether `salt` is empty.
 *
 * The `user.salt` column doubles as the scheme discriminator (the core `user`
 * table cannot take a new flag column):
 *   - salt empty/NULL  -> new scheme  -> password_verify(plaintext, hash)
 *   - salt non-empty   -> legacy      -> password_verify(sha1(salt.plaintext), hash)
 *
 * Callers verify with verify(), and on a SUCCESSFUL login should rehash when
 * needsUpgrade() is true (writes hash() back to the user row) — a transparent
 * verify-on-login upgrade that needs no mass reset and no schema change.
 */
class PasswordService
{
    /** Preferred algorithm: Argon2id > Argon2i > bcrypt default. */
    public static function algo(): string
    {
        if (defined('PASSWORD_ARGON2ID')) {
            return PASSWORD_ARGON2ID;
        }
        if (defined('PASSWORD_ARGON2I')) {
            return PASSWORD_ARGON2I;
        }

        return PASSWORD_DEFAULT;
    }

    /**
     * Hash a plaintext password with the target scheme.
     *
     * @return array{password_hash: string, salt: string} ready to store on the user row
     */
    public static function hash(string $plaintext): array
    {
        return [
            'password_hash' => password_hash($plaintext, self::algo()),
            'salt' => '', // new scheme carries no application salt (discriminator)
        ];
    }

    /**
     * Verify a plaintext password against a stored hash, supporting BOTH schemes.
     * The scheme is chosen by whether the stored salt is empty (new) or not (legacy).
     */
    public static function verify(string $plaintext, string $hash, ?string $salt): bool
    {
        if ('' === $hash) {
            return false;
        }
        if (self::isLegacy($salt)) {
            return password_verify(sha1($salt . $plaintext), $hash);
        }

        return password_verify($plaintext, $hash);
    }

    /**
     * Whether a stored credential should be upgraded on next successful login:
     * legacy (non-empty salt) always, or a new-scheme hash whose cost/algo is
     * now out of date.
     */
    public static function needsUpgrade(string $hash, ?string $salt): bool
    {
        if (self::isLegacy($salt)) {
            return true;
        }

        return '' !== $hash && password_needs_rehash($hash, self::algo());
    }

    /** A non-empty salt marks the legacy sha1(salt.plaintext) scheme. */
    private static function isLegacy(?string $salt): bool
    {
        return null !== $salt && '' !== $salt;
    }
}
