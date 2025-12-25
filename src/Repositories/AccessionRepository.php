<?php

declare(strict_types=1);

namespace AtomExtensions\Repositories;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Accession Repository.
 *
 * Provides access to accession records.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class AccessionRepository
{
    /**
     * Get available cultures from database.
     */
    public function getAvailableCultures(): Collection
    {
        return DB::table('accession_i18n')
            ->select('culture')
            ->distinct()
            ->orderBy('culture')
            ->get()
            ->pluck('culture');
    }

    /**
     * Get available repositories.
     */
    public function getAvailableRepositories(string $culture = 'en'): Collection
    {
        return DB::table('repository as r')
            ->join('actor as a', 'r.id', '=', 'a.id')
            ->join('object as o', 'a.id', '=', 'o.id')
            ->leftJoin('actor_i18n as i18n', function ($join) use ($culture) {
                $join->on('a.id', '=', 'i18n.id')
                     ->where('i18n.culture', $culture);
            })
            ->where('o.class_name', 'QubitRepository')
            ->whereNotNull('a.parent_id')
            ->select('r.id', 'i18n.authorized_form_of_name as name')
            ->orderBy('i18n.authorized_form_of_name')
            ->get();
    }

    /**
     * Count total accessions.
     */
    public function count(): int
    {
        return DB::table('accession')
            ->count();
    }
}
