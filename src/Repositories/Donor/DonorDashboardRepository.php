<?php

declare(strict_types=1);

namespace AtomFramework\Repositories\Donor;

use AtomExtensions\Helpers\CultureHelper;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

class DonorDashboardRepository
{
    /**
     * Check if a table exists
     */
    protected function tableExists(string $table): bool
    {
        try {
            $result = DB::select("SHOW TABLES LIKE ?", [$table]);
            return count($result) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if a column exists in a table
     */
    protected function columnExists(string $table, string $column): bool
    {
        try {
            $result = DB::select(
                "SELECT COUNT(*) as cnt FROM INFORMATION_SCHEMA.COLUMNS 
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?",
                [$table, $column]
            );
            return isset($result[0]) && $result[0]->cnt > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get dashboard statistics
     */
    public function getStatistics(?int $repositoryId = null): array
    {
        // Initialize defaults
        $stats = (object) [
            'total' => 0,
            'active' => 0,
            'draft' => 0,
            'expired' => 0,
            'terminated_count' => 0,
            'expiring_soon' => 0,
            'review_due' => 0,
        ];
        $totalDonors = 0;
        $activeDonors = 0;
        $pendingRemindersCount = 0;
        $restrictionsActiveCount = 0;

        // Get donor counts
        try {
            $baseQuery = DB::table('donor');
            if ($repositoryId) {
                $baseQuery->where('repository_id', $repositoryId);
            }
            $totalDonors = (clone $baseQuery)->count();

            // Check if donor_agreement table exists before querying
            if ($this->tableExists('donor_agreement')) {
                $activeDonors = (clone $baseQuery)
                    ->whereExists(function ($query) {
                        $query->select(DB::raw(1))
                            ->from('donor_agreement')
                            ->whereColumn('donor_agreement.donor_id', 'donor.id')
                            ->where('donor_agreement.status', 'active');
                    })
                    ->count();
            }
        } catch (\Exception $e) {
            // Donors table might not exist or have different structure
        }

        // Get agreement statistics
        if ($this->tableExists('donor_agreement')) {
            try {
                $agreementStats = DB::table('donor_agreement')
                    ->select([
                        DB::raw('COUNT(*) as `total`'),
                        DB::raw("SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as `active`"),
                        DB::raw("SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as `draft`"),
                        DB::raw("SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as `expired`"),
                        DB::raw("SUM(CASE WHEN status = 'terminated' THEN 1 ELSE 0 END) as `terminated_count`"),
                        DB::raw("SUM(CASE WHEN expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as `expiring_soon`"),
                        DB::raw("SUM(CASE WHEN review_date <= CURDATE() AND status = 'active' THEN 1 ELSE 0 END) as `review_due`"),
                    ]);

                if ($repositoryId) {
                    $agreementStats->where('repository_id', $repositoryId);
                }

                $stats = $agreementStats->first() ?? $stats;
            } catch (\Exception $e) {
                // Agreement table structure might differ
            }
        }

        // Get pending reminders count
        if ($this->tableExists('donor_agreement_reminder') && $this->tableExists('donor_agreement')) {
            try {
                $pendingReminders = DB::table('donor_agreement_reminder')
                    ->join('donor_agreement', 'donor_agreement_reminder.donor_agreement_id', '=', 'donor_agreement.id')
                    ->where('donor_agreement_reminder.status', 'pending')
                    ->where('donor_agreement_reminder.reminder_date', '<=', date('Y-m-d'));

                if ($repositoryId) {
                    $pendingReminders->where('donor_agreement.repository_id', $repositoryId);
                }

                $pendingRemindersCount = $pendingReminders->count();
            } catch (\Exception $e) {
                // Reminder table structure might differ
            }
        }

        // Get active restrictions count - with column existence check
        if ($this->tableExists('donor_agreement_restriction') && $this->tableExists('donor_agreement')) {
            try {
                $restrictionsQuery = DB::table('donor_agreement_restriction')
                    ->join('donor_agreement', 'donor_agreement_restriction.donor_agreement_id', '=', 'donor_agreement.id');

                // Only filter by is_active if column exists
                if ($this->columnExists('donor_agreement_restriction', 'is_active')) {
                    $restrictionsQuery->where('donor_agreement_restriction.is_active', 1);
                }

                // Only filter by end_date if column exists
                if ($this->columnExists('donor_agreement_restriction', 'end_date')) {
                    $restrictionsQuery->where(function ($q) {
                        $q->whereNull('donor_agreement_restriction.end_date')
                            ->orWhere('donor_agreement_restriction.end_date', '>', date('Y-m-d'));
                    });
                }

                if ($repositoryId) {
                    $restrictionsQuery->where('donor_agreement.repository_id', $repositoryId);
                }

                $restrictionsActiveCount = $restrictionsQuery->count();
            } catch (\Exception $e) {
                // Restriction table structure might differ
            }
        }

        return [
            'donors' => [
                'total' => $totalDonors,
                'active' => $activeDonors,
                'inactive' => $totalDonors - $activeDonors,
            ],
            'agreements' => [
                'total' => (int) ($stats->total ?? 0),
                'active' => (int) ($stats->active ?? 0),
                'draft' => (int) ($stats->draft ?? 0),
                'expired' => (int) ($stats->expired ?? 0),
                'terminated' => (int) ($stats->terminated_count ?? 0),
                'expiring_soon' => (int) ($stats->expiring_soon ?? 0),
                'review_due' => (int) ($stats->review_due ?? 0),
            ],
            'reminders' => [
                'pending' => $pendingRemindersCount,
            ],
            'restrictions' => [
                'active' => $restrictionsActiveCount,
            ],
        ];
    }

    /**
     * Get recent donors
     */
    public function getRecentDonors(int $limit = 10, ?int $repositoryId = null): Collection
    {
        try {
            $query = DB::table('donor')
                ->join('actor', 'donor.id', '=', 'actor.id')
                ->join('actor_i18n', 'actor.id', '=', 'actor_i18n.id')
                ->leftJoin('contact_information', 'actor.id', '=', 'contact_information.actor_id')
                ->select([
                    'donor.id',
                    'actor_i18n.authorized_form_of_name as name',
                    'contact_information.email',
                    'contact_information.city',
                    'actor.created_at',
                ])
                ->where('actor_i18n.culture', CultureHelper::getCulture())
                ->orderBy('actor.created_at', 'DESC')
                ->limit($limit);

            if ($repositoryId) {
                $query->where('donor.repository_id', $repositoryId);
            }

            return $query->get();
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    /**
     * Get recent agreements
     */
    public function getRecentAgreements(int $limit = 10, ?int $repositoryId = null): Collection
    {
        if (!$this->tableExists('donor_agreement')) {
            return collect([]);
        }

        try {
            $query = DB::table('donor_agreement')
                ->leftJoin('donor', 'donor_agreement.donor_id', '=', 'donor.id')
                ->leftJoin('actor', 'donor.id', '=', 'actor.id')
                ->leftJoin('actor_i18n', 'actor.id', '=', 'actor_i18n.id');

            // Only join agreement_type if it exists
            if ($this->tableExists('agreement_type')) {
                $query->leftJoin('agreement_type', 'donor_agreement.agreement_type_id', '=', 'agreement_type.id');
            }

            $query->select([
                'donor_agreement.id',
                'donor_agreement.agreement_number',
                'donor_agreement.title',
                'donor_agreement.status',
                'donor_agreement.agreement_date',
                'donor_agreement.expiry_date',
                'donor_agreement.created_at',
                'actor_i18n.authorized_form_of_name as donor_name',
            ]);

            if ($this->tableExists('agreement_type')) {
                $query->addSelect('agreement_type.name as agreement_type');
            }

            $query->where(function ($q) {
                $q->whereNull('actor_i18n.culture')
                    ->orWhere('actor_i18n.culture', CultureHelper::getCulture());
            })
            ->orderBy('donor_agreement.created_at', 'DESC')
            ->limit($limit);

            if ($repositoryId) {
                $query->where('donor_agreement.repository_id', $repositoryId);
            }

            return $query->get();
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    /**
     * Get expiring agreements
     */
    public function getExpiringAgreements(int $daysAhead = 30, ?int $repositoryId = null): Collection
    {
        if (!$this->tableExists('donor_agreement')) {
            return collect([]);
        }

        try {
            $query = DB::table('donor_agreement')
                ->leftJoin('donor', 'donor_agreement.donor_id', '=', 'donor.id')
                ->leftJoin('actor', 'donor.id', '=', 'actor.id')
                ->leftJoin('actor_i18n', 'actor.id', '=', 'actor_i18n.id')
                ->select([
                    'donor_agreement.id',
                    'donor_agreement.agreement_number',
                    'donor_agreement.title',
                    'donor_agreement.expiry_date',
                    'actor_i18n.authorized_form_of_name as donor_name',
                    DB::raw('DATEDIFF(donor_agreement.expiry_date, CURDATE()) as days_remaining'),
                ])
                ->where('donor_agreement.status', 'active')
                ->whereNotNull('donor_agreement.expiry_date')
                ->whereBetween('donor_agreement.expiry_date', [
                    date('Y-m-d'),
                    date('Y-m-d', strtotime("+{$daysAhead} days")),
                ])
                ->where(function ($q) {
                    $q->whereNull('actor_i18n.culture')
                        ->orWhere('actor_i18n.culture', CultureHelper::getCulture());
                })
                ->orderBy('donor_agreement.expiry_date', 'ASC');

            if ($repositoryId) {
                $query->where('donor_agreement.repository_id', $repositoryId);
            }

            return $query->get();
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    /**
     * Get pending reminders
     */
    public function getPendingReminders(int $limit = 10, ?int $repositoryId = null): Collection
    {
        if (!$this->tableExists('donor_agreement_reminder') || !$this->tableExists('donor_agreement')) {
            return collect([]);
        }

        try {
            $query = DB::table('donor_agreement_reminder')
                ->join('donor_agreement', 'donor_agreement_reminder.donor_agreement_id', '=', 'donor_agreement.id')
                ->leftJoin('donor', 'donor_agreement.donor_id', '=', 'donor.id')
                ->leftJoin('actor', 'donor.id', '=', 'actor.id')
                ->leftJoin('actor_i18n', 'actor.id', '=', 'actor_i18n.id')
                ->select([
                    'donor_agreement_reminder.id',
                    'donor_agreement_reminder.reminder_type',
                    'donor_agreement_reminder.reminder_date',
                    'donor_agreement_reminder.priority',
                    'donor_agreement_reminder.message',
                    'donor_agreement.id as agreement_id',
                    'donor_agreement.agreement_number',
                    'donor_agreement.title as agreement_title',
                    'actor_i18n.authorized_form_of_name as donor_name',
                ])
                ->where('donor_agreement_reminder.status', 'pending')
                ->where('donor_agreement_reminder.reminder_date', '<=', date('Y-m-d'))
                ->where(function ($q) {
                    $q->whereNull('actor_i18n.culture')
                        ->orWhere('actor_i18n.culture', CultureHelper::getCulture());
                })
                ->orderBy('donor_agreement_reminder.priority', 'DESC')
                ->orderBy('donor_agreement_reminder.reminder_date', 'ASC')
                ->limit($limit);

            if ($repositoryId) {
                $query->where('donor_agreement.repository_id', $repositoryId);
            }

            return $query->get();
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    /**
     * Get agreements due for review
     */
    public function getReviewDue(int $limit = 10, ?int $repositoryId = null): Collection
    {
        if (!$this->tableExists('donor_agreement')) {
            return collect([]);
        }

        try {
            $query = DB::table('donor_agreement')
                ->leftJoin('donor', 'donor_agreement.donor_id', '=', 'donor.id')
                ->leftJoin('actor', 'donor.id', '=', 'actor.id')
                ->leftJoin('actor_i18n', 'actor.id', '=', 'actor_i18n.id')
                ->select([
                    'donor_agreement.id',
                    'donor_agreement.agreement_number',
                    'donor_agreement.title',
                    'donor_agreement.review_date',
                    'actor_i18n.authorized_form_of_name as donor_name',
                    DB::raw('DATEDIFF(CURDATE(), donor_agreement.review_date) as days_overdue'),
                ])
                ->where('donor_agreement.status', 'active')
                ->whereNotNull('donor_agreement.review_date')
                ->where('donor_agreement.review_date', '<=', date('Y-m-d'))
                ->where(function ($q) {
                    $q->whereNull('actor_i18n.culture')
                        ->orWhere('actor_i18n.culture', CultureHelper::getCulture());
                })
                ->orderBy('donor_agreement.review_date', 'ASC')
                ->limit($limit);

            if ($repositoryId) {
                $query->where('donor_agreement.repository_id', $repositoryId);
            }

            return $query->get();
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    /**
     * Get agreements by type for chart
     */
    public function getAgreementsByType(?int $repositoryId = null): Collection
    {
        if (!$this->tableExists('donor_agreement') || !$this->tableExists('agreement_type')) {
            return collect([]);
        }

        try {
            $query = DB::table('donor_agreement')
                ->join('agreement_type', 'donor_agreement.agreement_type_id', '=', 'agreement_type.id')
                ->select([
                    'agreement_type.name as type',
                    'agreement_type.color',
                    DB::raw('COUNT(*) as `count`'),
                ])
                ->groupBy('agreement_type.id', 'agreement_type.name', 'agreement_type.color')
                ->orderBy(DB::raw('`count`'), 'DESC');

            if ($repositoryId) {
                $query->where('donor_agreement.repository_id', $repositoryId);
            }

            return $query->get();
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    /**
     * Get agreements by status for chart
     */
    public function getAgreementsByStatus(?int $repositoryId = null): Collection
    {
        if (!$this->tableExists('donor_agreement')) {
            return collect([]);
        }

        try {
            $query = DB::table('donor_agreement')
                ->select([
                    'status',
                    DB::raw('COUNT(*) as `count`'),
                ])
                ->groupBy('status');

            if ($repositoryId) {
                $query->where('repository_id', $repositoryId);
            }

            return $query->get();
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    /**
     * Get monthly agreement trends
     */
    public function getMonthlyTrends(int $months = 12, ?int $repositoryId = null): Collection
    {
        if (!$this->tableExists('donor_agreement')) {
            return collect([]);
        }

        try {
            $query = DB::table('donor_agreement')
                ->select([
                    DB::raw("DATE_FORMAT(agreement_date, '%Y-%m') as `month`"),
                    DB::raw('COUNT(*) as `count`'),
                ])
                ->whereNotNull('agreement_date')
                ->where('agreement_date', '>=', date('Y-m-d', strtotime("-{$months} months")))
                ->groupBy(DB::raw("DATE_FORMAT(agreement_date, '%Y-%m')"))
                ->orderBy('month', 'ASC');

            if ($repositoryId) {
                $query->where('repository_id', $repositoryId);
            }

            return $query->get();
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    /**
     * Get active restrictions releasing soon
     */
    public function getRestrictionsReleasingSoon(int $daysAhead = 30, ?int $repositoryId = null): Collection
    {
        if (!$this->tableExists('donor_agreement_restriction') || !$this->tableExists('donor_agreement')) {
            return collect([]);
        }

        // Check if required columns exist
        if (!$this->columnExists('donor_agreement_restriction', 'end_date')) {
            return collect([]);
        }

        try {
            $query = DB::table('donor_agreement_restriction')
                ->join('donor_agreement', 'donor_agreement_restriction.donor_agreement_id', '=', 'donor_agreement.id')
                ->leftJoin('donor', 'donor_agreement.donor_id', '=', 'donor.id')
                ->leftJoin('actor', 'donor.id', '=', 'actor.id')
                ->leftJoin('actor_i18n', 'actor.id', '=', 'actor_i18n.id')
                ->select([
                    'donor_agreement_restriction.id',
                    'donor_agreement_restriction.restriction_type',
                    'donor_agreement_restriction.end_date',
                    'donor_agreement.id as agreement_id',
                    'donor_agreement.agreement_number',
                    'actor_i18n.authorized_form_of_name as donor_name',
                    DB::raw('DATEDIFF(donor_agreement_restriction.end_date, CURDATE()) as days_remaining'),
                ]);

            // Only filter by is_active if column exists
            if ($this->columnExists('donor_agreement_restriction', 'is_active')) {
                $query->where('donor_agreement_restriction.is_active', 1);
            }

            $query->whereNotNull('donor_agreement_restriction.end_date')
                ->whereBetween('donor_agreement_restriction.end_date', [
                    date('Y-m-d'),
                    date('Y-m-d', strtotime("+{$daysAhead} days")),
                ])
                ->where(function ($q) {
                    $q->whereNull('actor_i18n.culture')
                        ->orWhere('actor_i18n.culture', CultureHelper::getCulture());
                })
                ->orderBy('donor_agreement_restriction.end_date', 'ASC');

            if ($repositoryId) {
                $query->where('donor_agreement.repository_id', $repositoryId);
            }

            return $query->get();
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    /**
     * Get top donors by agreement count
     */
    public function getTopDonors(int $limit = 10, ?int $repositoryId = null): Collection
    {
        try {
            $query = DB::table('donor')
                ->join('actor', 'donor.id', '=', 'actor.id')
                ->join('actor_i18n', 'actor.id', '=', 'actor_i18n.id');

            if ($this->tableExists('donor_agreement')) {
                $query->leftJoin('donor_agreement', 'donor.id', '=', 'donor_agreement.donor_id')
                    ->select([
                        'donor.id',
                        'actor_i18n.authorized_form_of_name as name',
                        DB::raw('COUNT(donor_agreement.id) as agreement_count'),
                        DB::raw("SUM(CASE WHEN donor_agreement.status = 'active' THEN 1 ELSE 0 END) as active_agreements"),
                    ])
                    ->where('actor_i18n.culture', CultureHelper::getCulture())
                    ->groupBy('donor.id', 'actor_i18n.authorized_form_of_name')
                    ->having('agreement_count', '>', 0)
                    ->orderBy('agreement_count', 'DESC');
            } else {
                $query->select([
                    'donor.id',
                    'actor_i18n.authorized_form_of_name as name',
                    DB::raw('0 as agreement_count'),
                    DB::raw('0 as active_agreements'),
                ])
                ->where('actor_i18n.culture', CultureHelper::getCulture())
                ->groupBy('donor.id', 'actor_i18n.authorized_form_of_name');
            }

            $query->limit($limit);

            if ($repositoryId) {
                $query->where('donor.repository_id', $repositoryId);
            }

            return $query->get();
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    /**
     * Get recent activity log
     */
    public function getRecentActivity(int $limit = 20, ?int $repositoryId = null): Collection
    {
        if (!$this->tableExists('donor_agreement_history') || !$this->tableExists('donor_agreement')) {
            return collect([]);
        }

        try {
            $query = DB::table('donor_agreement_history')
                ->join('donor_agreement', 'donor_agreement_history.donor_agreement_id', '=', 'donor_agreement.id')
                ->leftJoin('user', 'donor_agreement_history.user_id', '=', 'user.id')
                ->select([
                    'donor_agreement_history.id',
                    'donor_agreement_history.action',
                    'donor_agreement_history.field_name',
                    'donor_agreement_history.old_value',
                    'donor_agreement_history.new_value',
                    'donor_agreement_history.created_at',
                    'donor_agreement.id as agreement_id',
                    'donor_agreement.agreement_number',
                    'user.username',
                ])
                ->orderBy('donor_agreement_history.created_at', 'DESC')
                ->limit($limit);

            if ($repositoryId) {
                $query->where('donor_agreement.repository_id', $repositoryId);
            }

            return $query->get();
        } catch (\Exception $e) {
            return collect([]);
        }
    }

    /**
     * Search donors
     */
    public function searchDonors(string $term, int $limit = 20): Collection
    {
        try {
            return DB::table('donor')
                ->join('actor', 'donor.id', '=', 'actor.id')
                ->join('actor_i18n', 'actor.id', '=', 'actor_i18n.id')
                ->leftJoin('contact_information', 'actor.id', '=', 'contact_information.actor_id')
                ->select([
                    'donor.id',
                    'actor_i18n.authorized_form_of_name as name',
                    'contact_information.email',
                ])
                ->where('actor_i18n.culture', CultureHelper::getCulture())
                ->where(function ($q) use ($term) {
                    $q->where('actor_i18n.authorized_form_of_name', 'LIKE', "%{$term}%")
                        ->orWhere('contact_information.email', 'LIKE', "%{$term}%");
                })
                ->orderBy('actor_i18n.authorized_form_of_name')
                ->limit($limit)
                ->get();
        } catch (\Exception $e) {
            return collect([]);
        }
    }
}
