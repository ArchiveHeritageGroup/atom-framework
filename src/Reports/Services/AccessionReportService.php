<?php

declare(strict_types=1);

namespace AtomExtensions\Reports\Services;

use AtomExtensions\Helpers\CultureHelper;

use AtomExtensions\Reports\Filters\ReportFilter;
use AtomExtensions\Repositories\AccessionRepository;
use AtomExtensions\Services\TermService;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;
use Psr\Log\LoggerInterface;

/**
 * Accession Report Service - Standard AtoM accession fields.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
final class AccessionReportService
{
    private const DEFAULT_FILTER = [
        'className' => 'QubitAccession',
        'dateStart' => null,
        'dateEnd' => null,
        'dateOf' => 'CREATED_AT',
        'culture' => 'en',
        'limit' => 10,
        'page' => 1,
    ];

    public function __construct(
        private readonly AccessionRepository $repository,
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

        $query = DB::table('accession as acc')
            ->leftJoin('accession_i18n as i18n', function ($join) use ($culture) {
                $join->on('acc.id', '=', 'i18n.id')
                     ->where('i18n.culture', $culture);
            })
            ->select(
                'acc.id',
                'acc.identifier',
                'acc.date as accessionDate',
                'acc.acquisition_type_id as acquisitionTypeId',
                'acc.resource_type_id as resourceTypeId',
                'acc.processing_status_id as processingStatusId',
                'acc.processing_priority_id as processingPriorityId',
                'i18n.title',
                'i18n.scope_and_content as scopeAndContent',
                'i18n.archival_history as archivalHistory',
                'i18n.physical_characteristics as physicalCharacteristics',
                'i18n.received_extent_units as receivedExtentUnits',
                'i18n.culture',
                'acc.created_at as createdAt',
                'acc.updated_at as updatedAt'
            );

        $this->applyDateFilter($query, $filter);

        $dateOf = $filter->get('dateOf', 'CREATED_AT');
        if ($dateOf === 'UPDATED_AT') {
            $query->orderBy('acc.updated_at', 'desc');
        } else {
            $query->orderBy('acc.created_at', 'desc');
        }

        return $query;
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
                    $query->where('acc.created_at', '>=', $dateStart);
                }
                if ($dateEnd) {
                    $query->where('acc.created_at', '<=', $dateEnd);
                }
                break;

            case 'UPDATED_AT':
                if ($dateStart) {
                    $query->where('acc.updated_at', '>=', $dateStart);
                }
                if ($dateEnd) {
                    $query->where('acc.updated_at', '<=', $dateEnd);
                }
                break;

            case 'both':
            default:
                $query->where(function ($q) use ($dateStart, $dateEnd) {
                    if ($dateStart && $dateEnd) {
                        $q->whereBetween('acc.created_at', [$dateStart, $dateEnd])
                          ->orWhereBetween('acc.updated_at', [$dateStart, $dateEnd]);
                    } elseif ($dateStart) {
                        $q->where('acc.created_at', '>=', $dateStart)
                          ->orWhere('acc.updated_at', '>=', $dateStart);
                    } elseif ($dateEnd) {
                        $q->where('acc.created_at', '<=', $dateEnd)
                          ->orWhere('acc.updated_at', '<=', $dateEnd);
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
        $total = DB::table('accession')->count();

        return [
            'total' => $total,
        ];
    }
}
