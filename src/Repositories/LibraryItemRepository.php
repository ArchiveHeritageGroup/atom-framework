<?php

namespace AtomFramework\Repositories;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Repository for Library Item related data (subjects, creators).
 */
class LibraryItemRepository
{
    /**
     * Get creators for a library item.
     */
    public function getCreators(int $libraryItemId): array
    {
        return DB::table('library_item_creator')
            ->where('library_item_id', $libraryItemId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'name' => $row->name,
                    'role' => $row->role,
                    'authority_uri' => $row->authority_uri ?? null,
                ];
            })
            ->toArray();
    }

    /**
     * Save creators for a library item.
     */
    public function saveCreators(int $libraryItemId, array $creators): void
    {
        // Delete existing creators
        DB::table('library_item_creator')
            ->where('library_item_id', $libraryItemId)
            ->delete();

        // Insert new creators
        foreach ($creators as $index => $creator) {
            if (empty($creator['name'])) {
                continue;
            }

            DB::table('library_item_creator')->insert([
                'library_item_id' => $libraryItemId,
                'name' => trim($creator['name']),
                'role' => $creator['role'] ?? 'author',
                'sort_order' => $index,
                'authority_uri' => $creator['authority_uri'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Get subjects for a library item.
     */
    public function getSubjects(int $libraryItemId): array
    {
        return DB::table('library_item_subject')
            ->where('library_item_id', $libraryItemId)
            ->orderBy('id')
            ->get()
            ->map(function ($row) {
                return [
                    'id' => $row->id,
                    'heading' => $row->heading,
                    'subject_type' => $row->subject_type ?? 'topic',
                    'source' => $row->source ?? null,
                    'uri' => $row->uri ?? null,
                ];
            })
            ->toArray();
    }

    /**
     * Save subjects for a library item.
     */
    public function saveSubjects(int $libraryItemId, array $subjects): void
    {
        // Delete existing subjects
        DB::table('library_item_subject')
            ->where('library_item_id', $libraryItemId)
            ->delete();

        // Insert new subjects
        foreach ($subjects as $subject) {
            $heading = is_array($subject) ? ($subject['heading'] ?? '') : $subject;

            if (empty($heading)) {
                continue;
            }

            DB::table('library_item_subject')->insert([
                'library_item_id' => $libraryItemId,
                'heading' => trim($heading),
                'subject_type' => is_array($subject) ? ($subject['subject_type'] ?? 'topic') : 'topic',
                'source' => is_array($subject) ? ($subject['source'] ?? null) : null,
                'uri' => is_array($subject) ? ($subject['uri'] ?? null) : null,
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    /**
     * Get library item extended data by information_object_id.
     */
    public function getLibraryData(int $informationObjectId): ?array
    {
        $row = DB::table('library_item')
            ->where('information_object_id', $informationObjectId)
            ->first();

        if (!$row) {
            return null;
        }

        return (array) $row;
    }

    /**
     * Get library item ID by information object ID.
     */
    public function getLibraryItemId(int $informationObjectId): ?int
    {
        $row = DB::table('library_item')
            ->where('information_object_id', $informationObjectId)
            ->first(['id']);

        return $row ? (int) $row->id : null;
    }

    /**
     * Search subjects for autocomplete.
     */
    public function searchSubjects(string $query, int $limit = 10): array
    {
        return DB::table('library_item_subject')
            ->select('heading')
            ->where('heading', 'LIKE', $query . '%')
            ->groupBy('heading')
            ->orderByRaw('COUNT(*) DESC')
            ->limit($limit)
            ->pluck('heading')
            ->toArray();
    }

    /**
     * Search creators for autocomplete.
     */
    public function searchCreators(string $query, int $limit = 10): array
    {
        return DB::table('library_item_creator')
            ->select('name')
            ->where('name', 'LIKE', $query . '%')
            ->groupBy('name')
            ->orderByRaw('COUNT(*) DESC')
            ->limit($limit)
            ->pluck('name')
            ->toArray();
    }
}
