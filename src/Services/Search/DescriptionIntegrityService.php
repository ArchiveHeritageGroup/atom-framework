<?php

namespace AtomFramework\Services\Search;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Descriptions that base AtoM's search indexer cannot load (#311).
 *
 * arElasticSearchInformationObjectPdo::loadData() fetches a description with
 * INNER JOINs on object, slug and status. A description missing any of those
 * rows loads as nothing, parent_id included, and the run then aborts on:
 *
 *   Couldn't find ancestors, please make sure parent_id values are correct
 *
 * for the whole instance - naming a field that is not wrong. The fix belongs
 * upstream; this finds the records first, so the error is never the way anyone
 * learns about them.
 *
 * Read-only.
 */
class DescriptionIntegrityService
{
    /** Sample size per check; counts are always exact. */
    private const SAMPLE = 20;

    /**
     * @return array<string, array{label: string, count: int, sample: int[]}>
     *               keyed by check, only checks that found something
     */
    public function findProblems(): array
    {
        $root = 1;   // QubitInformationObject::ROOT_ID has no slug or status by design

        $checks = [
            'no_object' => [
                'information_object has no matching object row',
                DB::table('information_object as io')
                    ->leftJoin('object as o', 'o.id', '=', 'io.id')
                    ->whereNull('o.id'),
                'io.id',
            ],
            'no_slug' => [
                'description has no slug row',
                DB::table('information_object as io')
                    ->leftJoin('slug as s', 's.object_id', '=', 'io.id')
                    ->whereNull('s.id')
                    ->where('io.id', '<>', $root),
                'io.id',
            ],
            'no_status' => [
                'description has no status (publication) row',
                DB::table('information_object as io')
                    ->leftJoin('status as st', 'st.object_id', '=', 'io.id')
                    ->whereNull('st.id')
                    ->where('io.id', '<>', $root),
                'io.id',
            ],
            'orphan_object' => [
                'object row says QubitInformationObject but no information_object row exists',
                DB::table('object as o')
                    ->leftJoin('information_object as io', 'io.id', '=', 'o.id')
                    ->where('o.class_name', 'QubitInformationObject')
                    ->whereNull('io.id'),
                'o.id',
            ],
        ];

        $problems = [];
        foreach ($checks as $key => [$label, $query, $idColumn]) {
            $count = (clone $query)->count();
            if ($count > 0) {
                $problems[$key] = [
                    'label' => $label,
                    'count' => $count,
                    'sample' => (clone $query)->orderBy($idColumn)->limit(self::SAMPLE)
                        ->pluck($idColumn)->map(fn ($id) => (int) $id)->all(),
                ];
            }
        }

        return $problems;
    }
}
