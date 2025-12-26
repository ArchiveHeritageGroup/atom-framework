<?php
/**
 * Security Clearance Service.
 *
 * Manages user security clearances and object classifications
 * Uses Laravel Query Builder - no external model dependencies
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */

namespace AtomExtensions\Services;

use Illuminate\Database\Capsule\Manager as DB;

class SecurityClearanceService
{
    private static function log(string $level, string $message, array $context = []): void
    {
        $logPath = '/var/log/atom/security.log';
        $logDir = dirname($logPath);
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }
        $logMessage = date('Y-m-d H:i:s') . " [{$level}] {$message}";
        if (!empty($context)) {
            $logMessage .= ' ' . json_encode($context);
        }
        @file_put_contents($logPath, $logMessage . PHP_EOL, FILE_APPEND);
    }

    /**
     * Get all security classifications.
     */
    public static function getAllClassifications(): array
    {
        try {
            return DB::table('security_classification')
                ->orderBy('level', 'asc')
                ->get()
                ->toArray();
        } catch (\Exception $e) {
            self::log('error', 'Failed to get classifications', ['error' => $e->getMessage()]);
            return [];
        }
    }

    /**
     * Get user's clearance level.
     */
    public static function getUserClearance(int $userId): ?object
    {
        try {
            return DB::table('user_security_clearance')
                ->where('user_id', $userId)
                ->where('is_active', 1)
                ->where(function ($query) {
                    $query->whereNull('expires_at')
                        ->orWhere('expires_at', '>', date('Y-m-d H:i:s'));
                })
                ->first();
        } catch (\Exception $e) {
            self::log('error', 'Failed to get user clearance', ['user_id' => $userId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get object classification.
     */
    public static function getObjectClassification(int $objectId): ?object
    {
        try {
            return DB::table('object_security_classification as osc')
                ->join('security_classification as sc', 'osc.classification_id', '=', 'sc.id')
                ->where('osc.object_id', $objectId)
                ->select('osc.*', 'sc.name', 'sc.level', 'sc.color', 'sc.icon')
                ->first();
        } catch (\Exception $e) {
            self::log('error', 'Failed to get object classification', ['object_id' => $objectId, 'error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Check if user can access object based on clearance.
     */
    public static function canAccess(int $userId, int $objectId): bool
    {
        $userClearance = self::getUserClearance($userId);
        $objectClassification = self::getObjectClassification($objectId);

        // No classification = public access
        if (!$objectClassification) {
            return true;
        }

        // No clearance = only public access
        if (!$userClearance) {
            return $objectClassification->level <= 0;
        }

        // Check clearance level
        return $userClearance->clearance_level >= $objectClassification->level;
    }

    /**
     * Set object classification.
     */
    public static function setObjectClassification(int $objectId, int $classificationId, int $userId): bool
    {
        try {
            DB::table('object_security_classification')->updateOrInsert(
                ['object_id' => $objectId],
                [
                    'classification_id' => $classificationId,
                    'classified_by' => $userId,
                    'classified_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]
            );
            self::log('info', 'Object classification set', [
                'object_id' => $objectId,
                'classification_id' => $classificationId,
                'user_id' => $userId,
            ]);
            return true;
        } catch (\Exception $e) {
            self::log('error', 'Failed to set classification', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Grant user clearance.
     */
    public static function grantClearance(int $userId, int $level, int $grantedBy, ?string $expiresAt = null): bool
    {
        try {
            // Deactivate existing clearance
            DB::table('user_security_clearance')
                ->where('user_id', $userId)
                ->update(['is_active' => 0]);

            // Insert new clearance
            DB::table('user_security_clearance')->insert([
                'user_id' => $userId,
                'clearance_level' => $level,
                'granted_by' => $grantedBy,
                'granted_at' => date('Y-m-d H:i:s'),
                'expires_at' => $expiresAt,
                'is_active' => 1,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            self::log('info', 'Clearance granted', [
                'user_id' => $userId,
                'level' => $level,
                'granted_by' => $grantedBy,
            ]);
            return true;
        } catch (\Exception $e) {
            self::log('error', 'Failed to grant clearance', ['error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Revoke user clearance.
     */
    public static function revokeClearance(int $userId, int $revokedBy): bool
    {
        try {
            DB::table('user_security_clearance')
                ->where('user_id', $userId)
                ->where('is_active', 1)
                ->update([
                    'is_active' => 0,
                    'revoked_by' => $revokedBy,
                    'revoked_at' => date('Y-m-d H:i:s'),
                ]);

            self::log('info', 'Clearance revoked', [
                'user_id' => $userId,
                'revoked_by' => $revokedBy,
            ]);
            return true;
        } catch (\Exception $e) {
            self::log('error', 'Failed to revoke clearance', ['error' => $e->getMessage()]);
            return false;
        }
    }
}
