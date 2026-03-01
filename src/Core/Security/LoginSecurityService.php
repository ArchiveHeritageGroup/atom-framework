<?php

namespace AtomFramework\Core\Security;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Login security service — brute force protection.
 *
 * Tracks failed login attempts and enforces account lockout after
 * exceeding the threshold. Uses the `login_attempt` table to record
 * attempts by identifier (email/username) and IP address.
 *
 * Default policy: 5 failed attempts within 15 minutes = 15-minute lockout.
 */
class LoginSecurityService
{
    /** Maximum failed attempts before lockout */
    private const MAX_ATTEMPTS = 5;

    /** Lockout window in minutes (both for counting attempts and lockout duration) */
    private const LOCKOUT_MINUTES = 15;

    /**
     * Check if a login identifier is currently locked out.
     *
     * @param string $identifier Email or username
     * @param string $ipAddress  Client IP address
     * @return bool True if locked out
     */
    public static function isLockedOut(string $identifier, string $ipAddress = ''): bool
    {
        if (!self::tableExists()) {
            return false;
        }

        $since = date('Y-m-d H:i:s', time() - (self::LOCKOUT_MINUTES * 60));

        $failures = DB::table('login_attempt')
            ->where('identifier', $identifier)
            ->where('success', 0)
            ->where('attempted_at', '>=', $since)
            ->count();

        return $failures >= self::MAX_ATTEMPTS;
    }

    /**
     * Get the number of remaining attempts before lockout.
     *
     * @param string $identifier Email or username
     * @return int Remaining attempts (0 = locked out)
     */
    public static function remainingAttempts(string $identifier): int
    {
        if (!self::tableExists()) {
            return self::MAX_ATTEMPTS;
        }

        $since = date('Y-m-d H:i:s', time() - (self::LOCKOUT_MINUTES * 60));

        $failures = DB::table('login_attempt')
            ->where('identifier', $identifier)
            ->where('success', 0)
            ->where('attempted_at', '>=', $since)
            ->count();

        return max(0, self::MAX_ATTEMPTS - $failures);
    }

    /**
     * Get seconds until lockout expires.
     *
     * @param string $identifier Email or username
     * @return int Seconds until unlock, 0 if not locked
     */
    public static function lockoutRemaining(string $identifier): int
    {
        if (!self::tableExists()) {
            return 0;
        }

        $since = date('Y-m-d H:i:s', time() - (self::LOCKOUT_MINUTES * 60));

        $failures = DB::table('login_attempt')
            ->where('identifier', $identifier)
            ->where('success', 0)
            ->where('attempted_at', '>=', $since)
            ->count();

        if ($failures < self::MAX_ATTEMPTS) {
            return 0;
        }

        // Find the oldest failure in the window to determine when lockout expires
        $oldest = DB::table('login_attempt')
            ->where('identifier', $identifier)
            ->where('success', 0)
            ->where('attempted_at', '>=', $since)
            ->orderBy('attempted_at', 'asc')
            ->value('attempted_at');

        if (!$oldest) {
            return 0;
        }

        $expiresAt = strtotime($oldest) + (self::LOCKOUT_MINUTES * 60);

        return max(0, $expiresAt - time());
    }

    /**
     * Record a login attempt.
     *
     * @param string $identifier Email or username
     * @param string $ipAddress  Client IP
     * @param bool   $success    Whether the attempt succeeded
     */
    public static function recordAttempt(string $identifier, string $ipAddress, bool $success): void
    {
        if (!self::tableExists()) {
            return;
        }

        DB::table('login_attempt')->insert([
            'identifier' => $identifier,
            'ip_address' => $ipAddress,
            'success' => $success ? 1 : 0,
            'attempted_at' => date('Y-m-d H:i:s'),
        ]);

        // On successful login, clear previous failures for this identifier
        if ($success) {
            self::clearFailures($identifier);
        }
    }

    /**
     * Clear failed attempt records for an identifier (after successful login).
     */
    public static function clearFailures(string $identifier): void
    {
        if (!self::tableExists()) {
            return;
        }

        DB::table('login_attempt')
            ->where('identifier', $identifier)
            ->where('success', 0)
            ->delete();
    }

    /**
     * Cleanup old login attempt records (older than 24 hours).
     *
     * Call periodically via cron to prevent table growth.
     */
    public static function cleanup(): void
    {
        if (!self::tableExists()) {
            return;
        }

        $cutoff = date('Y-m-d H:i:s', time() - 86400);

        DB::table('login_attempt')
            ->where('attempted_at', '<', $cutoff)
            ->delete();
    }

    /**
     * Check if the login_attempt table exists.
     */
    private static function tableExists(): bool
    {
        static $exists = null;

        if ($exists === null) {
            try {
                $exists = DB::schema()->hasTable('login_attempt');
            } catch (\Throwable $e) {
                $exists = false;
            }
        }

        return $exists;
    }
}
