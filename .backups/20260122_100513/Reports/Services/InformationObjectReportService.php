<?php

declare(strict_types=1);

namespace AtomExtensions\Reports\Services;

use AtomExtensions\Helpers\CultureHelper;

use AtomExtensions\Reports\Filters\ReportFilter;
use AtomExtensions\Repositories\InformationObjectRepository;
use AtomExtensions\Services\TermService;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;
use Psr\Log\LoggerInterface;

/**
 * Information Object Report Service.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
final class InformationObjectReportService
{
    private const DEFAULT_FILTER = [
        'className' => 'QubitInformationObject',
        'dateStart' => null,
        'dateEnd' => null,
        'dateOf' => 'CREATED_AT',
        'publicationStatus' => null,
        'levelOfDescription' => null,
        'limit' => 10,
        'page' => 1,
    ];

    public function __construct(
        private readonly InformationObjectRepository $repository,
        private readonly TermService $termService,
        private readonly LoggerInterface $logger
    ) {
    }

    public function search(ReportFilter $filter): array
    {
        $filter = $filter->withDefaults(self::DEFAULT_FILTER);

        $query = $this->buildQuery($filter);

        $total = $query->count();

        $limit = (int) $filter->get('limit', 10);
        $page = (int) $filter->get('page', 1);
        $offset = ($page - 1) * $limit;

        $results = $query->offset($offset)->limit($limit)->get();

        $this->logger->debug('Information object report generated', [
            'total' => $total,
            'limit' => $limit,
            'page' => $page,
        ]);

        return [
            'results' => collect($results)->map(fn ($item) => (object) $item),
            'total' => $total,
            'limit' => $limit,
            'page' => $page,
        ];
    }

    private function buildQuery(ReportFilter $filter): Builder
    {
        $culture = $filter->get('culture', 'en');

        $query = DB::table('information_object as i')
            ->join('object as o', 'i.id', '=', 'o.id')
            ->leftJoin('information_object_i18n as i18n', function ($join) use ($culture) {
                $join->on('i.id', '=', 'i18n.id')
                     ->where('i18n.culture', $culture);
            })
            ->where('o.class_name', 'QubitInformationObject')
            ->whereNotNull('i.parent_id')
            ->select(
                'i.id',
                'i.identifier',
                'i.level_of_description_id as levelOfDescriptionId',
                'i.repository_id as repositoryId',
                'i18n.title',
                'i18n.alternate_title as alternateTitle',
                'i18n.extent_and_medium as extentAndMedium',
                'i18n.archival_history as archivalHistory',
                'i18n.acquisition',
                'i18n.scope_and_content as scopeAndContent',
                'i18n.appraisal',
                'i18n.accruals',
                'i18n.arrangement',
                'i18n.access_conditions as accessConditions',
                'i18n.reproduction_conditions as reproductionConditions',
                'i18n.physical_characteristics as physicalCharacteristics',
                'i18n.finding_aids as findingAids',
                'i18n.location_of_originals as locationOfOriginals',
                'i18n.location_of_copies as locationOfCopies',
                'i18n.related_units_of_description as relatedUnitsOfDescription',
                'i18n.institution_responsible_identifier as institutionResponsibleIdentifier',
                'i18n.rules',
                'i18n.sources',
                'i18n.revision_history as revisionHistory',
                'i18n.culture',
                'o.created_at as createdAt',
                'o.updated_at as updatedAt'
            );

        $this->applyFilters($query, $filter);
        $this->applyDateFilter($query, $filter);

        $dateOf = $filter->get('dateOf', 'CREATED_AT');
        if ($dateOf === 'UPDATED_AT') {
            $query->orderBy('o.updated_at', 'desc');
        } else {
            $query->orderBy('o.created_at', 'desc');
        }

        return $query;
    }

    private function applyFilters(Builder $query, ReportFilter $filter): void
    {
        if ($filter->has('levelOfDescription') && $filter->get('levelOfDescription')) {
            $query->where('i.level_of_description_id', $filter->get('levelOfDescription'));
        }

        if ($filter->has('publicationStatus') && $filter->get('publicationStatus')) {
            $query->join('status as s', function ($join) use ($filter) {
                $join->on('i.id', '=', 's.object_id')
                     ->where('s.type_id', 159)
                     ->where('s.status_id', $filter->get('publicationStatus'));
            });
        }
    }

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

    private function parseDate(?string $date, bool $startOfDay = true): ?string
    {
        if (!$date || $date === '') {
            return null;
        }

        // HTML5 date picker format: Y-m-d (2025-11-22)
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $time = $startOfDay ? '00:00:00' : '23:59:59';

            return $date . ' ' . $time;
        }

        // Legacy d/m/Y format support (22/11/2025)
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
        $total = DB::table('information_object as i')
            ->join('object as o', 'i.id', '=', 'o.id')
            ->whereNotNull('i.parent_id')
            ->where('o.class_name', 'QubitInformationObject')
            ->count();

        $byLevel = DB::table('information_object as i')
            ->join('object as o', 'i.id', '=', 'o.id')
            ->join('term_i18n as t', function ($join) {
                $join->on('i.level_of_description_id', '=', 't.id')
                     ->where('t.culture', CultureHelper::getCulture());
            })
            ->whereNotNull('i.parent_id')
            ->where('o.class_name', 'QubitInformationObject')
            ->select('i.level_of_description_id', 't.name', DB::raw('count(*) as count'))
            ->groupBy('i.level_of_description_id', 't.name')
            ->get();

        return [
            'total' => $total,
            'by_level' => $byLevel->mapWithKeys(function ($item) {
                return [$item->name => $item->count];
            })->all(),
        ];
    }
}
