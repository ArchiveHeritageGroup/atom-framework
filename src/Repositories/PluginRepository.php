<?php

declare(strict_types=1);

namespace AtomFramework\Repositories;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Plugin Repository
 *
 * Manages atom_plugin table operations using Laravel Query Builder.
 */
class PluginRepository
{
    protected const TABLE_PLUGIN = 'atom_plugin';
    protected const TABLE_DEPENDENCY = 'atom_plugin_dependency';
    protected const TABLE_AUDIT = 'atom_plugin_audit';

    /**
     * Find all plugins with optional filters.
     */
    public function findAll(array $filters = []): array
    {
        $query = DB::table(self::TABLE_PLUGIN);

        if (isset($filters['is_enabled'])) {
            $query->where('is_enabled', $filters['is_enabled'] ? 1 : 0);
        }
        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }
        if (isset($filters['is_core'])) {
            $query->where('is_core', $filters['is_core'] ? 1 : 0);
        }

        return $query->orderBy('load_order')
            ->orderBy('name')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    /**
     * Find all enabled plugin names.
     */
    public function findEnabled(): array
    {
        return DB::table(self::TABLE_PLUGIN)
            ->where('is_enabled', 1)
            ->orderBy('load_order')
            ->orderBy('name')
            ->pluck('name')
            ->toArray();
    }

    /**
     * Find a plugin by name.
     */
    public function findByName(string $name): ?array
    {
        $result = DB::table(self::TABLE_PLUGIN)
            ->where('name', $name)
            ->first();

        return $result ? (array) $result : null;
    }

    /**
     * Check if a plugin exists.
     */
    public function exists(string $name): bool
    {
        return DB::table(self::TABLE_PLUGIN)
            ->where('name', $name)
            ->exists();
    }

    /**
     * Check if a plugin is enabled.
     */
    public function isEnabled(string $name): bool
    {
        return DB::table(self::TABLE_PLUGIN)
            ->where('name', $name)
            ->where('is_enabled', 1)
            ->exists();
    }

    /**
     * Enable a plugin.
     */
    public function enable(string $name): bool
    {
        $now = date('Y-m-d H:i:s');

        return DB::table(self::TABLE_PLUGIN)
            ->where('name', $name)
            ->update([
                'is_enabled' => 1,
                'enabled_at' => $now,
                'disabled_at' => null,
                'updated_at' => $now,
            ]) > 0;
    }

    /**
     * Disable a plugin.
     */
    public function disable(string $name): bool
    {
        $now = date('Y-m-d H:i:s');

        return DB::table(self::TABLE_PLUGIN)
            ->where('name', $name)
            ->update([
                'is_enabled' => 0,
                'disabled_at' => $now,
                'updated_at' => $now,
            ]) > 0;
    }

    /**
     * Get dependencies for a plugin.
     */
    public function getDependencies(int $pluginId): array
    {
        return DB::table(self::TABLE_DEPENDENCY)
            ->where('plugin_id', $pluginId)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    /**
     * Get plugins that depend on a given plugin.
     */
    public function getDependents(string $pluginName): array
    {
        return DB::table(self::TABLE_DEPENDENCY . ' as d')
            ->join(self::TABLE_PLUGIN . ' as p', 'p.id', '=', 'd.plugin_id')
            ->where('d.requires_plugin', $pluginName)
            ->where('p.is_enabled', 1)
            ->select('p.name', 'p.id')
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    /**
     * Add an audit log entry.
     */
    public function addAuditLog(array $data): int
    {
        return DB::table(self::TABLE_AUDIT)->insertGetId([
            'plugin_id' => $data['plugin_id'],
            'user_id' => $data['user_id'] ?? null,
            'action' => $data['action'],
            'previous_state' => isset($data['previous_state']) ? json_encode($data['previous_state']) : null,
            'new_state' => isset($data['new_state']) ? json_encode($data['new_state']) : null,
            'reason' => $data['reason'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get audit log entries.
     */
    public function getAuditLog(?int $pluginId = null, int $limit = 50): array
    {
        $query = DB::table(self::TABLE_AUDIT . ' as a')
            ->join(self::TABLE_PLUGIN . ' as p', 'p.id', '=', 'a.plugin_id')
            ->select('a.*', 'p.name as plugin_name');

        if (null !== $pluginId) {
            $query->where('a.plugin_id', $pluginId);
        }

        return $query->orderBy('a.created_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->toArray();
    }

    /**
     * Get all plugin names.
     */
    public function getAllPluginNames(): array
    {
        return DB::table(self::TABLE_PLUGIN)
            ->pluck('name')
            ->toArray();
    }
}
