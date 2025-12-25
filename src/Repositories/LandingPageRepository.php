<?php

namespace AtomExtensions\Repositories;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Landing Page Repository
 * 
 * Handles database operations for landing pages and blocks
 */
class LandingPageRepository
{
    protected string $pageTable = 'atom_landing_page';
    protected string $blockTable = 'atom_landing_block';
    protected string $blockTypeTable = 'atom_landing_block_type';
    protected string $versionTable = 'atom_landing_page_version';
    protected string $auditTable = 'atom_landing_page_audit';

    // =========================================================================
    // LANDING PAGES
    // =========================================================================

    /**
     * Get all landing pages
     */
    public function getAllPages(bool $activeOnly = false): Collection
    {
        $query = DB::table($this->pageTable)
            ->select([
                'atom_landing_page.*',
                DB::raw('(SELECT COUNT(*) FROM atom_landing_block WHERE page_id = atom_landing_page.id) as block_count')
            ])
            ->orderBy('is_default', 'desc')
            ->orderBy('name', 'asc');

        if ($activeOnly) {
            $query->where('is_active', 1);
        }

        return $query->get();
    }

    /**
     * Get page by ID
     */
    public function getPageById(int $id): ?object
    {
        return DB::table($this->pageTable)
            ->where('id', $id)
            ->first();
    }

    /**
     * Get page by slug
     */
    public function getPageBySlug(string $slug): ?object
    {
        return DB::table($this->pageTable)
            ->where('slug', $slug)
            ->where('is_active', 1)
            ->first();
    }

    /**
     * Get default landing page
     */
    public function getDefaultPage(): ?object
    {
        return DB::table($this->pageTable)
            ->where('is_default', 1)
            ->where('is_active', 1)
            ->first();
    }

    /**
     * Create new landing page
     */
    public function createPage(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        
        // If setting as default, clear other defaults
        if (!empty($data['is_default'])) {
            DB::table($this->pageTable)->update(['is_default' => 0]);
        }

        return DB::table($this->pageTable)->insertGetId([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
            'is_default' => $data['is_default'] ?? 0,
            'is_active' => $data['is_active'] ?? 1,
            'culture' => $data['culture'] ?? 'en',
            'created_by' => $data['user_id'] ?? null,
            'updated_by' => $data['user_id'] ?? null,
            'created_at' => $now,
            'updated_at' => $now
        ]);
    }

    /**
     * Update landing page
     */
    public function updatePage(int $id, array $data): bool
    {
        // If setting as default, clear other defaults
        if (!empty($data['is_default'])) {
            DB::table($this->pageTable)
                ->where('id', '!=', $id)
                ->update(['is_default' => 0]);
        }

        $updateData = [
            'updated_at' => date('Y-m-d H:i:s'),
            'updated_by' => $data['user_id'] ?? null
        ];

        foreach (['name', 'slug', 'description', 'is_default', 'is_active', 'culture'] as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        return DB::table($this->pageTable)
            ->where('id', $id)
            ->update($updateData) > 0;
    }

    /**
     * Delete landing page
     */
    public function deletePage(int $id): bool
    {
        // Check if it's the default page
        $page = $this->getPageById($id);
        if ($page && $page->is_default) {
            return false; // Cannot delete default page
        }

        return DB::table($this->pageTable)
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Check if slug exists
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $query = DB::table($this->pageTable)->where('slug', $slug);
        
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    // =========================================================================
    // BLOCK TYPES
    // =========================================================================

    /**
     * Get all block types
     */
    public function getAllBlockTypes(bool $activeOnly = true): Collection
    {
        $query = DB::table($this->blockTypeTable)
            ->orderBy('sort_order', 'asc')
            ->orderBy('label', 'asc');

        if ($activeOnly) {
            $query->where('is_active', 1);
        }

        return $query->get()->map(function ($type) {
            $type->default_config = json_decode($type->default_config, true) ?? [];
            $type->config_schema = json_decode($type->config_schema, true) ?? [];
            return $type;
        });
    }

    /**
     * Get block type by ID
     */
    public function getBlockTypeById(int $id): ?object
    {
        $type = DB::table($this->blockTypeTable)
            ->where('id', $id)
            ->first();

        if ($type) {
            $type->default_config = json_decode($type->default_config, true) ?? [];
            $type->config_schema = json_decode($type->config_schema, true) ?? [];
        }

        return $type;
    }

    /**
     * Get block type by machine name
     */
    public function getBlockTypeByName(string $machineName): ?object
    {
        $type = DB::table($this->blockTypeTable)
            ->where('machine_name', $machineName)
            ->first();

        if ($type) {
            $type->default_config = json_decode($type->default_config, true) ?? [];
            $type->config_schema = json_decode($type->config_schema, true) ?? [];
        }

        return $type;
    }

    // =========================================================================
    // BLOCKS
    // =========================================================================

    /**
     * Get all blocks for a page
     */
    public function getPageBlocks(int $pageId, bool $visibleOnly = false): Collection
    {
        $query = DB::table($this->blockTable)
            ->join($this->blockTypeTable, 'atom_landing_block.block_type_id', '=', 'atom_landing_block_type.id')
            ->select([
                'atom_landing_block.*',
                'atom_landing_block_type.machine_name',
                'atom_landing_block_type.label as type_label',
                'atom_landing_block_type.icon as type_icon',
                'atom_landing_block_type.template',
                'atom_landing_block_type.config_schema'
            ])
            ->where('atom_landing_block.page_id', $pageId)
            ->whereNull('atom_landing_block.parent_block_id')
            ->orderBy('atom_landing_block.position', 'asc');

        if ($visibleOnly) {
            $query->where('atom_landing_block.is_visible', 1);
        }

        return $query->get()->map(function ($block) {
            $block->config = json_decode($block->config, true) ?? [];
            $block->config_schema = json_decode($block->config_schema, true) ?? [];
            return $block;
        });
    }

    /**
     * Get block by ID
     */
    public function getBlockById(int $id): ?object
    {
        $block = DB::table($this->blockTable)
            ->join($this->blockTypeTable, 'atom_landing_block.block_type_id', '=', 'atom_landing_block_type.id')
            ->select([
                'atom_landing_block.*',
                'atom_landing_block_type.machine_name',
                'atom_landing_block_type.label as type_label',
                'atom_landing_block_type.icon as type_icon',
                'atom_landing_block_type.template',
                'atom_landing_block_type.config_schema',
                'atom_landing_block_type.default_config'
            ])
            ->where('atom_landing_block.id', $id)
            ->first();

        if ($block) {
            $block->config = json_decode($block->config, true) ?? [];
            $block->config_schema = json_decode($block->config_schema, true) ?? [];
            $block->default_config = json_decode($block->default_config, true) ?? [];
        }

        return $block;
    }

    /**
     * Create new block
     */
    public function createBlock(array $data): int
    {
        // Get max position for page
        $maxPosition = DB::table($this->blockTable)
            ->where('page_id', $data['page_id'])
            ->max('position') ?? -1;

        $now = date('Y-m-d H:i:s');

        return DB::table($this->blockTable)->insertGetId([
            'page_id' => $data['page_id'],
            'block_type_id' => $data['block_type_id'],
            'title' => $data['title'] ?? null,
            'config' => json_encode($data['config'] ?? []),
            'css_classes' => $data['css_classes'] ?? null,
            'container_type' => $data['container_type'] ?? 'container',
            'background_color' => $data['background_color'] ?? null,
            'text_color' => $data['text_color'] ?? null,
            'padding_top' => $data['padding_top'] ?? '3',
            'padding_bottom' => $data['padding_bottom'] ?? '3',
            'position' => $data['position'] ?? ($maxPosition + 1),
            'parent_block_id' => $data['parent_block_id'] ?? null,
            'column_slot' => $data['column_slot'] ?? null,
            'is_visible' => $data['is_visible'] ?? 1,
            'created_at' => $now,
            'updated_at' => $now
        ]);
    }

    /**
     * Update block
     */
    public function updateBlock(int $id, array $data): bool
    {
        $updateData = ['updated_at' => date('Y-m-d H:i:s')];

        $fields = ['title', 'css_classes', 'container_type', 'background_color', 
                   'text_color', 'padding_top', 'padding_bottom', 'position', 'is_visible'];

        foreach ($fields as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        if (array_key_exists('config', $data)) {
            $updateData['config'] = json_encode($data['config']);
        }

        return DB::table($this->blockTable)
            ->where('id', $id)
            ->update($updateData) > 0;
    }

    /**
     * Delete block
     */
    public function deleteBlock(int $id): bool
    {
        $block = $this->getBlockById($id);
        if (!$block) {
            return false;
        }

        $deleted = DB::table($this->blockTable)
            ->where('id', $id)
            ->delete() > 0;

        // Reorder remaining blocks
        if ($deleted) {
            $this->reorderBlocks($block->page_id);
        }

        return $deleted;
    }

    /**
     * Reorder blocks on a page
     */
    public function reorderBlocks(int $pageId, ?array $order = null): bool
    {
        if ($order === null) {
            // Auto-reorder based on current positions
            $blocks = DB::table($this->blockTable)
                ->where('page_id', $pageId)
                ->orderBy('position', 'asc')
                ->pluck('id')
                ->toArray();
            $order = $blocks;
        }

        foreach ($order as $position => $blockId) {
            DB::table($this->blockTable)
                ->where('id', $blockId)
                ->where('page_id', $pageId)
                ->update(['position' => $position]);
        }

        return true;
    }

    /**
     * Duplicate block
     */
    public function duplicateBlock(int $blockId): ?int
    {
        $block = $this->getBlockById($blockId);
        if (!$block) {
            return null;
        }

        return $this->createBlock([
            'page_id' => $block->page_id,
            'block_type_id' => $block->block_type_id,
            'title' => $block->title ? $block->title . ' (Copy)' : null,
            'config' => $block->config,
            'css_classes' => $block->css_classes,
            'container_type' => $block->container_type,
            'background_color' => $block->background_color,
            'text_color' => $block->text_color,
            'padding_top' => $block->padding_top,
            'padding_bottom' => $block->padding_bottom,
            'position' => $block->position + 1,
            'is_visible' => 1
        ]);
    }

    // =========================================================================
    // VERSIONS
    // =========================================================================

    /**
     * Create page version snapshot
     */
    public function createVersion(int $pageId, string $status = 'draft', ?int $userId = null, ?string $notes = null): int
    {
        $blocks = $this->getPageBlocks($pageId);
        
        // Get next version number
        $maxVersion = DB::table($this->versionTable)
            ->where('page_id', $pageId)
            ->max('version_number') ?? 0;

        $versionData = [
            'page_id' => $pageId,
            'version_number' => $maxVersion + 1,
            'blocks_snapshot' => json_encode($blocks->toArray()),
            'status' => $status,
            'created_at' => date('Y-m-d H:i:s'),
            'notes' => $notes
        ];

        if ($status === 'published') {
            $versionData['published_at'] = date('Y-m-d H:i:s');
            $versionData['published_by'] = $userId;
        }

        return DB::table($this->versionTable)->insertGetId($versionData);
    }

    /**
     * Get page versions
     */
    public function getPageVersions(int $pageId, int $limit = 10): Collection
    {
        return DB::table($this->versionTable)
            ->where('page_id', $pageId)
            ->orderBy('version_number', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Restore version
     */
    public function restoreVersion(int $versionId): bool
    {
        $version = DB::table($this->versionTable)
            ->where('id', $versionId)
            ->first();

        if (!$version) {
            return false;
        }

        $blocks = json_decode($version->blocks_snapshot, true);
        if (!$blocks) {
            return false;
        }

        // Delete current blocks
        DB::table($this->blockTable)
            ->where('page_id', $version->page_id)
            ->delete();

        // Recreate from snapshot
        foreach ($blocks as $block) {
            unset($block['id']);
            $block['config'] = json_encode($block['config'] ?? []);
            $block['created_at'] = date('Y-m-d H:i:s');
            $block['updated_at'] = date('Y-m-d H:i:s');
            DB::table($this->blockTable)->insert($block);
        }

        return true;
    }

    // =========================================================================
    // AUDIT
    // =========================================================================

    /**
     * Log audit entry
     */
    public function logAudit(string $action, ?int $pageId = null, ?int $blockId = null, ?array $details = null, ?int $userId = null): void
    {
        DB::table($this->auditTable)->insert([
            'page_id' => $pageId,
            'block_id' => $blockId,
            'action' => $action,
            'details' => $details ? json_encode($details) : null,
            'user_id' => $userId,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ]);
    }

    /**
     * Get audit log
     */
    public function getAuditLog(?int $pageId = null, int $limit = 50): Collection
    {
        $query = DB::table($this->auditTable)
            ->leftJoin('user', 'atom_landing_page_audit.user_id', '=', 'user.id')
            ->select([
                'atom_landing_page_audit.*',
                'user.username'
            ])
            ->orderBy('atom_landing_page_audit.created_at', 'desc')
            ->limit($limit);

        if ($pageId) {
            $query->where('atom_landing_page_audit.page_id', $pageId);
        }

        return $query->get();
    }

    /**
     * Get child blocks for a parent block
     */
    public function getChildBlocks(int $parentBlockId): Collection
    {
        return DB::table($this->blockTable)
            ->join($this->blockTypeTable, 'atom_landing_block.block_type_id', '=', 'atom_landing_block_type.id')
            ->select([
                'atom_landing_block.*',
                'atom_landing_block_type.machine_name',
                'atom_landing_block_type.label as type_label',
                'atom_landing_block_type.icon as type_icon',
                'atom_landing_block_type.template'
            ])
            ->where('atom_landing_block.parent_block_id', $parentBlockId)
            ->orderBy('atom_landing_block.column_slot', 'asc')
            ->orderBy('atom_landing_block.position', 'asc')
            ->get()
            ->map(function ($block) {
                $block->config = json_decode($block->config, true) ?? [];
                return $block;
            });
    }

    /**
     * Move block into a column
     */
    public function moveBlockToColumn(int $blockId, ?int $parentBlockId, ?string $columnSlot): bool
    {
        return DB::table($this->blockTable)
            ->where('id', $blockId)
            ->update([
                'parent_block_id' => $parentBlockId,
                'column_slot' => $columnSlot,
                'updated_at' => date('Y-m-d H:i:s')
            ]) > 0;
    }
}