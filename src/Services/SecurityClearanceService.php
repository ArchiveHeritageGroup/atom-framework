<?php

namespace AtomExtensions\Services;

use Illuminate\Database\Capsule\Manager as DB;

class SecurityClearanceService
{
    /**
     * Get all security classifications
     */
    public static function getAllClassifications(): array
    {
        return DB::table('security_classification')
            ->orderBy('level', 'asc')
            ->get()
            ->toArray();
    }

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
    /**
     * Log action
     */

    /**
     * Get classification for an information object
     */
    public static function getObjectClassification(int $objectId): ?object
    {
        return DB::table('security_classification_object')
            ->join('security_classification', 'security_classification_object.classification_id', '=', 'security_classification.id')
            ->where('security_classification_object.object_id', $objectId)
            ->select('security_classification.*')
            ->first();
    }
    private static function log(string $action, string $message, array $context = []): void
    {
        try {
            DB::table('user_security_clearance_log')->insert([
                'user_id' => $context['user_id'] ?? 0,
                'classification_id' => $context['classification_id'] ?? null,
                'action' => $action === 'info' ? 'granted' : 'revoked',
                'changed_by' => $context['granted_by'] ?? $context['revoked_by'] ?? null,
                'notes' => $message . ' - ' . json_encode($context),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            error_log("Security log failed: " . $e->getMessage());
        }
    }
}
