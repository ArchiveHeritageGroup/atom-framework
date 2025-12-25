<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RightsService
{
    /**
     * Apply rights to multiple information objects.
     */
    public function applyBatchRights(array $objectIds, array $rightsData): array
    {
        $success = 0;
        $failed = 0;
        $errors = [];

        foreach ($objectIds as $objectId) {
            try {
                $this->createRight((int) $objectId, $rightsData);
                $success++;
            } catch (\Exception $e) {
                $failed++;
                $errors[] = "ID {$objectId}: " . $e->getMessage();
            }
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'errors' => $errors,
        ];
    }

    /**
     * Create a single right record.
     */
    public function createRight(int $objectId, array $data): int
    {
        // Verify object exists
        $exists = DB::table('information_object')->where('id', $objectId)->exists();
        if (!$exists) {
            throw new \Exception("Information object not found");
        }

        // Create base object record
        $baseId = DB::table('object')->insertGetId([
            'class_name' => 'QubitRights',
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Create rights record
        DB::table('rights')->insert([
            'id' => $baseId,
            'object_id' => $objectId,
            'start_date' => $data['start_date'] ?: null,
            'end_date' => $data['end_date'] ?: null,
            'basis_id' => $data['basis_id'] ?: null,
            'copyright_status_id' => $data['copyright_status_id'] ?: null,
            'rights_holder_id' => $data['rights_holder_id'] ?: null,
            'restriction' => $data['restriction'] ?? 1,
            'source_culture' => $data['culture'] ?? 'en',
        ]);

        // Create i18n record
        DB::table('rights_i18n')->insert([
            'id' => $baseId,
            'culture' => $data['culture'] ?? 'en',
            'rights_note' => $data['rights_note'] ?: null,
            'copyright_note' => $data['copyright_note'] ?: null,
        ]);

        return $baseId;
    }

    /**
     * Get rights basis options.
     */
    public function getRightsBasisOptions(string $culture = 'en'): Collection
    {
        return DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->join('taxonomy', 'term.taxonomy_id', '=', 'taxonomy.id')
            ->where('taxonomy.name', 'LIKE', '%rights basis%')
            ->where('term_i18n.culture', $culture)
            ->whereNotNull('term.parent_id')
            ->select('term.id', 'term_i18n.name')
            ->orderBy('term_i18n.name')
            ->get();
    }

    /**
     * Get copyright status options.
     */
    public function getCopyrightStatusOptions(string $culture = 'en'): Collection
    {
        return DB::table('term')
            ->join('term_i18n', 'term.id', '=', 'term_i18n.id')
            ->join('taxonomy', 'term.taxonomy_id', '=', 'taxonomy.id')
            ->where('taxonomy.name', 'LIKE', '%copyright status%')
            ->where('term_i18n.culture', $culture)
            ->whereNotNull('term.parent_id')
            ->select('term.id', 'term_i18n.name')
            ->orderBy('term_i18n.name')
            ->get();
    }

    /**
     * Get rights holders.
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
     * Get child records of a parent.
     */
    public function getChildIds(int $parentId): array
    {
        return DB::table('information_object')
            ->where('parent_id', $parentId)
            ->pluck('id')
            ->toArray();
    }
}
