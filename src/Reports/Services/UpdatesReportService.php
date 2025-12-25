<?php

declare(strict_types=1);

namespace AtomExtensions\Reports\Services;

use AtomExtensions\Helpers\CultureHelper;

use AtomExtensions\Reports\Filters\ReportFilter;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;
use Psr\Log\LoggerInterface;

/**
 * Updates Report Service.
 *
 * Shows recent updates across all entity types.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
final class UpdatesReportService
{
    private const DEFAULT_FILTER = [
        'dateStart' => null,
        'dateEnd' => null,
        'dateOf' => 'UPDATED_AT',
        'className' => 'all',
        'limit' => 20,
        'page' => 1,
    ];

    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function search(ReportFilter $filter): array
    {
        $filter = $filter->withDefaults(self::DEFAULT_FILTER);

        $className = $filter->get('className', 'all');

        if ($className === 'all') {
            $results = $this->searchAllTypes($filter);
        } else {
            $results = $this->searchByType($filter, $className);
        }

        return $results;
    }

    private function searchAllTypes(ReportFilter $filter): array
    {
        $limit = (int) $filter->get('limit', 20);
        $page = (int) $filter->get('page', 1);
        $offset = ($page - 1) * $limit;

        // Get updates from all object types
        $query = DB::table('object as o')
            ->select(
                'o.id',
                'o.class_name as className',
                'o.created_at as createdAt',
                'o.updated_at as updatedAt'
            )
            ->whereIn('o.class_name', [
                'QubitInformationObject',
                'QubitActor',
                'QubitRepository',
                'QubitAccession',
                'QubitPhysicalObject',
                'QubitDonor'
            ]);

        $this->applyDateFilter($query, $filter);

        $dateOf = $filter->get('dateOf', 'UPDATED_AT');
        if ($dateOf === 'UPDATED_AT') {
            $query->orderBy('o.updated_at', 'desc');
        } else {
            $query->orderBy('o.created_at', 'desc');
        }

        $total = $query->count();
        $results = $query->offset($offset)->limit($limit)->get();

        // Enrich results with names
        $enrichedResults = $this->enrichResults($results);

        return [
            'results' => collect($enrichedResults)->map(fn ($item) => (object) $item),
            'total' => $total,
            'limit' => $limit,
            'page' => $page,
        ];
    }

    private function searchByType(ReportFilter $filter, string $className): array
    {
        // Similar to searchAllTypes but filtered by className
        $limit = (int) $filter->get('limit', 20);
        $page = (int) $filter->get('page', 1);
        $offset = ($page - 1) * $limit;

        $query = DB::table('object as o')
            ->select(
                'o.id',
                'o.class_name as className',
                'o.created_at as createdAt',
                'o.updated_at as updatedAt'
            )
            ->where('o.class_name', $className);

        $this->applyDateFilter($query, $filter);

        $dateOf = $filter->get('dateOf', 'UPDATED_AT');
        if ($dateOf === 'UPDATED_AT') {
            $query->orderBy('o.updated_at', 'desc');
        } else {
            $query->orderBy('o.created_at', 'desc');
        }

        $total = $query->count();
        $results = $query->offset($offset)->limit($limit)->get();

        $enrichedResults = $this->enrichResults($results);

        return [
            'results' => collect($enrichedResults)->map(fn ($item) => (object) $item),
            'total' => $total,
            'limit' => $limit,
            'page' => $page,
        ];
    }

    private function enrichResults($results): array
    {
        $enriched = [];

        foreach ($results as $result) {
            $item = (array) $result;
            $item['name'] = $this->getEntityName($result->id, $result->className);
            $item['identifier'] = $this->getEntityIdentifier($result->id, $result->className);
            $enriched[] = $item;
        }

        return $enriched;
    }

    private function getEntityName(int $id, string $className): string
    {
        switch ($className) {
            case 'QubitInformationObject':
                $result = DB::table('information_object_i18n')
                    ->where('id', $id)
                    ->where('culture', CultureHelper::getCulture())
                    ->value('title');

                return $result ?? '-';

            case 'QubitActor':
            case 'QubitRepository':
            case 'QubitDonor':
                $result = DB::table('actor_i18n')
                    ->where('id', $id)
                    ->where('culture', CultureHelper::getCulture())
                    ->value('authorized_form_of_name');

                return $result ?? '-';

            case 'QubitAccession':
                $result = DB::table('accession_i18n')
                    ->where('id', $id)
                    ->where('culture', CultureHelper::getCulture())
                    ->value('title');

                return $result ?? '-';

            case 'QubitPhysicalObject':
                $result = DB::table('physical_object_i18n')
                    ->where('id', $id)
                    ->where('culture', CultureHelper::getCulture())
                    ->value('name');

                return $result ?? '-';

            default:
                return '-';
        }
    }

    private function getEntityIdentifier(int $id, string $className): ?string
    {
        switch ($className) {
            case 'QubitInformationObject':
                return DB::table('information_object')->where('id', $id)->value('identifier');

            case 'QubitRepository':
                return DB::table('repository')->where('id', $id)->value('identifier');

            case 'QubitAccession':
                return DB::table('accession')->where('id', $id)->value('identifier');

            default:
                return null;
        }
    }

    private function applyDateFilter(Builder $query, ReportFilter $filter): void
    {
        $dateOf = $filter->get('dateOf', 'UPDATED_AT');
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

    private function parseDate(?string $date, bool $startOfDay = true): ?string
    {
        if (!$date || $date === '') {
            return null;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $time = $startOfDay ? '00:00:00' : '23:59:59';

            return $date . ' ' . $time;
        }

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

        return null;
    }

    public function getStatistics(): array
    {
        return [
            'total' => DB::table('object')->count(),
        ];
    }
}
