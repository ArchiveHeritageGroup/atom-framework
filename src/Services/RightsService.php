<?php

declare(strict_types=1);

namespace AtomFramework\Services;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

class RightsService
{
    /**
     * Get Rights Statements (rightsstatements.org)
     */
    public function getRightsStatements(string $culture = 'en'): Collection
    {
        return DB::table('rights_statement as rs')
            ->join('rights_statement_i18n as rs_i18n', 'rs.id', '=', 'rs_i18n.rights_statement_id')
            ->where('rs_i18n.culture', $culture)
            ->where('rs.is_active', 1)
            ->select('rs.id', 'rs.code', 'rs.uri', 'rs_i18n.name')
            ->orderBy('rs.sort_order')
            ->get();
    }

    /**
     * Get Creative Commons Licenses
     */
    public function getCreativeCommonsLicenses(string $culture = 'en'): Collection
    {
        // Check if i18n table exists, otherwise use main table
        $hasI18n = DB::getSchemaBuilder()->hasTable('creative_commons_license_i18n');
        
        if ($hasI18n) {
            return DB::table('creative_commons_license as cc')
                ->join('creative_commons_license_i18n as cc_i18n', 'cc.id', '=', 'cc_i18n.creative_commons_license_id')
                ->where('cc_i18n.culture', $culture)
                ->where('cc.is_active', 1)
                ->select('cc.id', 'cc.code', 'cc.uri', 'cc_i18n.name')
                ->orderBy('cc.sort_order')
                ->get();
        }
        
        // Fallback: no i18n, construct name from code
        return DB::table('creative_commons_license')
            ->where('is_active', 1)
            ->select('id', 'code', 'uri')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($item) {
                $item->name = 'CC ' . strtoupper($item->code);
                return $item;
            });
    }

    /**
     * Get Traditional Knowledge Labels
     */
    public function getTkLabels(string $culture = 'en'): Collection
    {
        // Check if i18n table exists
        $hasI18n = DB::getSchemaBuilder()->hasTable('tk_label_i18n');
        
        if ($hasI18n) {
            return DB::table('tk_label as tk')
                ->join('tk_label_i18n as tk_i18n', 'tk.id', '=', 'tk_i18n.tk_label_id')
                ->where('tk_i18n.culture', $culture)
                ->where('tk.is_active', 1)
                ->select('tk.id', 'tk.code', 'tk.icon_url', 'tk_i18n.name')
                ->orderBy('tk.sort_order')
                ->get();
        }
        
        // Fallback: no i18n, use code as name
        return DB::table('tk_label')
            ->where('is_active', 1)
            ->select('id', 'code', 'icon_url')
            ->orderBy('sort_order')
            ->get()
            ->map(function ($item) {
                $item->name = $item->code;
                return $item;
            });
    }

    /**
     * Get Rights Holders
     */
    public function getRightsHolders(string $culture = 'en'): Collection
    {
        return DB::table('actor')
            ->join('actor_i18n', 'actor.id', '=', 'actor_i18n.id')
            ->join('object', 'actor.id', '=', 'object.id')
            ->where('object.class_name', 'QubitRightsHolder')
            ->where('actor_i18n.culture', $culture)
            ->select('actor.id', 'actor_i18n.authorized_form_of_name as name')
            ->orderBy('actor_i18n.authorized_form_of_name')
            ->get();
    }

    /**
     * Get information objects for select.
     */
    public function getInformationObjects(string $culture = 'en'): Collection
    {
        return DB::table('information_object as io')
            ->leftJoin('information_object_i18n as io_i18n', function ($join) use ($culture) {
                $join->on('io.id', '=', 'io_i18n.id')
                    ->where('io_i18n.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as level_i18n', function ($join) use ($culture) {
                $join->on('io.level_of_description_id', '=', 'level_i18n.id')
                    ->where('level_i18n.culture', '=', $culture);
            })
            ->whereNotNull('io.parent_id')
            ->select([
                'io.id',
                'io.identifier',
                'io_i18n.title',
                'level_i18n.name as level',
            ])
            ->orderByRaw('COALESCE(io_i18n.title, io.identifier) ASC')
            ->get();
    }

    /**
     * Get direct children of a parent.
     */
    public function getChildIds(int $parentId): array
    {
        return DB::table('information_object')
            ->where('parent_id', $parentId)
            ->pluck('id')
            ->toArray();
    }

    /**
     * Get ALL descendants using nested set.
     */
    public function getAllDescendantIds(int $parentId): array
    {
        $parent = DB::table('information_object')
            ->where('id', $parentId)
            ->select('lft', 'rgt')
            ->first();

        if (!$parent) {
            return [];
        }

        return DB::table('information_object')
            ->where('lft', '>', $parent->lft)
            ->where('rgt', '<', $parent->rgt)
            ->pluck('id')
            ->toArray();
    }

    /**
     * Apply extended rights to objects
     */
    public function applyBatchRights(array $objectIds, array $rightsData, string $action = 'assign'): array
    {
        $success = 0;
        $failed = 0;
        $errors = [];

        foreach ($objectIds as $objectId) {
            try {
                if ($action === 'clear') {
                    $this->clearRights((int) $objectId);
                } else {
                    $this->assignExtendedRights((int) $objectId, $rightsData);
                }
                $success++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "ID {$objectId}: " . $e->getMessage();
            }
        }

        return compact('success', 'failed', 'errors');
    }

    /**
     * Assign extended rights to an object
     */
    protected function assignExtendedRights(int $objectId, array $data): void
    {
        $exists = DB::table('information_object')->where('id', $objectId)->exists();
        if (!$exists) {
            throw new \Exception("Information object not found");
        }

        // Check if extended_rights record exists
        $existing = DB::table('extended_rights')->where('object_id', $objectId)->first();

        $rightsRecord = [
            'rights_statement_id' => $data['rights_statement_id'] ?: null,
            'creative_commons_license_id' => $data['creative_commons_id'] ?: null,
            'rights_holder' => $data['rights_holder'] ?: null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if ($existing) {
            if (!empty($data['overwrite'])) {
                DB::table('extended_rights')
                    ->where('object_id', $objectId)
                    ->update($rightsRecord);
            }
        } else {
            $rightsRecord['object_id'] = $objectId;
            $rightsRecord['created_at'] = date('Y-m-d H:i:s');
            $extendedRightsId = DB::table('extended_rights')->insertGetId($rightsRecord);

            // Add TK Labels if any
            if (!empty($data['tk_label_ids']) && is_array($data['tk_label_ids'])) {
                foreach ($data['tk_label_ids'] as $tkLabelId) {
                    DB::table('extended_rights_tk_label')->insert([
                        'extended_rights_id' => $extendedRightsId,
                        'tk_label_id' => (int) $tkLabelId,
                    ]);
                }
            }
        }
    }

    /**
     * Clear rights from an object
     */
    protected function clearRights(int $objectId): void
    {
        $existing = DB::table('extended_rights')->where('object_id', $objectId)->first();
        if ($existing) {
            DB::table('extended_rights_tk_label')->where('extended_rights_id', $existing->id)->delete();
            DB::table('extended_rights')->where('id', $existing->id)->delete();
        }
    }
}
