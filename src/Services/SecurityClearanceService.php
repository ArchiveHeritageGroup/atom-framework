<?php

namespace AtomExtensions\Services;

use Illuminate\Database\Capsule\Manager as DB;

class SecurityClearanceService
{
    /**
     * Grant clearance to a user
     */
    public static function grantClearance(int $userId, int $classificationId, int $grantedBy, ?string $expiresAt = null, ?string $notes = null): bool
    {
        try {
            // Check if user already has clearance - update or insert
            $existing = DB::table('user_security_clearance')
                ->where('user_id', $userId)
                ->first();

            if ($existing) {
                DB::table('user_security_clearance')
                    ->where('user_id', $userId)
                    ->update([
                        'classification_id' => $classificationId,
                        'granted_by' => $grantedBy,
                        'granted_at' => date('Y-m-d H:i:s'),
                        'expires_at' => $expiresAt,
                        'notes' => $notes,
                    ]);
            } else {
                DB::table('user_security_clearance')->insert([
                    'user_id' => $userId,
                    'classification_id' => $classificationId,
                    'granted_by' => $grantedBy,
                    'granted_at' => date('Y-m-d H:i:s'),
                    'expires_at' => $expiresAt,
                    'notes' => $notes,
                ]);
            }

            self::log('info', 'Clearance granted', [
                'user_id' => $userId,
                'classification_id' => $classificationId,
                'granted_by' => $grantedBy,
            ]);

            return true;
        } catch (\Exception $e) {
            self::log('error', 'Failed to grant clearance', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Revoke clearance from a user
     */
    public static function revokeClearance(int $userId, int $revokedBy, ?string $notes = null): bool
    {
        try {
            DB::table('user_security_clearance')
                ->where('user_id', $userId)
                ->delete();

            self::log('info', 'Clearance revoked', [
                'user_id' => $userId,
                'revoked_by' => $revokedBy,
                'notes' => $notes,
            ]);

            return true;
        } catch (\Exception $e) {
            self::log('error', 'Failed to revoke clearance', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Get user's current clearance
     */
    public static function getUserClearance(int $userId): ?object
    {
        return DB::table('user_security_clearance as usc')
            ->leftJoin('security_classification as sc', 'usc.classification_id', '=', 'sc.id')
            ->where('usc.user_id', $userId)
            ->where(function ($q) {
                $q->whereNull('usc.expires_at')
                  ->orWhere('usc.expires_at', '>', date('Y-m-d H:i:s'));
            })
            ->select('usc.*', 'sc.name as classification_name', 'sc.level')
            ->first();
    }

    /**
     * Check if user has clearance for a classification level
     */
    public static function hasAccess(int $userId, int $requiredLevel): bool
    {
        $clearance = self::getUserClearance($userId);
        if (!$clearance) {
            return false;
        }
        return ($clearance->level ?? 0) >= $requiredLevel;
    }

    /**
     * Log action
     */
    private static function log(string $level, string $message, array $context = []): void
    {
        try {
            DB::table('user_security_clearance_log')->insert([
                'level' => $level,
                'message' => $message,
                'context' => json_encode($context),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            error_log("Security log failed: " . $e->getMessage());
        }
    }
}
