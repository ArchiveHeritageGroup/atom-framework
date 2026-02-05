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
     * Get user's current clearance (active only)
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
     * Get user's clearance record including expired (for admin view)
     */
    public static function getUserClearanceRecord(int $userId): ?object
    {
        $clearance = DB::table('user_security_clearance as usc')
            ->leftJoin('security_classification as sc', 'usc.classification_id', '=', 'sc.id')
            ->where('usc.user_id', $userId)
            ->select('usc.*', 'sc.name as classification_name', 'sc.level')
            ->first();

        if ($clearance) {
            // Add expired flag
            $clearance->is_expired = $clearance->expires_at && strtotime($clearance->expires_at) < time();
        }

        return $clearance;
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
        return DB::table('object_security_classification as osc')
            ->join('security_classification as sc', 'osc.classification_id', '=', 'sc.id')
            ->where('osc.object_id', $objectId)
            ->select('osc.*', 'sc.name', 'sc.code', 'sc.level', 'sc.color')
            ->first();
    }

    /**
     * Get classification by ID
     */
    public static function getClassification(int $id): ?object
    {
        return DB::table('security_classification')
            ->where('id', $id)
            ->first();
    }

    /**
     * Get effective classification (including inherited from parents)
     */
    public static function getEffectiveClassification(int $objectId): ?object
    {
        // Check direct classification first
        $direct = self::getObjectClassification($objectId);
        if ($direct) {
            return $direct;
        }

        // Check parent hierarchy
        $parentId = DB::table('information_object')
            ->where('id', $objectId)
            ->value('parent_id');

        // QubitInformationObject::ROOT_ID = 1
        if ($parentId && $parentId != 1) {
            $parentClass = self::getEffectiveClassification($parentId);
            if ($parentClass && ($parentClass->inherit_to_children ?? true)) {
                return $parentClass;
            }
        }

        return null;
    }

    /**
     * Get the effective classification of an object's parent (for escalation validation)
     */
    public static function getParentEffectiveClassification(int $objectId): ?object
    {
        $parentId = DB::table('information_object')
            ->where('id', $objectId)
            ->value('parent_id');

        // QubitInformationObject::ROOT_ID = 1
        if (!$parentId || $parentId == 1) {
            return null;
        }

        return self::getEffectiveClassification($parentId);
    }

    /**
     * Classify an object with escalation constraint validation
     *
     * @return array{success: bool, error: string|null}
     */
    public static function classifyObject(int $objectId, int $classificationId, array $data, int $classifiedBy): array
    {
        try {
            // Get the new classification level
            $newClassification = self::getClassification($classificationId);
            if (!$newClassification) {
                return ['success' => false, 'error' => 'Invalid classification level'];
            }

            // ESCALATION CONSTRAINT: Child records cannot have a LOWER classification than parent
            $parentClassification = self::getParentEffectiveClassification($objectId);
            if ($parentClassification) {
                if ($newClassification->level < $parentClassification->level) {
                    return [
                        'success' => false,
                        'error' => sprintf(
                            'Cannot set classification to "%s" (level %d). Parent record has classification "%s" (level %d). Child records can only escalate to a higher classification level, not lower.',
                            $newClassification->name,
                            $newClassification->level,
                            $parentClassification->name,
                            $parentClassification->level
                        ),
                    ];
                }
            }

            // Remove existing classification
            DB::table('object_security_classification')
                ->where('object_id', $objectId)
                ->delete();

            DB::table('object_security_classification')->insert([
                'object_id' => $objectId,
                'classification_id' => $classificationId,
                'classified_by' => $classifiedBy,
                'classified_date' => date('Y-m-d'),
                'review_date' => $data['review_date'] ?? null,
                'declassify_date' => $data['declassify_date'] ?? null,
                'reason' => $data['reason'] ?? null,
                'handling_instructions' => $data['handling_instructions'] ?? null,
                'inherit_to_children' => $data['inherit_to_children'] ?? 1,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            return ['success' => true, 'error' => null];
        } catch (\Exception $e) {
            error_log('SecurityClearance: Classification failed - ' . $e->getMessage());
            return ['success' => false, 'error' => 'Classification failed: ' . $e->getMessage()];
        }
    }

    /**
     * Declassify an object (remove or downgrade classification)
     */
    public static function declassifyObject(int $objectId, ?int $newClassificationId, int $declassifiedBy, ?string $reason = null): bool
    {
        $current = self::getObjectClassification($objectId);
        if (!$current) {
            return false;
        }

        try {
            if ($newClassificationId) {
                // Downgrade to new level
                DB::table('object_security_classification')
                    ->where('object_id', $objectId)
                    ->update([
                        'classification_id' => $newClassificationId,
                        'declassify_date' => null,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
            } else {
                // Remove classification entirely
                DB::table('object_security_classification')
                    ->where('object_id', $objectId)
                    ->delete();
            }

            return true;
        } catch (\Exception $e) {
            error_log('SecurityClearance: Declassification failed - ' . $e->getMessage());
            return false;
        }
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
