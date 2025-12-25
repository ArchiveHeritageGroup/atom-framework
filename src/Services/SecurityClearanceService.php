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
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

class SecurityClearanceService
{
    private static ?Logger $logger = null;

    private static function getLogger(): Logger
    {
        if (null === self::$logger) {
            self::$logger = new Logger('security');
            $logPath = '/var/log/atom/security.log';
            $logDir = dirname($logPath);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            if (is_writable($logDir)) {
                self::$logger->pushHandler(new RotatingFileHandler($logPath, 30, Logger::DEBUG));
            }
        }
        return self::$logger;
    }

    public static function getAllClassifications(bool $activeOnly = true): array
    {
        try {
            $query = DB::table('security_classification')->orderBy('level', 'asc');
            if ($activeOnly) {
                $query->where('active', 1);
            }
            return $query->get()->toArray();
        } catch (\Exception $e) {
            self::getLogger()->error('Failed to get classifications', ['error' => $e->getMessage()]);
            return [];
        }
    }

    public static function getClassificationById(int $id): ?object
    {
        try {
            return DB::table('security_classification')->where('id', $id)->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getUserClearance(int $userId): ?object
    {
        try {
            return DB::table('user_security_clearance as usc')
                ->join('security_classification as sc', 'usc.classification_id', '=', 'sc.id')
                ->leftJoin('user as gb', 'usc.granted_by', '=', 'gb.id')
                ->leftJoin('user as u', 'usc.user_id', '=', 'u.id')
                ->where('usc.user_id', $userId)
                ->select([
                    'usc.*',
                    'sc.code as classificationCode',
                    'sc.name as classificationName',
                    'sc.level as classificationLevel',
                    'sc.color as classificationColor',
                    'sc.icon as classificationIcon',
                    'gb.username as grantedByUsername',
                    'u.username as username',
                    'u.email as userEmail',
                ])
                ->first();
        } catch (\Exception $e) {
            return null;
        }
    }

    public static function getUserClearanceLevel(int $userId): int
    {
        $clearance = self::getUserClearance($userId);
        if (!$clearance) {
            return 0;
        }
        if (isset($clearance->expires_at) && $clearance->expires_at && strtotime($clearance->expires_at) < time()) {
            return 0;
        }
        return (int) $clearance->classificationLevel;
    }

    public static function getObjectClassification(int $objectId): ?object
    {
        try {
            return DB::table('object_security_classification as osc')
                ->join('security_classification as sc', 'osc.classification_id', '=', 'sc.id')
                ->leftJoin('user as cb', 'osc.classified_by', '=', 'cb.id')
                ->where('osc.object_id', $objectId)
                ->where('osc.active', 1)
                ->select([
                    'osc.*',
                    'sc.code as classificationCode',
                    'sc.name as classificationName',
                    'sc.level as classificationLevel',
                    'sc.color as classificationColor',
                    'sc.icon as classificationIcon',
                    'cb.username as classifiedByUsername',
                ])
                ->first();
        } catch (\Exception $e) {
            self::getLogger()->error('Failed to get object classification', [
                'objectId' => $objectId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    public static function getObjectClassificationLevel(int $objectId): int
    {
        $classification = self::getObjectClassification($objectId);
        return $classification ? (int) $classification->classificationLevel : 0;
    }

    public static function classifyObject(
        int $objectId,
        int $classificationId,
        int $classifiedBy,
        ?string $reason = null,
        ?string $reviewDate = null,
        ?string $declassifyDate = null,
        ?int $declassifyToId = null,
        ?string $handlingInstructions = null,
        bool $inheritToChildren = true
    ): bool {
        self::getLogger()->info('classifyObject called', [
            'objectId' => $objectId,
            'classificationId' => $classificationId,
            'classifiedBy' => $classifiedBy,
        ]);
        
        try {
            // Verify object exists
            $objectExists = DB::table('information_object')->where('id', $objectId)->exists();
            if (!$objectExists) {
                self::getLogger()->error('Object does not exist', ['objectId' => $objectId]);
                return false;
            }
            
            // Verify classification exists
            $classificationExists = DB::table('security_classification')->where('id', $classificationId)->exists();
            if (!$classificationExists) {
                self::getLogger()->error('Classification does not exist', ['classificationId' => $classificationId]);
                return false;
            }

            // Check for ANY existing record (active or not) due to unique constraint
            $existing = DB::table('object_security_classification')
                ->where('object_id', $objectId)
                ->first();

            $updateData = [
                'classification_id' => $classificationId,
                'classified_by' => $classifiedBy,
                'classified_at' => date('Y-m-d H:i:s'),
                'reason' => $reason,
                'handling_instructions' => $handlingInstructions,
                'inherit_to_children' => $inheritToChildren ? 1 : 0,
                'active' => 1,
                'updated_at' => date('Y-m-d H:i:s'),
            ];
            
            if ($reviewDate) {
                $updateData['review_date'] = $reviewDate;
            }
            if ($declassifyDate) {
                $updateData['declassify_date'] = $declassifyDate;
            }
            if ($declassifyToId) {
                $updateData['declassify_to_id'] = $declassifyToId;
            }

            if ($existing) {
                // UPDATE existing record
                self::getLogger()->info('Updating existing classification', ['existingId' => $existing->id]);
                
                // Log to history
                try {
                    DB::table('object_classification_history')->insert([
                        'object_id' => $objectId,
                        'previous_classification_id' => $existing->classification_id,
                        'new_classification_id' => $classificationId,
                        'action' => $existing->active ? 'reclassified' : 'reactivated',
                        'changed_by' => $classifiedBy,
                        'reason' => $reason,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                } catch (\Exception $historyError) {
                    self::getLogger()->warning('Could not log to history', ['error' => $historyError->getMessage()]);
                }
                
                DB::table('object_security_classification')
                    ->where('id', $existing->id)
                    ->update($updateData);
                    
                self::getLogger()->info('Classification updated', ['id' => $existing->id]);
                return true;
            } else {
                // INSERT new record
                self::getLogger()->info('Inserting new classification');
                
                $insertData = array_merge($updateData, [
                    'object_id' => $objectId,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                $id = DB::table('object_security_classification')->insertGetId($insertData);
                
                self::getLogger()->info('Classification saved', ['newId' => $id]);
                return $id > 0;
            }
        } catch (\Exception $e) {
            self::getLogger()->error('Failed to classify object', [
                'objectId' => $objectId, 
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return false;
        }
    }

    public static function declassifyObject(int $objectId, int $declassifiedBy, ?string $reason = null): bool
    {
        self::getLogger()->info('declassifyObject called', ['objectId' => $objectId]);
        
        try {
            $existing = DB::table('object_security_classification')
                ->where('object_id', $objectId)
                ->where('active', 1)
                ->first();

            if (!$existing) {
                self::getLogger()->info('No active classification to declassify', ['objectId' => $objectId]);
                return false;
            }

            // Log to history
            try {
                DB::table('object_classification_history')->insert([
                    'object_id' => $objectId,
                    'previous_classification_id' => $existing->classification_id,
                    'new_classification_id' => null,
                    'action' => 'declassified',
                    'changed_by' => $declassifiedBy,
                    'reason' => $reason,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Exception $historyError) {
                self::getLogger()->warning('Could not log declassification to history', ['error' => $historyError->getMessage()]);
            }

            DB::table('object_security_classification')
                ->where('id', $existing->id)
                ->update(['active' => 0, 'updated_at' => date('Y-m-d H:i:s')]);

            self::getLogger()->info('Object declassified', ['objectId' => $objectId]);
            return true;
        } catch (\Exception $e) {
            self::getLogger()->error('Failed to declassify', ['objectId' => $objectId, 'error' => $e->getMessage()]);
            return false;
        }
    }

    public static function canUserAccessObject(int $userId, int $objectId, bool $isAdmin = false): bool
    {
        if ($isAdmin) {
            return true;
        }
        return self::getUserClearanceLevel($userId) >= self::getObjectClassificationLevel($objectId);
    }

    public static function getAccessibleClassificationIds(int $userId, bool $isAdmin = false): array
    {
        $userLevel = $isAdmin ? 999 : self::getUserClearanceLevel($userId);
        try {
            return DB::table('security_classification')
                ->where('level', '<=', $userLevel)
                ->where('active', 1)
                ->pluck('id')
                ->toArray();
        } catch (\Exception $e) {
            return [1];
        }
    }

    /**
     * Grant or update user clearance
     */
    public static function grantClearance(
        int $userId,
        int $classificationId,
        int $grantedBy,
        ?string $expiresAt = null,
        ?string $notes = null
    ): bool {
        try {
            // Verify user exists
            $userExists = DB::table('user')->where('id', $userId)->exists();
            if (!$userExists) {
                self::getLogger()->error('User does not exist', ['userId' => $userId]);
                return false;
            }

            // Verify classification exists
            $classification = DB::table('security_classification')->where('id', $classificationId)->first();
            if (!$classification) {
                self::getLogger()->error('Classification does not exist', ['classificationId' => $classificationId]);
                return false;
            }

            // Check for existing clearance
            $existing = DB::table('user_security_clearance')->where('user_id', $userId)->first();

            $data = [
                'classification_id' => $classificationId,
                'granted_by' => $grantedBy,
                'granted_at' => date('Y-m-d H:i:s'),
                'expires_at' => $expiresAt,
                'notes' => $notes,
            ];

            DB::beginTransaction();

            if ($existing) {
                // Log the change
                try {
                    DB::table('user_security_clearance_log')->insert([
                        'user_id' => $userId,
                        'classification_id' => $classificationId,
                        'action' => 'updated',
                        'changed_by' => $grantedBy,
                        'notes' => "Changed from classification {$existing->classification_id} to {$classificationId}. " . ($notes ?? ''),
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                } catch (\Exception $logError) {
                    self::getLogger()->warning('Could not log clearance change', ['error' => $logError->getMessage()]);
                }

                DB::table('user_security_clearance')
                    ->where('user_id', $userId)
                    ->update($data);
            } else {
                // Log the grant
                try {
                    DB::table('user_security_clearance_log')->insert([
                        'user_id' => $userId,
                        'classification_id' => $classificationId,
                        'action' => 'granted',
                        'changed_by' => $grantedBy,
                        'notes' => $notes,
                        'created_at' => date('Y-m-d H:i:s'),
                    ]);
                } catch (\Exception $logError) {
                    self::getLogger()->warning('Could not log clearance grant', ['error' => $logError->getMessage()]);
                }

                $data['user_id'] = $userId;
                DB::table('user_security_clearance')->insert($data);
            }

            DB::commit();

            self::getLogger()->info('Clearance granted', [
                'userId' => $userId,
                'classificationId' => $classificationId,
                'grantedBy' => $grantedBy
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            self::getLogger()->error('Failed to grant clearance', [
                'userId' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Revoke user clearance
     */
    public static function revokeClearance(int $userId, int $revokedBy, ?string $notes = null): bool
    {
        try {
            $existing = DB::table('user_security_clearance')->where('user_id', $userId)->first();

            if (!$existing) {
                self::getLogger()->info('No clearance to revoke', ['userId' => $userId]);
                return false;
            }

            DB::beginTransaction();

            // Log the revocation
            try {
                DB::table('user_security_clearance_log')->insert([
                    'user_id' => $userId,
                    'classification_id' => $existing->classification_id,
                    'action' => 'revoked',
                    'changed_by' => $revokedBy,
                    'notes' => $notes,
                    'created_at' => date('Y-m-d H:i:s'),
                ]);
            } catch (\Exception $logError) {
                self::getLogger()->warning('Could not log clearance revocation', ['error' => $logError->getMessage()]);
            }

            // Delete the clearance
            DB::table('user_security_clearance')->where('user_id', $userId)->delete();

            DB::commit();

            self::getLogger()->info('Clearance revoked', ['userId' => $userId, 'revokedBy' => $revokedBy]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            self::getLogger()->error('Failed to revoke clearance', [
                'userId' => $userId,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
}
