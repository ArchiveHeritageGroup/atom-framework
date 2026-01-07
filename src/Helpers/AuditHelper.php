<?php
namespace AtomFramework\Helpers;

use Illuminate\Database\Capsule\Manager as DB;

class AuditHelper
{
    /**
     * Log an audit entry with old/new values
     */
    public static function log(
        string $action,
        string $entityType,
        int $entityId,
        array $oldValues,
        array $newValues,
        ?string $entitySlug = null,
        ?string $entityTitle = null,
        string $module = 'unknown'
    ): void {
        try {
            $user = null;
            $userId = null;
            $username = null;

            if (class_exists('sfContext') && \sfContext::hasInstance()) {
                $context = \sfContext::getInstance();
                $user = $context->getUser();
                if ($user && $user->isAuthenticated()) {
                    $userId = $user->getAttribute('user_id');
                    if ($userId) {
                        $userRecord = DB::table('user')->where('id', $userId)->first();
                        $username = $userRecord->username ?? null;
                    }
                }
            }

            $changedFields = [];
            foreach ($newValues as $key => $newVal) {
                $oldVal = $oldValues[$key] ?? null;
                if ($newVal !== $oldVal) {
                    $changedFields[] = $key;
                }
            }

            $uuid = sprintf(
                '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
                mt_rand(0, 0xffff), mt_rand(0, 0xffff),
                mt_rand(0, 0xffff),
                mt_rand(0, 0x0fff) | 0x4000,
                mt_rand(0, 0x3fff) | 0x8000,
                mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
            );

            DB::table('ahg_audit_log')->insert([
                'uuid' => $uuid,
                'user_id' => $userId,
                'username' => $username,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
                'session_id' => session_id() ?: null,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'entity_slug' => $entitySlug,
                'entity_title' => $entityTitle ?? $newValues['title'] ?? null,
                'module' => $module,
                'action_name' => $action,
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? null,
                'request_uri' => $_SERVER['REQUEST_URI'] ?? null,
                'old_values' => !empty($oldValues) ? json_encode($oldValues) : null,
                'new_values' => !empty($newValues) ? json_encode($newValues) : null,
                'changed_fields' => !empty($changedFields) ? json_encode($changedFields) : null,
                'status' => 'success',
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Exception $e) {
            error_log("AuditHelper ERROR: " . $e->getMessage());
        }
    }

    /**
     * Capture values from a database table
     */
    public static function captureValues(string $table, string $idColumn, int $id, array $fields, ?string $i18nTable = null): array
    {
        try {
            $values = [];
            $row = DB::table($table)->where($idColumn, $id)->first();
            if ($row) {
                foreach ($fields as $field) {
                    if (isset($row->$field) && $row->$field !== null && $row->$field !== '') {
                        $values[$field] = $row->$field;
                    }
                }
            }
            
            // Get i18n fields if table specified
            if ($i18nTable) {
                $i18nRow = DB::table($i18nTable)
                    ->where('id', $id)
                    ->where('culture', 'en')
                    ->first();
                if ($i18nRow) {
                    foreach (['title', 'description', 'name'] as $field) {
                        if (isset($i18nRow->$field) && $i18nRow->$field !== null && $i18nRow->$field !== '') {
                            $values[$field] = $i18nRow->$field;
                        }
                    }
                }
            }
            
            return $values;
        } catch (\Exception $e) {
            error_log("AuditHelper captureValues ERROR: " . $e->getMessage());
            return [];
        }
    }
}
