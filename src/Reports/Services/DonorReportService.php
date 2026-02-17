<?php

declare(strict_types=1);

namespace AtomExtensions\Reports\Services;

use AtomExtensions\Helpers\CultureHelper;

use AtomExtensions\Reports\Filters\ReportFilter;
use AtomExtensions\Repositories\DonorRepository;
use AtomExtensions\Services\TermService;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;
use Psr\Log\LoggerInterface;

/**
 * Donor Report Service - Standard AtoM fields only.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
final class DonorReportService
{
    private const DEFAULT_FILTER = [
        'className' => 'QubitDonor',
        'dateStart' => null,
        'dateEnd' => null,
        'dateOf' => 'CREATED_AT',
        'culture' => 'en',
        'limit' => 10,
        'page' => 1,
    ];

    public function __construct(
        private readonly DonorRepository $repository,
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
        $culture = $filter->get('culture', CultureHelper::getCulture());

        $query = DB::table('donor as d')
            ->join('actor as a', 'd.id', '=', 'a.id')
            ->join('object as o', 'a.id', '=', 'o.id')
            ->leftJoin('actor_i18n as i18n', function ($join) use ($culture) {
                $join->on('a.id', '=', 'i18n.id')
                     ->where('i18n.culture', $culture);
            })
            ->leftJoin('contact_information as ci', function ($join) {
                $join->on('a.id', '=', 'ci.actor_id')
                     ->where('ci.primary_contact', 1);
            })
            ->leftJoin('contact_information_i18n as ci_i18n', function ($join) use ($culture) {
                $join->on('ci.id', '=', 'ci_i18n.id')
                     ->where('ci_i18n.culture', $culture);
            })
            ->where('o.class_name', 'QubitDonor')
            // Removed parent_id filter - include all donors
            ->select(
                'd.id',
                'i18n.authorized_form_of_name as authorizedFormOfName',
                'ci.contact_person as contactPerson',
                'ci.street_address as streetAddress',
                'ci_i18n.city',
                'ci_i18n.region',
                'ci.country_code as countryCode',
                'ci.postal_code as postalCode',
                'ci.telephone',
                'ci.email',
                'ci.website',
                'i18n.culture',
                'o.created_at as createdAt',
                'o.updated_at as updatedAt'
            );

        $this->applyDateFilter($query, $filter);

        $dateOf = $filter->get('dateOf', 'CREATED_AT');
        if ($dateOf === 'UPDATED_AT') {
            $query->orderBy('o.updated_at', 'desc');
        } else {
            $query->orderBy('o.created_at', 'desc');
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
        $total = DB::table('donor as d')
            ->join('actor as a', 'd.id', '=', 'a.id')
            ->join('object as o', 'a.id', '=', 'o.id')
            ->where('o.class_name', 'QubitDonor')
            ->count();

        return [
            'total' => $total,
        ];
    }
}
