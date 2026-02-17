<?php

declare(strict_types=1);

namespace AtomExtensions\Reports\Services;

use AtomExtensions\Helpers\CultureHelper;

use AtomExtensions\Reports\Filters\ReportFilter;
use AtomExtensions\Repositories\PhysicalObjectRepository;
use AtomExtensions\Services\TermService;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;
use Psr\Log\LoggerInterface;

/**
 * Physical Object Report Service - Standard AtoM fields only.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
final class PhysicalObjectReportService
{
    private const DEFAULT_FILTER = [
        'className' => 'QubitPhysicalObject',
        'dateStart' => null,
        'dateEnd' => null,
        'dateOf' => 'CREATED_AT',
        'culture' => 'en',
        'repositoryId' => null,
        'showLinkedIO' => false,
        'limit' => 10,
        'page' => 1,
    ];

    public function __construct(
        private readonly PhysicalObjectRepository $repository,
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

        // If showLinkedIO is true, get linked information objects
        if ($filter->get('showLinkedIO')) {
            $results = $this->attachLinkedInformationObjects($results, $filter->get('culture', CultureHelper::getCulture()));
        }

        return [
            'results' => collect($results)->map(fn ($item) => (object) $item),
            'total' => $total,
            'limit' => $limit,
            'page' => $page,
        ];
    }

    private function buildQuery(ReportFilter $filter): Builder
    {
        $culture = $filter->get('culture', CultureHelper::getCulture());

        $query = DB::table('physical_object as po')
            ->join('object as o', 'po.id', '=', 'o.id')
            ->leftJoin('physical_object_i18n as po_i18n', function ($join) use ($culture) {
                $join->on('po.id', '=', 'po_i18n.id')
                     ->where('po_i18n.culture', $culture);
            })
            ->where('o.class_name', 'QubitPhysicalObject')
            ->select(
                'po.id',
                'po.type_id as typeId',
                'po_i18n.name',
                'po_i18n.location',
                'po_i18n.culture',
                
                'o.created_at as createdAt',
                'o.updated_at as updatedAt'
            );

        // Filter by repository if specified
        if ($filter->has('repositoryId') && $filter->get('repositoryId')) {
            // Repository filter via information_object relation
            $query->whereExists(function($sub) use ($filter) {
                $sub->select(DB::raw(1))
                    ->from('relation as rel')
                    ->join('information_object as io', 'rel.subject_id', '=', 'io.id')
                    ->whereColumn('rel.object_id', 'po.id')
                    ->where('io.repository_id', $filter->get('repositoryId'));
            });
        }

        $this->applyDateFilter($query, $filter);

        $dateOf = $filter->get('dateOf', 'CREATED_AT');
        if ($dateOf === 'UPDATED_AT') {
            $query->orderBy('o.updated_at', 'desc');
        } else {
            $query->orderBy('o.created_at', 'desc');
        }

        return $query;
    }

    private function attachLinkedInformationObjects($results, string $culture)
    {
        foreach ($results as $physicalObject) {
            // Get linked information objects
            $linkedIOs = DB::table('relation')
                ->join('information_object as io', 'relation.object_id', '=', 'io.id')
                ->join('information_object_i18n as io_i18n', function ($join) use ($culture) {
                    $join->on('io.id', '=', 'io_i18n.id')
                         ->where('io_i18n.culture', $culture);
                })
                ->where('relation.subject_id', $physicalObject->id)
                ->where('relation.type_id', 67) // QubitTerm::PHYSICAL_OBJECT_ID
                ->select('io.id', 'io_i18n.title', 'io.identifier')
                ->get();

            $physicalObject->linkedInformationObjects = $linkedIOs;
        }

        return $results;
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
        $total = DB::table('physical_object as po')
            ->join('object as o', 'po.id', '=', 'o.id')
            ->where('o.class_name', 'QubitPhysicalObject')
            ->count();

        // Get repository stats via relation -> information_object -> repository
        $byRepository = DB::table('physical_object as po')
            ->join('object as o', 'po.id', '=', 'o.id')
            ->join('relation as rel', 'po.id', '=', 'rel.object_id')
            ->join('information_object as io', 'rel.subject_id', '=', 'io.id')
            ->join('repository as r', 'io.repository_id', '=', 'r.id')
            ->join('actor_i18n as ai', function ($join) {
                $join->on('r.id', '=', 'ai.id')
                     ->where('ai.culture', CultureHelper::getCulture());
            })
            ->where('o.class_name', 'QubitPhysicalObject')
            ->whereNotNull('io.repository_id')
            ->select('io.repository_id', 'ai.authorized_form_of_name as repository_name', DB::raw('count(DISTINCT po.id) as count'))
            ->groupBy('io.repository_id', 'ai.authorized_form_of_name')
            ->get();

        return [
            'total' => $total,
            'by_repository' => $byRepository->mapWithKeys(function ($item) {
                return [$item->repository_name => $item->count];
            })->all(),
        ];
    }
}
