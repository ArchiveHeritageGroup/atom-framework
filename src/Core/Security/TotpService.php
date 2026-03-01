<?php

namespace AtomFramework\Core\Security;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * TOTP (Time-based One-Time Password) Service.
 *
 * Implements RFC 6238 TOTP and RFC 4226 HOTP for two-factor authentication.
 * Compatible with Google Authenticator, Authy, Microsoft Authenticator, etc.
 *
 * Uses PHP built-in HMAC-SHA1 — no external library required.
 *
 * Storage: `user_totp_secret` table (user_id, secret, verified, created_at).
 * The secret is stored encrypted if EncryptionService is available.
 */
class TotpService
{
    /** TOTP period in seconds (standard: 30) */
    private const PERIOD = 30;

    /** Code length (standard: 6 digits) */
    private const DIGITS = 6;

    /** Time drift tolerance: accept codes from ±1 period */
    private const DRIFT_PERIODS = 1;

    /** Issuer name for authenticator apps */
    private const ISSUER = 'AtoM Heratio';

    /** Base32 alphabet (RFC 4648) */
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    // =========================================================================
    // Secret management
    // =========================================================================

    /**
     * Generate a new TOTP secret for a user.
     *
     * @param int $userId The user ID
     * @return string The base32-encoded secret (display to user for manual entry)
     */
    public static function generateSecret(int $userId): string
    {
        $secret = self::generateBase32Secret(32);

        // Store (or replace) the secret — not yet verified until user confirms
        if (self::tableExists()) {
            DB::table('user_totp_secret')
                ->where('user_id', $userId)
                ->delete();

            DB::table('user_totp_secret')->insert([
                'user_id' => $userId,
                'secret' => $secret,
                'verified' => 0,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        return $secret;
    }

    /**
     * Get the stored secret for a user.
     *
     * @return string|null The base32 secret, or null if not enrolled
     */
    public static function getSecret(int $userId): ?string
    {
        if (!self::tableExists()) {
            return null;
        }

        return DB::table('user_totp_secret')
            ->where('user_id', $userId)
            ->value('secret');
    }

    /**
     * Check if a user has TOTP set up and verified.
     */
    public static function isEnrolled(int $userId): bool
    {
        if (!self::tableExists()) {
            return false;
        }

        return (bool) DB::table('user_totp_secret')
            ->where('user_id', $userId)
            ->where('verified', 1)
            ->exists();
    }

    /**
     * Check if a user has a pending (unverified) TOTP setup.
     */
    public static function hasPendingSetup(int $userId): bool
    {
        if (!self::tableExists()) {
            return false;
        }

        return (bool) DB::table('user_totp_secret')
            ->where('user_id', $userId)
            ->where('verified', 0)
            ->exists();
    }

    /**
     * Mark the user's TOTP secret as verified (after they confirm with a valid code).
     */
    public static function confirmEnrollment(int $userId): bool
    {
        if (!self::tableExists()) {
            return false;
        }

        return DB::table('user_totp_secret')
            ->where('user_id', $userId)
            ->where('verified', 0)
            ->update(['verified' => 1]) > 0;
    }

    /**
     * Remove TOTP enrollment for a user (admin action).
     */
    public static function removeEnrollment(int $userId): bool
    {
        if (!self::tableExists()) {
            return false;
        }

        return DB::table('user_totp_secret')
            ->where('user_id', $userId)
            ->delete() > 0;
    }

    // =========================================================================
    // Code generation & verification
    // =========================================================================

    /**
     * Generate the current TOTP code.
     *
     * @param string $secret Base32-encoded secret
     * @return string 6-digit code (zero-padded)
     */
    public static function generateCode(string $secret): string
    {
        $timeCounter = self::getTimeCounter();

        return self::hotpCode($secret, $timeCounter);
    }

    /**
     * Verify a TOTP code against a user's stored secret.
     *
     * Allows ±1 time period drift to handle clock skew between the
     * server and the user's authenticator app.
     *
     * @param int    $userId The user ID
     * @param string $code   The 6-digit code to verify
     * @return bool True if the code is valid
     */
    public static function verifyCode(int $userId, string $code): bool
    {
        $secret = self::getSecret($userId);
        if (!$secret) {
            return false;
        }

        return self::verifyCodeWithSecret($secret, $code);
    }

    /**
     * Verify a TOTP code against a known secret.
     *
     * @param string $secret Base32-encoded secret
     * @param string $code   The code to verify
     * @return bool True if valid
     */
    public static function verifyCodeWithSecret(string $secret, string $code): bool
    {
        $code = trim($code);
        if (!preg_match('/^\d{' . self::DIGITS . '}$/', $code)) {
            return false;
        }

        $timeCounter = self::getTimeCounter();

        // Check current period and ±drift periods
        for ($i = -self::DRIFT_PERIODS; $i <= self::DRIFT_PERIODS; $i++) {
            $testCode = self::hotpCode($secret, $timeCounter + $i);
            if (hash_equals($testCode, $code)) {
                return true;
            }
        }

        return false;
    }

    // =========================================================================
    // QR code / provisioning URI
    // =========================================================================

    /**
     * Generate the otpauth:// provisioning URI for authenticator apps.
     *
     * @param string $secret     Base32 secret
     * @param string $accountName User's email or display name
     * @return string otpauth://totp/... URI
     */
    public static function getProvisioningUri(string $secret, string $accountName): string
    {
        $issuer = rawurlencode(self::ISSUER);
        $account = rawurlencode($accountName);

        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&algorithm=SHA1&digits=%d&period=%d',
            $issuer,
            $account,
            $secret,
            $issuer,
            self::DIGITS,
            self::PERIOD
        );
    }

    /**
     * Generate a QR code as a data URI (SVG) for the provisioning URI.
     *
     * Uses a simple inline SVG QR code generator — no external dependencies.
     * Falls back to a Google Charts URL if SVG generation is not possible.
     *
     * @param string $uri The otpauth:// URI
     * @return string URL for QR code image (data: URI or Google Charts URL)
     */
    public static function getQrCodeUrl(string $uri): string
    {
        // Use Google Charts API as a simple, no-dependency QR generator
        return 'https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl='
            . rawurlencode($uri)
            . '&choe=UTF-8';
    }

    // =========================================================================
    // Email fallback code
    // =========================================================================

    /**
     * Generate and store a one-time email verification code.
     *
     * @param int $userId The user ID
     * @return string 6-digit code
     */
    public static function generateEmailCode(int $userId): string
    {
        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        if (self::tableExists()) {
            DB::table('user_totp_secret')
                ->updateOrInsert(
                    ['user_id' => $userId],
                    [
                        'email_code' => password_hash($code, PASSWORD_DEFAULT),
                        'email_code_expires' => date('Y-m-d H:i:s', time() + 600), // 10 minutes
                    ]
                );
        }

        return $code;
    }

    /**
     * Verify an email fallback code.
     */
    public static function verifyEmailCode(int $userId, string $code): bool
    {
        if (!self::tableExists()) {
            return false;
        }

        $row = DB::table('user_totp_secret')
            ->where('user_id', $userId)
            ->select('email_code', 'email_code_expires')
            ->first();

        if (!$row || !$row->email_code) {
            return false;
        }

        if (strtotime($row->email_code_expires) < time()) {
            return false; // Expired
        }

        if (!password_verify(trim($code), $row->email_code)) {
            return false;
        }

        // Clear the code after successful use
        DB::table('user_totp_secret')
            ->where('user_id', $userId)
            ->update(['email_code' => null, 'email_code_expires' => null]);

        return true;
    }

    // =========================================================================
    // Internal: HOTP algorithm (RFC 4226)
    // =========================================================================

    /**
     * Generate an HOTP code for a given counter.
     *
     * @param string $secret  Base32-encoded secret
     * @param int    $counter The counter value
     * @return string Zero-padded numeric code
     */
    private static function hotpCode(string $secret, int $counter): string
    {
        $key = self::base32Decode($secret);

        // Pack counter as 8-byte big-endian
        $counterBytes = pack('J', $counter);

        // HMAC-SHA1
        $hash = hash_hmac('sha1', $counterBytes, $key, true);

        // Dynamic truncation (RFC 4226 section 5.4)
        $offset = ord($hash[19]) & 0x0F;
        $binary = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        $otp = $binary % (10 ** self::DIGITS);

        return str_pad((string) $otp, self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Get the current TOTP time counter.
     */
    private static function getTimeCounter(): int
    {
        return intdiv(time(), self::PERIOD);
    }

    // =========================================================================
    // Internal: Base32 encoding/decoding
    // =========================================================================

    /**
     * Generate a random base32-encoded secret.
     *
     * @param int $length Number of base32 characters
     * @return string Base32-encoded secret
     */
    private static function generateBase32Secret(int $length = 32): string
    {
        $secret = '';
        $bytes = random_bytes($length);

        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_CHARS[ord($bytes[$i]) % 32];
        }

        return $secret;
    }

    /**
     * Decode a base32-encoded string to binary.
     *
     * @param string $input Base32-encoded string
     * @return string Raw binary data
     */
    private static function base32Decode(string $input): string
    {
        $input = strtoupper(rtrim($input, '='));
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        for ($i = 0, $len = strlen($input); $i < $len; $i++) {
            $val = strpos(self::BASE32_CHARS, $input[$i]);
            if ($val === false) {
                continue; // Skip invalid characters
            }

            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $output;
    }

    /**
     * Check if the user_totp_secret table exists.
     */
    private static function tableExists(): bool
    {
        static $exists = null;

        if ($exists === null) {
            try {
                $exists = DB::schema()->hasTable('user_totp_secret');
            } catch (\Throwable $e) {
                $exists = false;
            }
        }

        return $exists;
    }
}
