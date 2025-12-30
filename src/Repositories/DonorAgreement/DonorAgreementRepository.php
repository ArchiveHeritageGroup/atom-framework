<?php

declare(strict_types=1);

namespace AtomFramework\Repositories\DonorAgreement;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

class DonorAgreementRepository
{
    protected string $table = 'donor_agreement';

    /**
     * Find agreement by ID with related data
     */
    public function find(int $id): ?object
    {
        return DB::table($this->table)
            ->leftJoin('agreement_type', 'donor_agreement.agreement_type_id', '=', 'agreement_type.id')
            ->leftJoin('donor', 'donor_agreement.donor_id', '=', 'donor.id')
            ->leftJoin('actor', 'donor.id', '=', 'actor.id')
            ->leftJoin('actor_i18n', function ($join) {
                $join->on('actor.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', 'en');
            })
            ->leftJoin('repository', 'donor_agreement.repository_id', '=', 'repository.id')
            ->leftJoin('actor as repo_actor', 'repository.id', '=', 'repo_actor.id')
            ->leftJoin('actor_i18n as repo_actor_i18n', function ($join) {
                $join->on('repo_actor.id', '=', 'repo_actor_i18n.id')
                    ->where('repo_actor_i18n.culture', '=', 'en');
            })
            ->select([
                'donor_agreement.*',
                'agreement_type.name as type_name',
                'agreement_type.code as type_code',
                'agreement_type.color as type_color',
                'agreement_type.requires_witness',
                'agreement_type.requires_legal_review',
                'actor_i18n.authorized_form_of_name as donor_name',
                'repo_actor_i18n.authorized_form_of_name as repository_name',
            ])
            ->where('donor_agreement.id', $id)
            ->first();
    }

    /**
     * Browse agreements with filters and pagination
     */
    public function browse(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        $query = DB::table($this->table)
            ->leftJoin('agreement_type', 'donor_agreement.agreement_type_id', '=', 'agreement_type.id')
            ->leftJoin('donor', 'donor_agreement.donor_id', '=', 'donor.id')
            ->leftJoin('actor', 'donor.id', '=', 'actor.id')
            ->leftJoin('actor_i18n', function ($join) {
                $join->on('actor.id', '=', 'actor_i18n.id')
                    ->where('actor_i18n.culture', '=', 'en');
            })
            ->select([
                'donor_agreement.id',
                'donor_agreement.agreement_number',
                'donor_agreement.title',
                'donor_agreement.status',
                'donor_agreement.agreement_date',
                'donor_agreement.expiry_date',
                'donor_agreement.review_date',
                'donor_agreement.donor_id',
                'agreement_type.name as type_name',
                'agreement_type.color as type_color',
                'actor_i18n.authorized_form_of_name as donor_name',
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
                $q->where('donor_agreement.title', 'LIKE', $search)
                    ->orWhere('donor_agreement.agreement_number', 'LIKE', $search)
                    ->orWhere('actor_i18n.authorized_form_of_name', 'LIKE', $search);
            });
        }

        if (!empty($filters['expiring_days'])) {
            $query->where('donor_agreement.expiry_date', '<=', date('Y-m-d', strtotime("+{$filters['expiring_days']} days")))
                ->where('donor_agreement.expiry_date', '>=', date('Y-m-d'))
                ->where('donor_agreement.status', 'active');
        }

        // Get total count
        $total = $query->count();

        // Apply pagination and sorting
        $query->orderBy('donor_agreement.created_at', 'DESC')
            ->offset(($page - 1) * $perPage)
            ->limit($perPage);

        return [
            'data' => $query->get(),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage),
        ];
    }

    /**
     * Create new agreement
     */
    public function create(array $data): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        return DB::table($this->table)->insertGetId($data);
    }

    /**
     * Update agreement
     */
    public function update(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');

        return DB::table($this->table)
            ->where('id', $id)
            ->update($data) > 0;
    }

    /**
     * Delete agreement
     */
    public function delete(int $id): bool
    {
        return DB::table($this->table)
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Generate next agreement number
     */
    public function generateAgreementNumber(string $prefix = 'AGR'): string
    {
        $year = date('Y');
        $pattern = $prefix . '-' . $year . '-%';

        $last = DB::table($this->table)
            ->where('agreement_number', 'LIKE', $pattern)
            ->orderBy('agreement_number', 'DESC')
            ->value('agreement_number');

        if ($last) {
            $parts = explode('-', $last);
            $seq = (int) end($parts) + 1;
        } else {
            $seq = 1;
        }

        return sprintf('%s-%s-%04d', $prefix, $year, $seq);
    }

    /**
     * Get all agreement types
     */
    public function getTypes(): Collection
    {
        return DB::table('agreement_type')
            ->where('is_active', 1)
            ->orderBy('sort_order')
            ->get();
    }

    /**
     * Get agreement statuses
     */
    public function getStatuses(): array
    {
        return [
            'draft' => 'Draft',
            'pending_review' => 'Pending Review',
            'pending_signature' => 'Pending Signature',
            'active' => 'Active',
            'expired' => 'Expired',
            'terminated' => 'Terminated',
            'superseded' => 'Superseded',
        ];
    }

    /**
     * Get rights for agreement
     */
    public function getRights(int $agreementId): Collection
    {
        return DB::table('donor_agreement_right')
            ->where('donor_agreement_id', $agreementId)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Add right to agreement
     */
    public function addRight(int $agreementId, array $data): int
    {
        $data['donor_agreement_id'] = $agreementId;
        $data['created_at'] = date('Y-m-d H:i:s');

        return DB::table('donor_agreement_right')->insertGetId($data);
    }

    /**
     * Delete right
     */
    public function deleteRight(int $id): bool
    {
        return DB::table('donor_agreement_right')
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Get restrictions for agreement
     */
    public function getRestrictions(int $agreementId): Collection
    {
        return DB::table('donor_agreement_restriction')
            ->where('donor_agreement_id', $agreementId)
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Add restriction to agreement
     */
    public function addRestriction(int $agreementId, array $data): int
    {
        $data['donor_agreement_id'] = $agreementId;
        $data['created_at'] = date('Y-m-d H:i:s');

        return DB::table('donor_agreement_restriction')->insertGetId($data);
    }

    /**
     * Delete restriction
     */
    public function deleteRestriction(int $id): bool
    {
        return DB::table('donor_agreement_restriction')
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Get documents for agreement
     */
    public function getDocuments(int $agreementId): Collection
    {
        return DB::table('donor_agreement_document')
            ->where('donor_agreement_id', $agreementId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * Add document to agreement
     */
    public function addDocument(int $agreementId, array $data): int
    {
        $data['donor_agreement_id'] = $agreementId;
        $data['created_at'] = date('Y-m-d H:i:s');

        return DB::table('donor_agreement_document')->insertGetId($data);
    }

    /**
     * Get document by ID
     */
    public function getDocument(int $id): ?object
    {
        return DB::table('donor_agreement_document')
            ->where('id', $id)
            ->first();
    }

    /**
     * Delete document
     */
    public function deleteDocument(int $id): bool
    {
        return DB::table('donor_agreement_document')
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Get reminders for agreement
     */
    public function getReminders(int $agreementId): Collection
    {
        return DB::table('donor_agreement_reminder')
            ->where('donor_agreement_id', $agreementId)
            ->orderBy('reminder_date')
            ->get();
    }

    /**
     * Add reminder to agreement
     */
    public function addReminder(int $agreementId, array $data): int
    {
        $data['donor_agreement_id'] = $agreementId;
        $data['created_at'] = date('Y-m-d H:i:s');

        return DB::table('donor_agreement_reminder')->insertGetId($data);
    }

    /**
     * Complete reminder
     */
    public function completeReminder(int $id, int $userId): bool
    {
        return DB::table('donor_agreement_reminder')
            ->where('id', $id)
            ->update([
                'is_completed' => 1,
                'completed_at' => date('Y-m-d H:i:s'),
                'completed_by' => $userId,
            ]) > 0;
    }

    /**
     * Get history for agreement
     */
    public function getHistory(int $agreementId): Collection
    {
        return DB::table('donor_agreement_history')
            ->leftJoin('user', 'donor_agreement_history.performed_by', '=', 'user.id')
            ->select([
                'donor_agreement_history.*',
                'user.username',
            ])
            ->where('donor_agreement_id', $agreementId)
            ->orderBy('created_at', 'DESC')
            ->get();
    }

    /**
     * Add history entry
     */
    public function addHistory(int $agreementId, string $action, array $data = []): int
    {
        return DB::table('donor_agreement_history')->insertGetId([
            'donor_agreement_id' => $agreementId,
            'action' => $action,
            'field_name' => $data['field_name'] ?? null,
            'old_value' => $data['old_value'] ?? null,
            'new_value' => $data['new_value'] ?? null,
            'performed_by' => $data['performed_by'] ?? null,
            'ip_address' => $data['ip_address'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Get linked accessions
     */
    public function getAccessions(int $agreementId): Collection
    {
        return DB::table('donor_agreement_accession')
            ->leftJoin('accession', 'donor_agreement_accession.accession_id', '=', 'accession.id')
            ->leftJoin('accession_i18n', function ($join) {
                $join->on('accession.id', '=', 'accession_i18n.id')
                    ->where('accession_i18n.culture', '=', 'en');
            })
            ->select([
                'donor_agreement_accession.*',
                'accession.identifier',
                'accession_i18n.title as accession_title',
            ])
            ->where('donor_agreement_id', $agreementId)
            ->get();
    }

    /**
     * Link accession to agreement
     */
    public function linkAccession(int $agreementId, int $accessionId, ?string $notes = null): bool
    {
        return DB::table('donor_agreement_accession')->insertOrIgnore([
            'donor_agreement_id' => $agreementId,
            'accession_id' => $accessionId,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
        ]) > 0;
    }

    /**
     * Get linked information objects
     */
    public function getRecords(int $agreementId): Collection
    {
        return DB::table('donor_agreement_record')
            ->leftJoin('information_object', 'donor_agreement_record.information_object_id', '=', 'information_object.id')
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', 'en');
            })
            ->leftJoin('slug', function ($join) {
                $join->on('information_object.id', '=', 'slug.object_id');
            })
            ->select([
                'donor_agreement_record.*',
                'information_object.identifier',
                'information_object_i18n.title as record_title',
                'slug.slug',
            ])
            ->where('donor_agreement_id', $agreementId)
            ->get();
    }

    /**
     * Link information object to agreement
     */
    public function linkRecord(int $agreementId, int $ioId, ?string $notes = null): bool
    {
        return DB::table('donor_agreement_record')->insertOrIgnore([
            'donor_agreement_id' => $agreementId,
            'information_object_id' => $ioId,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
        ]) > 0;
    }
}
