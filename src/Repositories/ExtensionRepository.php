<?php

namespace AtomFramework\Repositories;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

class ExtensionRepository
{
    protected string $table = 'atom_extension';
    protected string $settingsTable = 'atom_extension_setting';
    protected string $auditTable = 'atom_extension_audit';
    protected string $pendingTable = 'atom_extension_pending_deletion';

    /**
     * Get all extensions
     */
    public function all(): Collection
    {
        return DB::table($this->table)
            ->orderBy('display_name')
            ->get();
    }

    /**
     * Get extensions by status
     */
    public function getByStatus(string $status): Collection
    {
        return DB::table($this->table)
            ->where('status', $status)
            ->orderBy('display_name')
            ->get();
    }

    /**
     * Find by machine name
     */
    public function findByMachineName(string $machineName): ?object
    {
        return DB::table($this->table)
            ->where('machine_name', $machineName)
            ->first();
    }

    /**
     * Find by ID
     */
    public function find(int $id): ?object
    {
        return DB::table($this->table)
            ->where('id', $id)
            ->first();
    }

    /**
     * Create extension record
     */
    public function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        return DB::table($this->table)->insertGetId($data);
    }

    /**
     * Update extension record
     */
    public function update(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        return DB::table($this->table)
            ->where('id', $id)
            ->update($data) > 0;
    }

    /**
     * Update by machine name
     */
    public function updateByMachineName(string $machineName, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');
        
        return DB::table($this->table)
            ->where('machine_name', $machineName)
            ->update($data) > 0;
    }

    /**
     * Delete extension record
     */
    public function delete(int $id): bool
    {
        return DB::table($this->table)
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Check if extension exists
     */
    public function exists(string $machineName): bool
    {
        return DB::table($this->table)
            ->where('machine_name', $machineName)
            ->exists();
    }

    // ==========================================
    // Settings Methods
    // ==========================================

    /**
     * Get setting value
     */
    public function getSetting(string $key, ?int $extensionId = null, $default = null)
    {
        $query = DB::table($this->settingsTable)
            ->where('setting_key', $key);
        
        if ($extensionId === null) {
            $query->whereNull('extension_id');
        } else {
            $query->where('extension_id', $extensionId);
        }
        
        $setting = $query->first();
        
        if (!$setting) {
            return $default;
        }
        
        return $this->castSettingValue($setting->setting_value, $setting->setting_type);
    }

    /**
     * Set setting value
     */
    public function setSetting(string $key, $value, ?int $extensionId = null, string $type = 'string'): bool
    {
        $stringValue = is_array($value) ? json_encode($value) : (string)$value;

        $exists = DB::table($this->settingsTable)
            ->where('setting_key', $key)
            ->where(function($q) use ($extensionId) {
                if ($extensionId === null) {
                    $q->whereNull('extension_id');
                } else {
                    $q->where('extension_id', $extensionId);
                }
            })
            ->exists();

        if ($exists) {
            return DB::table($this->settingsTable)
                ->where('setting_key', $key)
                ->where(function($q) use ($extensionId) {
                    if ($extensionId === null) {
                        $q->whereNull('extension_id');
                    } else {
                        $q->where('extension_id', $extensionId);
                    }
                })
                ->update([
                    'setting_value' => $stringValue,
                    'setting_type' => $type,
                    'updated_at' => date('Y-m-d H:i:s'),
                ]) >= 0;
        }

        return DB::table($this->settingsTable)->insert([
            'extension_id' => $extensionId,
            'setting_key' => $key,
            'setting_value' => $stringValue,
            'setting_type' => $type,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get all settings for extension
     */
    public function getSettings(?int $extensionId = null): Collection
    {
        $query = DB::table($this->settingsTable);
        
        if ($extensionId === null) {
            $query->whereNull('extension_id');
        } else {
            $query->where('extension_id', $extensionId);
        }
        
        return $query->get();
    }

    // ==========================================
    // Audit Methods
    // ==========================================

    /**
     * Log an action
     */
    public function logAction(string $extensionName, string $action, ?int $extensionId = null, ?int $userId = null, array $details = []): int
    {
        return DB::table($this->auditTable)->insertGetId([
            'extension_id' => $extensionId,
            'extension_name' => $extensionName,
            'action' => $action,
            'performed_by' => $userId,
            'details' => !empty($details) ? json_encode($details) : null,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get audit log for extension
     */
    public function getAuditLog(?string $extensionName = null, int $limit = 50): Collection
    {
        $query = DB::table($this->auditTable)
            ->orderBy('created_at', 'desc')
            ->limit($limit);
        
        if ($extensionName) {
            $query->where('extension_name', $extensionName);
        }
        
        return $query->get();
    }

    // ==========================================
    // Pending Deletion Methods
    // ==========================================

    /**
     * Queue table for deletion
     */
    public function queueForDeletion(string $extensionName, string $tableName, int $recordCount, ?string $backupPath, \DateTime $deleteAfter): int
    {
        return DB::table($this->pendingTable)->insertGetId([
            'extension_name' => $extensionName,
            'table_name' => $tableName,
            'record_count' => $recordCount,
            'backup_path' => $backupPath,
            'delete_after' => $deleteAfter->format('Y-m-d H:i:s'),
            'status' => 'pending',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get pending deletions ready to process
     */
    public function getPendingDeletions(): Collection
    {
        return DB::table($this->pendingTable)
            ->where('status', 'pending')
            ->where('delete_after', '<=', date('Y-m-d H:i:s'))
            ->get();
    }

    /**
     * Get pending deletions for extension
     */
    public function getPendingForExtension(string $extensionName): Collection
    {
        return DB::table($this->pendingTable)
            ->where('extension_name', $extensionName)
            ->where('status', 'pending')
            ->get();
    }

    /**
     * Cancel pending deletion
     */
    public function cancelPendingDeletion(string $extensionName): bool
    {
        return DB::table($this->pendingTable)
            ->where('extension_name', $extensionName)
            ->where('status', 'pending')
            ->update([
                'status' => 'cancelled',
                'updated_at' => date('Y-m-d H:i:s'),
            ]) > 0;
    }

    /**
     * Update pending deletion status
     */
    public function updatePendingStatus(int $id, string $status, ?string $error = null): bool
    {
        return DB::table($this->pendingTable)
            ->where('id', $id)
            ->update([
                'status' => $status,
                'error_message' => $error,
                'processed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]) > 0;
    }

    // ==========================================
    // Helper Methods
    // ==========================================

    /**
     * Cast setting value to proper type
     */
    protected function castSettingValue($value, string $type)
    {
        return match($type) {
            'integer' => (int)$value,
            'boolean' => (bool)$value,
            'json', 'array' => json_decode($value, true),
            default => $value,
        };
    }
}