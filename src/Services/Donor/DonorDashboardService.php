<?php

declare(strict_types=1);

namespace AtomFramework\Services\Donor;

use AtomFramework\Repositories\Donor\DonorDashboardRepository;

class DonorDashboardService
{
    protected DonorDashboardRepository $repository;

    public function __construct(?DonorDashboardRepository $repository = null)
    {
        $this->repository = $repository ?? new DonorDashboardRepository();
    }

    /**
     * Get complete dashboard data
     */
    public function getDashboardData(?int $repositoryId = null): array
    {
        return [
            'statistics' => $this->repository->getStatistics($repositoryId),
            'recent_donors' => $this->repository->getRecentDonors(5, $repositoryId),
            'recent_agreements' => $this->repository->getRecentAgreements(5, $repositoryId),
            'expiring_agreements' => $this->repository->getExpiringAgreements(30, $repositoryId),
            'pending_reminders' => $this->repository->getPendingReminders(5, $repositoryId),
            'review_due' => $this->repository->getReviewDue(5, $repositoryId),
            'restrictions_releasing' => $this->repository->getRestrictionsReleasingSoon(30, $repositoryId),
            'agreements_by_type' => $this->repository->getAgreementsByType($repositoryId),
            'agreements_by_status' => $this->repository->getAgreementsByStatus($repositoryId),
            'monthly_trends' => $this->repository->getMonthlyTrends(12, $repositoryId),
            'top_donors' => $this->repository->getTopDonors(5, $repositoryId),
            'recent_activity' => $this->repository->getRecentActivity(10, $repositoryId),
        ];
    }

    /**
     * Get statistics only
     */
    public function getStatistics(?int $repositoryId = null): array
    {
        return $this->repository->getStatistics($repositoryId);
    }

    /**
     * Get chart data for agreements by type
     */
    public function getTypeChartData(?int $repositoryId = null): array
    {
        $data = $this->repository->getAgreementsByType($repositoryId);

        $labels = [];
        $values = [];
        $colors = [];

        $defaultColors = [
            '#4e73df', '#1cc88a', '#36b9cc', '#f6c23e', '#e74a3b',
            '#858796', '#5a5c69', '#2e59d9', '#17a673', '#2c9faf',
        ];

        foreach ($data as $index => $item) {
            $labels[] = $item->type;
            $values[] = (int) $item->count;
            $colors[] = $item->color ?? ($defaultColors[$index % count($defaultColors)]);
        }

        return [
            'labels' => $labels,
            'datasets' => [[
                'data' => $values,
                'backgroundColor' => $colors,
            ]],
        ];
    }

    /**
     * Get chart data for agreements by status
     */
    public function getStatusChartData(?int $repositoryId = null): array
    {
        $data = $this->repository->getAgreementsByStatus($repositoryId);

        $statusColors = [
            'draft' => '#6c757d',
            'pending_approval' => '#ffc107',
            'active' => '#28a745',
            'suspended' => '#fd7e14',
            'expired' => '#dc3545',
            'terminated' => '#343a40',
            'renewed' => '#17a2b8',
        ];

        $labels = [];
        $values = [];
        $colors = [];

        foreach ($data as $item) {
            $labels[] = ucfirst(str_replace('_', ' ', $item->status));
            $values[] = (int) $item->count;
            $colors[] = $statusColors[$item->status] ?? '#858796';
        }

        return [
            'labels' => $labels,
            'datasets' => [[
                'data' => $values,
                'backgroundColor' => $colors,
            ]],
        ];
    }

    /**
     * Get chart data for monthly trends
     */
    public function getTrendChartData(?int $repositoryId = null): array
    {
        $data = $this->repository->getMonthlyTrends(12, $repositoryId);

        $labels = [];
        $values = [];

        foreach ($data as $item) {
            $labels[] = date('M Y', strtotime($item->month . '-01'));
            $values[] = (int) $item->count;
        }

        return [
            'labels' => $labels,
            'datasets' => [[
                'label' => 'Agreements',
                'data' => $values,
                'borderColor' => '#4e73df',
                'backgroundColor' => 'rgba(78, 115, 223, 0.1)',
                'fill' => true,
                'tension' => 0.4,
            ]],
        ];
    }

    /**
     * Get alerts/notifications count
     */
    public function getAlertCounts(?int $repositoryId = null): array
    {
        $stats = $this->repository->getStatistics($repositoryId);

        return [
            'critical' => $stats['reminders']['pending'],
            'warning' => $stats['agreements']['expiring_soon'] + $stats['agreements']['review_due'],
            'info' => $stats['agreements']['draft'],
        ];
    }

    /**
     * Search donors for autocomplete
     */
    public function searchDonors(string $term): array
    {
        return $this->repository->searchDonors($term)->toArray();
    }
}
