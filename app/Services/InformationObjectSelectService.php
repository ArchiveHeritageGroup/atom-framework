<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service for building searchable record select options.
 * Format: Title (identifier) with level as metadata
 */
class InformationObjectSelectService
{
    /**
     * Get all information objects formatted for select dropdown.
     *
     * @param string|null $culture
     * @param int|null $repositoryId Filter by repository
     * @param bool $publishedOnly Only show published records
     * @return Collection
     */
    public function getSelectOptions(
        ?string $culture = 'en',
        ?int $repositoryId = null,
        bool $publishedOnly = false
    ): Collection {
        $culture = $culture ?? 'en';

        $query = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as io_i18n', function ($join) use ($culture) {
                $join->on('io.id', '=', 'io_i18n.id')
                    ->where('io_i18n.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as level_i18n', function ($join) use ($culture) {
                $join->on('io.level_of_description_id', '=', 'level_i18n.id')
                    ->where('level_i18n.culture', '=', $culture);
            })
            ->select([
                'io.id',
                'io.identifier',
                'io.source_culture',
                'io_i18n.title',
                'level_i18n.name as level_of_description',
            ])
            ->whereNotNull('io.id')
            ->where('io.parent_id', '!=', DB::raw('io.id'))
            ->orderByRaw('COALESCE(io_i18n.title, io.identifier) ASC');

        if ($repositoryId) {
            $query->where('io.repository_id', '=', $repositoryId);
        }

        return $query->get()->map(function ($record) {
            return [
                'id' => $record->id,
                'identifier' => $record->identifier ?? '',
                'title' => $record->title ?? $record->identifier ?? '[Untitled]',
                'level' => $record->level_of_description ?? '',
            ];
        });
    }

    /**
     * Search records by title or identifier (for AJAX).
     *
     * @param string $search
     * @param string|null $culture
     * @param int $limit
     * @return Collection
     */
    public function searchRecords(
        string $search,
        ?string $culture = 'en',
        int $limit = 50
    ): Collection {
        $culture = $culture ?? 'en';
        $searchTerm = '%' . $search . '%';

        return DB::table('information_object as io')
            ->leftJoin('information_object_i18n as io_i18n', function ($join) use ($culture) {
                $join->on('io.id', '=', 'io_i18n.id')
                    ->where('io_i18n.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as level_i18n', function ($join) use ($culture) {
                $join->on('io.level_of_description_id', '=', 'level_i18n.id')
                    ->where('level_i18n.culture', '=', $culture);
            })
            ->select([
                'io.id',
                'io.identifier',
                'io_i18n.title',
                'level_i18n.name as level_of_description',
            ])
            ->where(function ($q) use ($searchTerm) {
                $q->where('io_i18n.title', 'LIKE', $searchTerm)
                    ->orWhere('io.identifier', 'LIKE', $searchTerm);
            })
            ->orderByRaw('COALESCE(io_i18n.title, io.identifier) ASC')
            ->limit($limit)
            ->get()
            ->map(function ($record) {
                return [
                    'id' => $record->id,
                    'identifier' => $record->identifier ?? '',
                    'title' => $record->title ?? $record->identifier ?? '[Untitled]',
                    'level' => $record->level_of_description ?? '',
                ];
            });
    }

    /**
     * Get child records for a parent (for hierarchical selection).
     *
     * @param int $parentId
     * @param string|null $culture
     * @return Collection
     */
    public function getChildRecords(int $parentId, ?string $culture = 'en'): Collection
    {
        $culture = $culture ?? 'en';

        return DB::table('information_object as io')
            ->leftJoin('information_object_i18n as io_i18n', function ($join) use ($culture) {
                $join->on('io.id', '=', 'io_i18n.id')
                    ->where('io_i18n.culture', '=', $culture);
            })
            ->leftJoin('term_i18n as level_i18n', function ($join) use ($culture) {
                $join->on('io.level_of_description_id', '=', 'level_i18n.id')
                    ->where('level_i18n.culture', '=', $culture);
            })
            ->select([
                'io.id',
                'io.identifier',
                'io_i18n.title',
                'level_i18n.name as level_of_description',
            ])
            ->where('io.parent_id', '=', $parentId)
            ->orderBy('io.lft')
            ->get()
            ->map(function ($record) {
                return [
                    'id' => $record->id,
                    'identifier' => $record->identifier ?? '',
                    'title' => $record->title ?? $record->identifier ?? '[Untitled]',
                    'level' => $record->level_of_description ?? '',
                ];
            });
    }
}
