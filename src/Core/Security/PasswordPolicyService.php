<?php

namespace AtomFramework\Core\Security;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Password Policy Service — expiry and history enforcement.
 *
 * Enforces:
 * - Password expiry (configurable days, default 90)
 * - Password history (prevents reuse of last N passwords, default 5)
 *
 * Requires the `password_history` table. Gracefully degrades if the table
 * does not exist (returns safe defaults so authentication still works).
 *
 * Table SQL:
 *   CREATE TABLE IF NOT EXISTS password_history (
 *       id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 *       user_id INT NOT NULL,
 *       password_hash VARCHAR(255) NOT NULL,
 *       changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 *       INDEX idx_password_history_user (user_id),
 *       CONSTRAINT fk_password_history_user FOREIGN KEY (user_id)
 *           REFERENCES user(id) ON DELETE CASCADE
 *   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
 */
class PasswordPolicyService
{
    /** Default maximum password age in days */
    private const DEFAULT_EXPIRY_DAYS = 90;

    /** Default number of previous passwords to remember */
    private const DEFAULT_HISTORY_COUNT = 5;

    /**
     * Check if a user's password has expired.
     *
     * @param int $userId The user ID
     * @return bool True if the password has expired
     */
    public static function isPasswordExpired(int $userId): bool
    {
        $expiryDays = self::getExpiryDays();

        if ($expiryDays <= 0) {
            return false; // Expiry disabled
        }

        if (!self::tableExists()) {
            return false;
        }

        $lastChange = DB::table('password_history')
            ->where('user_id', $userId)
            ->orderByDesc('changed_at')
            ->value('changed_at');

        if (!$lastChange) {
            // No password history — treat as expired to force initial recording
            return true;
        }

        $expiryDate = date('Y-m-d H:i:s', strtotime($lastChange . " + {$expiryDays} days"));

        return date('Y-m-d H:i:s') >= $expiryDate;
    }

    /**
     * Get days until password expires.
     *
     * @param int $userId The user ID
     * @return int Days remaining (0 = expired, -1 = no expiry)
     */
    public static function daysUntilExpiry(int $userId): int
    {
        $expiryDays = self::getExpiryDays();

        if ($expiryDays <= 0) {
            return -1;
        }

        if (!self::tableExists()) {
            return -1;
        }

        $lastChange = DB::table('password_history')
            ->where('user_id', $userId)
            ->orderByDesc('changed_at')
            ->value('changed_at');

        if (!$lastChange) {
            return 0;
        }

        $expiryTimestamp = strtotime($lastChange . " + {$expiryDays} days");
        $remaining = (int) ceil(($expiryTimestamp - time()) / 86400);

        return max(0, $remaining);
    }

    /**
     * Check if a password was previously used by this user.
     *
     * The plaintext password is hashed with each stored salt+hash to detect reuse.
     * AtoM stores passwords as: password_hash(sha1(salt . plaintext), PASSWORD_DEFAULT).
     *
     * @param int    $userId       The user ID
     * @param string $sha1Hash     The SHA1(salt + plaintext) hash to check
     * @return bool True if the password was previously used
     */
    public static function isPasswordReused(int $userId, string $sha1Hash): bool
    {
        $historyCount = self::getHistoryCount();

        if ($historyCount <= 0) {
            return false; // History disabled
        }

        if (!self::tableExists()) {
            return false;
        }

        $previousHashes = DB::table('password_history')
            ->where('user_id', $userId)
            ->orderByDesc('changed_at')
            ->limit($historyCount)
            ->pluck('password_hash');

        foreach ($previousHashes as $storedHash) {
            if (password_verify($sha1Hash, $storedHash)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Record a password change in history.
     *
     * @param int    $userId       The user ID
     * @param string $passwordHash The new password_hash value (Argon2i/Bcrypt)
     */
    public static function recordPasswordChange(int $userId, string $passwordHash): void
    {
        if (!self::tableExists()) {
            return;
        }

        DB::table('password_history')->insert([
            'user_id' => $userId,
            'password_hash' => $passwordHash,
            'changed_at' => date('Y-m-d H:i:s'),
        ]);

        // Trim history beyond the configured limit
        self::trimHistory($userId);
    }

    /**
     * Remove old password history entries beyond the configured limit.
     */
    private static function trimHistory(int $userId): void
    {
        $keepCount = self::getHistoryCount();

        if ($keepCount <= 0) {
            return;
        }

        $keepIds = DB::table('password_history')
            ->where('user_id', $userId)
            ->orderByDesc('changed_at')
            ->limit($keepCount)
            ->pluck('id')
            ->toArray();

        if (empty($keepIds)) {
            return;
        }

        DB::table('password_history')
            ->where('user_id', $userId)
            ->whereNotIn('id', $keepIds)
            ->delete();
    }

    /**
     * Get password expiry days from settings.
     */
    private static function getExpiryDays(): int
    {
        try {
            $val = DB::table('ahg_settings')
                ->where('setting_key', 'password_expiry_days')
                ->value('setting_value');

            return $val !== null ? (int) $val : self::DEFAULT_EXPIRY_DAYS;
        } catch (\Throwable $e) {
            return self::DEFAULT_EXPIRY_DAYS;
        }
    }

    /**
     * Get password history count from settings.
     */
    private static function getHistoryCount(): int
    {
        try {
            $val = DB::table('ahg_settings')
                ->where('setting_key', 'password_history_count')
                ->value('setting_value');

            return $val !== null ? (int) $val : self::DEFAULT_HISTORY_COUNT;
        } catch (\Throwable $e) {
            return self::DEFAULT_HISTORY_COUNT;
        }
    }

    /**
     * Check if the password_history table exists.
     */
    private static function tableExists(): bool
    {
        static $exists = null;

        if ($exists === null) {
            try {
                $exists = DB::schema()->hasTable('password_history');
            } catch (\Throwable $e) {
                $exists = false;
            }
        }

        return $exists;
    }
}
