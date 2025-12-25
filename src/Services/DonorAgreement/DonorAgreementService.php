<?php

declare(strict_types=1);

namespace AtomFramework\Services\DonorAgreement;

use AtomExtensions\Helpers\CultureHelper;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

class DonorAgreementService
{
    /**
     * Status options
     */
    public function getStatuses(): array
    {
        return [
            'draft' => 'Draft',
            'pending_approval' => 'Pending Approval',
            'active' => 'Active',
            'suspended' => 'Suspended',
            'expired' => 'Expired',
            'terminated' => 'Terminated',
            'renewed' => 'Renewed',
        ];
    }

    /**
     * Get agreement types
     */
    public function getTypes(): Collection
    {
        if (!$this->tableExists('agreement_type')) {
            return collect([]);
        }

        return DB::table('agreement_type')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Browse agreements with filters
     */
    public function browse(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        if (!$this->tableExists('donor_agreement')) {
            return ['data' => [], 'total' => 0, 'page' => $page, 'per_page' => $perPage];
        }

        $query = DB::table('donor_agreement')
            ->leftJoin('donor', 'donor_agreement.donor_id', '=', 'donor.id')
            ->leftJoin('actor', 'donor.id', '=', 'actor.id')
            ->leftJoin('actor_i18n', function ($join) {
                $join->on('actor.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', CultureHelper::getCulture());
            })
            ->leftJoin('slug', 'donor.id', '=', 'slug.object_id')
            ->leftJoin('agreement_type', 'donor_agreement.agreement_type_id', '=', 'agreement_type.id')
            ->select([
                'donor_agreement.*',
                'actor_i18n.authorized_form_of_name as donor_name',
                'agreement_type.name as type_name',
                'slug.slug as donor_slug',
            ]);

        // Apply filters
        if (!empty($filters['status'])) {
            $query->where('donor_agreement.status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $query->where('donor_agreement.agreement_type_id', $filters['type']);
        }

        if (!empty($filters['donor_id'])) {
            $query->where('donor_agreement.donor_id', $filters['donor_id']);
        }

        if (!empty($filters['repository_id'])) {
            $query->where('donor_agreement.repository_id', $filters['repository_id']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('donor_agreement.agreement_number', 'LIKE', $search)
                    ->orWhere('donor_agreement.title', 'LIKE', $search);
            });
        }

        if (!empty($filters['expiring'])) {
            $days = (int) $filters['expiring'];
            $query->where('donor_agreement.status', 'active')
                ->whereNotNull('donor_agreement.expiry_date')
                ->whereBetween('donor_agreement.expiry_date', [
                    date('Y-m-d'),
                    date('Y-m-d', strtotime("+{$days} days")),
                ]);
        }

        $query->orderBy('donor_agreement.created_at', 'DESC');

        // Get total
        $total = $query->count();

        // Paginate
        $offset = ($page - 1) * $perPage;
        $data = $query->offset($offset)->limit($perPage)->get();

        return [
            'data' => $data,
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Find agreement by ID
     */
    public function find(int $id): ?object
    {
        if (!$this->tableExists('donor_agreement')) {
            return null;
        }

        return DB::table('donor_agreement')
            ->leftJoin('donor', 'donor_agreement.donor_id', '=', 'donor.id')
            ->leftJoin('actor', 'donor.id', '=', 'actor.id')
            ->leftJoin('actor_i18n', function ($join) {
                $join->on('actor.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', CultureHelper::getCulture());
            })
            ->leftJoin('slug', 'donor.id', '=', 'slug.object_id')
            ->leftJoin('agreement_type', 'donor_agreement.agreement_type_id', '=', 'agreement_type.id')
            ->select([
                'donor_agreement.*',
                'actor_i18n.authorized_form_of_name as donor_name',
                'agreement_type.name as type_name',
                'slug.slug as donor_slug',
            ])
            ->where('donor_agreement.id', $id)
            ->first();
    }

    /**
     * Create agreement
     */
    public function create(array $data): int
    {
        // Generate agreement number if not provided
        if (empty($data['agreement_number'])) {
            $data['agreement_number'] = $this->generateAgreementNumber($data['agreement_type_id'] ?? null);
        }

        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        // Filter to valid columns
        $validColumns = [
            'agreement_number', 'title', 'description', 'agreement_type_id',
            'donor_id', 'repository_id', 'status', 'agreement_date',
            'effective_date', 'expiry_date', 'review_date', 'notes',
            'created_by', 'created_at', 'updated_at',
        ];

        $insertData = array_intersect_key($data, array_flip($validColumns));

        return DB::table('donor_agreement')->insertGetId($insertData);
    }

    /**
     * Update agreement
     */
    public function update(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');

        // Filter to valid columns
        $validColumns = [
            'agreement_number', 'title', 'description', 'agreement_type_id',
            'donor_id', 'repository_id', 'status', 'agreement_date',
            'effective_date', 'expiry_date', 'review_date', 'notes',
            'updated_by', 'updated_at',
        ];

        $updateData = array_intersect_key($data, array_flip($validColumns));

        return DB::table('donor_agreement')
            ->where('id', $id)
            ->update($updateData) > 0;
    }

    /**
     * Delete agreement
     */
    public function delete(int $id): bool
    {
        // Delete related records first
        if ($this->tableExists('donor_agreement_document')) {
            DB::table('donor_agreement_document')->where('donor_agreement_id', $id)->delete();
        }
        if ($this->tableExists('donor_agreement_reminder')) {
            DB::table('donor_agreement_reminder')->where('donor_agreement_id', $id)->delete();
        }
        if ($this->tableExists('donor_agreement_restriction')) {
            DB::table('donor_agreement_restriction')->where('donor_agreement_id', $id)->delete();
        }
        if ($this->tableExists('donor_agreement_right')) {
            DB::table('donor_agreement_right')->where('donor_agreement_id', $id)->delete();
        }
        if ($this->tableExists('donor_agreement_history')) {
            DB::table('donor_agreement_history')->where('donor_agreement_id', $id)->delete();
        }

        return DB::table('donor_agreement')->where('id', $id)->delete() > 0;
    }

    /**
     * Get documents for agreement
     */
    public function getDocuments(int $agreementId): Collection
    {
        if (!$this->tableExists('donor_agreement_document')) {
            return collect([]);
        }

        return DB::table('donor_agreement_document')
            ->where('donor_agreement_id', $agreementId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * Get reminders for agreement
     */
    public function getReminders(int $agreementId): Collection
    {
        if (!$this->tableExists('donor_agreement_reminder')) {
            return collect([]);
        }

        return DB::table('donor_agreement_reminder')
            ->where('donor_agreement_id', $agreementId)
            ->orderBy('reminder_date', 'ASC')
            ->get();
    }

    /**
     * Get pending reminders
     */
    public function getPendingReminders(): Collection
    {
        if (!$this->tableExists('donor_agreement_reminder') || !$this->tableExists('donor_agreement')) {
            return collect([]);
        }

        return DB::table('donor_agreement_reminder')
            ->join('donor_agreement', 'donor_agreement_reminder.donor_agreement_id', '=', 'donor_agreement.id')
            ->select([
                'donor_agreement_reminder.*',
                'donor_agreement.agreement_number',
            ])
            ->where('donor_agreement_reminder.status', 'pending')
            ->where('donor_agreement_reminder.reminder_date', '<=', date('Y-m-d'))
            ->orderBy('donor_agreement_reminder.priority', 'DESC')
            ->orderBy('donor_agreement_reminder.reminder_date', 'ASC')
            ->get();
    }

    /**
     * Get history for agreement
     */
    public function getHistory(int $agreementId): Collection
    {
        if (!$this->tableExists('donor_agreement_history')) {
            return collect([]);
        }

        return DB::table('donor_agreement_history')
            ->leftJoin('user', 'donor_agreement_history.user_id', '=', 'user.id')
            ->select([
                'donor_agreement_history.*',
                'user.username',
            ])
            ->where('donor_agreement_history.donor_agreement_id', $agreementId)
            ->orderBy('donor_agreement_history.created_at', 'DESC')
            ->get();
    }

    /**
     * Generate agreement number
     */
    protected function generateAgreementNumber(?int $typeId = null): string
    {
        $prefix = 'AGR';

        if ($typeId && $this->tableExists('agreement_type')) {
            $type = DB::table('agreement_type')->where('id', $typeId)->first();
            if ($type && !empty($type->prefix)) {
                $prefix = $type->prefix;
            }
        }

        $year = date('Y');
        $lastNum = DB::table('donor_agreement')
            ->where('agreement_number', 'LIKE', "{$prefix}-{$year}-%")
            ->max(DB::raw("CAST(SUBSTRING_INDEX(agreement_number, '-', -1) AS UNSIGNED)"));

        $nextNum = ($lastNum ?? 0) + 1;

        return sprintf('%s-%s-%04d', $prefix, $year, $nextNum);
    }

    /**
     * Check if table exists
     */
    protected function tableExists(string $table): bool
    {
        try {
            $result = DB::select("SHOW TABLES LIKE '" . $table . "'");
            return count($result) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
}
