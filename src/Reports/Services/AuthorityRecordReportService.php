<?php

declare(strict_types=1);

namespace AtomExtensions\Reports\Services;

use AtomExtensions\Helpers\CultureHelper;

use AtomExtensions\Reports\Filters\ReportFilter;
use AtomExtensions\Reports\Results\AuthorityRecordReportResult;
use AtomExtensions\Repositories\ActorRepository;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;
use Psr\Log\LoggerInterface;

/**
 * Authority Record Report Service - Migrated to Laravel Query Builder.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
final class AuthorityRecordReportService
{
    private const DEFAULT_FILTER = [
        'className' => 'QubitActor',
        'dateStart' => null,
        'dateEnd' => null,
        'dateOf' => 'CREATED_AT',
        'publicationStatus' => 'all',
        'limit' => 10,
        'sort' => 'updatedDown',
        'page' => 1,
        'entityType' => null,
    ];

    public function __construct(
        private readonly ActorRepository $repository,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Search for authority records using modern query builder.
     */
    public function search(ReportFilter $filter): AuthorityRecordReportResult
    {
        $filter = $filter->withDefaults(self::DEFAULT_FILTER);

        $query = $this->buildQuery($filter);

        // Apply sorting
        $this->applySorting($query, $filter->get('sort', 'updatedDown'));

        // Get total count before pagination
        $total = $query->count();

        // Apply pagination
        $limit = (int) $filter->get('limit', 10);
        $page = (int) $filter->get('page', 1);
        $offset = ($page - 1) * $limit;

        $results = $query->offset($offset)->limit($limit)->get();

        $this->logger->debug('Authority record report generated', [
            'dateOf' => $filter->get('dateOf'),
            'sort' => $filter->get('sort'),
            'limit' => $limit,
            'page' => $page,
            'total' => $total,
        ]);

        return new AuthorityRecordReportResult(
            collect($results)->map(fn ($item) => (array) $item),
            $total,
            $limit,
            $page,
            $filter->get('sort'),
            $filter->get('dateOf'),
            $filter->get('dateStart'),
            $filter->get('dateEnd')
        );
    }

    /**
     * Build the main query using Laravel Query Builder.
     */
    private function buildQuery(ReportFilter $filter): Builder
    {
        $query = DB::table('actor as a')
            ->join('object as o', 'a.id', '=', 'o.id')
            ->leftJoin('actor_i18n as i18n', function ($join) {
                $join->on('a.id', '=', 'i18n.id')
                     ->where('i18n.culture', CultureHelper::getCulture());
            })
            ->leftJoin('term_i18n as entity_type', function ($join) {
                $join->on('a.entity_type_id', '=', 'entity_type.id')
                     ->where('entity_type.culture', CultureHelper::getCulture());
            })
            ->leftJoin('slug', 'a.id', '=', 'slug.object_id')
            ->whereNotNull('a.parent_id')
            ->where('o.class_name', 'QubitActor')
            ->select(
                'a.id',
                'a.entity_type_id',
                'slug.slug',
                'i18n.authorized_form_of_name',
                'i18n.dates_of_existence',
                'entity_type.name as entity_type_name',
                'o.created_at',
                'o.updated_at'
            );

        // Apply entity type filter
        if ($filter->has('entityType') && $filter->get('entityType') !== '') {
            $query->where('a.entity_type_id', $filter->get('entityType'));
        }

        // Apply date filtering
        $this->applyDateFilter($query, $filter);

        return $query;
    }

    /**
     * Apply date range filtering.
     */
    private function applyDateFilter(Builder $query, ReportFilter $filter): void
    {
        $dateOf = $filter->get('dateOf', 'CREATED_AT');
        $dateStart = $this->parseDate($filter->get('dateStart'), true);
        $dateEnd = $this->parseDate($filter->get('dateEnd'), false);

        if (!$dateStart && !$dateEnd) {
            return;
        }

        switch ($dateOf) {
            case 'CREATED_AT':
                if ($dateStart) {
                    $query->where('o.created_at', '>=', $dateStart);
                }
                if ($dateEnd) {
                    $query->where('o.created_at', '<=', $dateEnd);
                }
                break;

            case 'UPDATED_AT':
                if ($dateStart) {
                    $query->where('o.updated_at', '>=', $dateStart);
                }
                if ($dateEnd) {
                    $query->where('o.updated_at', '<=', $dateEnd);
                }
                break;

            case 'both':
            default:
                $query->where(function ($q) use ($dateStart, $dateEnd) {
                    if ($dateStart && $dateEnd) {
                        $q->whereBetween('o.created_at', [$dateStart, $dateEnd])
                          ->orWhereBetween('o.updated_at', [$dateStart, $dateEnd]);
                    } elseif ($dateStart) {
                        $q->where('o.created_at', '>=', $dateStart)
                          ->orWhere('o.updated_at', '>=', $dateStart);
                    } elseif ($dateEnd) {
                        $q->where('o.created_at', '<=', $dateEnd)
                          ->orWhere('o.updated_at', '<=', $dateEnd);
                    }
                });
                break;
        }
    }

    /**
     * Parse date from various formats to MySQL datetime.
     */
    private function parseDate(?string $date, bool $startOfDay = true): ?string
    {
        if (!$date || $date === '') {
            return null;
        }

        // Try d/m/Y format first
        if (strpos($date, '/') !== false) {
            $parts = explode('/', $date);
            if (count($parts) === 3) {
                $day = (int) $parts[0];
                $month = (int) $parts[1];
                $year = (int) $parts[2];

                if (checkdate($month, $day, $year)) {
                    $time = $startOfDay ? '00:00:00' : '23:59:59';
                    return sprintf('%04d-%02d-%02d %s', $year, $month, $day, $time);
                }
            }
        }

        // Try Y-m-d format
        if (strpos($date, '-') !== false) {
            $time = $startOfDay ? '00:00:00' : '23:59:59';
            return date('Y-m-d', strtotime($date)) . ' ' . $time;
        }

        return null;
    }

    /**
     * Apply sorting to query.
     */
    private function applySorting(Builder $query, string $sort): void
    {
        switch ($sort) {
            case 'nameUp':
                $query->orderBy('i18n.authorized_form_of_name', 'asc');
                break;
            case 'nameDown':
                $query->orderBy('i18n.authorized_form_of_name', 'desc');
                break;
            case 'updatedUp':
                $query->orderBy('o.updated_at', 'asc');
                break;
            case 'updatedDown':
            default:
                $query->orderBy('o.updated_at', 'desc');
                break;
        }
    }

    /**
     * Get statistics about authority records.
     */
    public function getStatistics(): array
    {
        $total = DB::table('actor as a')
            ->join('object as o', 'a.id', '=', 'o.id')
            ->whereNotNull('a.parent_id')
            ->where('o.class_name', 'QubitActor')
            ->count();

        $byType = DB::table('actor as a')
            ->join('object as o', 'a.id', '=', 'o.id')
            ->leftJoin('term_i18n as t', function ($join) {
                $join->on('a.entity_type_id', '=', 't.id')
                     ->where('t.culture', CultureHelper::getCulture());
            })
            ->whereNotNull('a.parent_id')
            ->where('o.class_name', 'QubitActor')
            ->whereNotNull('a.entity_type_id')
            ->select('a.entity_type_id', 't.name', DB::raw('count(*) as count'))
            ->groupBy('a.entity_type_id', 't.name')
            ->get();

        return [
            'total' => $total,
            'by_type' => $byType->mapWithKeys(function ($item) {
                return [$item->name ?? 'Unknown' => $item->count];
            })->all(),
        ];
    }
}
