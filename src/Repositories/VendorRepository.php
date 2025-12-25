<?php

namespace AtomFramework\Repositories;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

class VendorRepository
{
    protected string $vendorsTable = 'ahg_vendors';
    protected string $contactsTable = 'ahg_vendor_contacts';
    protected string $servicesTable = 'ahg_vendor_services';
    protected string $serviceTypesTable = 'ahg_vendor_service_types';
    protected string $transactionsTable = 'ahg_vendor_transactions';
    protected string $transactionItemsTable = 'ahg_vendor_transaction_items';
    protected string $historyTable = 'ahg_vendor_transaction_history';
    protected string $attachmentsTable = 'ahg_vendor_transaction_attachments';
    protected string $metricsTable = 'ahg_vendor_metrics';

    // =========================================================================
    // VENDOR MANAGEMENT
    // =========================================================================

    public function getAllVendors(array $filters = []): Collection
    {
        $query = DB::table($this->vendorsTable)
            ->select([
                'ahg_vendors.*',
                DB::raw('(SELECT COUNT(*) FROM ahg_vendor_transactions WHERE vendor_id = ahg_vendors.id) as transaction_count'),
                DB::raw('(SELECT COUNT(*) FROM ahg_vendor_transactions WHERE vendor_id = ahg_vendors.id AND status NOT IN ("returned", "cancelled")) as active_transactions'),
            ]);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (!empty($filters['vendor_type'])) {
            $query->where('vendor_type', $filters['vendor_type']);
        }

        if (!empty($filters['service_type_id'])) {
            $query->whereExists(function ($q) use ($filters) {
                $q->select(DB::raw(1))
                    ->from($this->servicesTable)
                    ->whereColumn('vendor_id', 'ahg_vendors.id')
                    ->where('service_type_id', $filters['service_type_id']);
            });
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', $search)
                    ->orWhere('vendor_code', 'LIKE', $search)
                    ->orWhere('email', 'LIKE', $search)
                    ->orWhere('city', 'LIKE', $search);
            });
        }

        if (!empty($filters['has_insurance'])) {
            $query->where('has_insurance', 1)
                ->where('insurance_expiry_date', '>=', date('Y-m-d'));
        }

        $sortField = $filters['sort'] ?? 'name';
        $sortDir = $filters['direction'] ?? 'asc';
        $query->orderBy($sortField, $sortDir);

        return $query->get();
    }

    public function getVendorById(int $id): ?object
    {
        return DB::table($this->vendorsTable)->where('id', $id)->first();
    }

    public function getVendorBySlug(string $slug): ?object
    {
        return DB::table($this->vendorsTable)->where('slug', $slug)->first();
    }

    public function createVendor(array $data): int
    {
        $data['slug'] = $this->generateSlug($data['name']);
        $data['vendor_code'] = $data['vendor_code'] ?? $this->generateVendorCode();
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        return DB::table($this->vendorsTable)->insertGetId($data);
    }

    public function updateVendor(int $id, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');

        if (isset($data['name'])) {
            $data['slug'] = $this->generateSlug($data['name'], $id);
        }

        return DB::table($this->vendorsTable)->where('id', $id)->update($data) >= 0;
    }

    public function deleteVendor(int $id): bool
    {
        $activeTransactions = DB::table($this->transactionsTable)
            ->where('vendor_id', $id)
            ->whereNotIn('status', ['returned', 'cancelled'])
            ->count();

        if ($activeTransactions > 0) {
            return false;
        }

        return DB::table($this->vendorsTable)->where('id', $id)->delete() > 0;
    }

    protected function generateSlug(string $name, ?int $excludeId = null): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        $originalSlug = $slug;
        $counter = 1;

        while (true) {
            $query = DB::table($this->vendorsTable)->where('slug', $slug);
            if ($excludeId) {
                $query->where('id', '!=', $excludeId);
            }
            if (!$query->exists()) {
                break;
            }
            $slug = $originalSlug . '-' . $counter++;
        }

        return $slug;
    }

    protected function generateVendorCode(): string
    {
        $prefix = 'VND';
        $year = date('y');
        $lastCode = DB::table($this->vendorsTable)
            ->where('vendor_code', 'LIKE', "{$prefix}{$year}%")
            ->orderBy('vendor_code', 'desc')
            ->value('vendor_code');

        if ($lastCode) {
            $num = (int) substr($lastCode, -4) + 1;
        } else {
            $num = 1;
        }

        return $prefix . $year . str_pad($num, 4, '0', STR_PAD_LEFT);
    }

    // =========================================================================
    // VENDOR CONTACTS
    // =========================================================================

    public function getVendorContacts(int $vendorId): Collection
    {
        return DB::table($this->contactsTable)
            ->where('vendor_id', $vendorId)
            ->orderByDesc('is_primary')
            ->orderBy('name')
            ->get();
    }

    public function addContact(int $vendorId, array $data): int
    {
        if (!empty($data['is_primary'])) {
            DB::table($this->contactsTable)
                ->where('vendor_id', $vendorId)
                ->update(['is_primary' => 0]);
        }

        $data['vendor_id'] = $vendorId;
        $data['created_at'] = date('Y-m-d H:i:s');

        return DB::table($this->contactsTable)->insertGetId($data);
    }

    public function updateContact(int $contactId, array $data): bool
    {
        if (!empty($data['is_primary'])) {
            $contact = DB::table($this->contactsTable)->where('id', $contactId)->first();
            if ($contact) {
                DB::table($this->contactsTable)
                    ->where('vendor_id', $contact->vendor_id)
                    ->where('id', '!=', $contactId)
                    ->update(['is_primary' => 0]);
            }
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        return DB::table($this->contactsTable)->where('id', $contactId)->update($data) >= 0;
    }

    public function deleteContact(int $contactId): bool
    {
        return DB::table($this->contactsTable)->where('id', $contactId)->delete() > 0;
    }

    // =========================================================================
    // SERVICE TYPES
    // =========================================================================

    public function getAllServiceTypes(bool $activeOnly = true): Collection
    {
        $query = DB::table($this->serviceTypesTable);

        if ($activeOnly) {
            $query->where('is_active', 1);
        }

        return $query->orderBy('display_order')->orderBy('name')->get();
    }

    public function getServiceTypeById(int $id): ?object
    {
        return DB::table($this->serviceTypesTable)->where('id', $id)->first();
    }

    public function getServiceTypeBySlug(string $slug): ?object
    {
        return DB::table($this->serviceTypesTable)->where('slug', $slug)->first();
    }

    // =========================================================================
    // VENDOR SERVICES (linking vendors to service types)
    // =========================================================================

    public function getVendorServices(int $vendorId): Collection
    {
        return DB::table($this->servicesTable)
            ->join($this->serviceTypesTable, 'ahg_vendor_services.service_type_id', '=', 'ahg_vendor_service_types.id')
            ->where('ahg_vendor_services.vendor_id', $vendorId)
            ->select([
                'ahg_vendor_services.*',
                'ahg_vendor_service_types.name as service_name',
                'ahg_vendor_service_types.slug as service_slug',
            ])
            ->orderBy('ahg_vendor_service_types.display_order')
            ->get();
    }

    public function getVendorsForService(int $serviceTypeId): Collection
    {
        return DB::table($this->servicesTable)
            ->join($this->vendorsTable, 'ahg_vendor_services.vendor_id', '=', 'ahg_vendors.id')
            ->where('ahg_vendor_services.service_type_id', $serviceTypeId)
            ->where('ahg_vendors.status', 'active')
            ->select([
                'ahg_vendors.*',
                'ahg_vendor_services.hourly_rate',
                'ahg_vendor_services.fixed_rate',
                'ahg_vendor_services.is_preferred',
            ])
            ->orderByDesc('ahg_vendor_services.is_preferred')
            ->orderBy('ahg_vendors.name')
            ->get();
    }

    public function assignServiceToVendor(int $vendorId, int $serviceTypeId, array $data = []): bool
    {
        $exists = DB::table($this->servicesTable)
            ->where('vendor_id', $vendorId)
            ->where('service_type_id', $serviceTypeId)
            ->exists();

        if ($exists) {
            return DB::table($this->servicesTable)
                ->where('vendor_id', $vendorId)
                ->where('service_type_id', $serviceTypeId)
                ->update($data) >= 0;
        }

        $data['vendor_id'] = $vendorId;
        $data['service_type_id'] = $serviceTypeId;
        $data['created_at'] = date('Y-m-d H:i:s');

        return DB::table($this->servicesTable)->insert($data);
    }

    public function removeServiceFromVendor(int $vendorId, int $serviceTypeId): bool
    {
        return DB::table($this->servicesTable)
            ->where('vendor_id', $vendorId)
            ->where('service_type_id', $serviceTypeId)
            ->delete() > 0;
    }

    // =========================================================================
    // TRANSACTIONS
    // =========================================================================

    public function getAllTransactions(array $filters = []): Collection
    {
        $query = DB::table($this->transactionsTable)
            ->join($this->vendorsTable, 'ahg_vendor_transactions.vendor_id', '=', 'ahg_vendors.id')
            ->join($this->serviceTypesTable, 'ahg_vendor_transactions.service_type_id', '=', 'ahg_vendor_service_types.id')
            ->select([
                'ahg_vendor_transactions.*',
                'ahg_vendors.name as vendor_name',
                'ahg_vendors.slug as vendor_slug',
                'ahg_vendor_service_types.name as service_name',
                DB::raw('(SELECT COUNT(*) FROM ahg_vendor_transaction_items WHERE transaction_id = ahg_vendor_transactions.id) as item_count'),
            ]);

        if (!empty($filters['status'])) {
            if (is_array($filters['status'])) {
                $query->whereIn('ahg_vendor_transactions.status', $filters['status']);
            } else {
                $query->where('ahg_vendor_transactions.status', $filters['status']);
            }
        }

        if (!empty($filters['vendor_id'])) {
            $query->where('ahg_vendor_transactions.vendor_id', $filters['vendor_id']);
        }

        if (!empty($filters['service_type_id'])) {
            $query->where('ahg_vendor_transactions.service_type_id', $filters['service_type_id']);
        }

        if (!empty($filters['overdue'])) {
            $query->where('ahg_vendor_transactions.expected_return_date', '<', date('Y-m-d'))
                ->whereNull('ahg_vendor_transactions.actual_return_date')
                ->whereNotIn('ahg_vendor_transactions.status', ['returned', 'cancelled']);
        }

        if (!empty($filters['date_from'])) {
            $query->where('ahg_vendor_transactions.request_date', '>=', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $query->where('ahg_vendor_transactions.request_date', '<=', $filters['date_to']);
        }

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($search) {
                $q->where('ahg_vendor_transactions.transaction_number', 'LIKE', $search)
                    ->orWhere('ahg_vendors.name', 'LIKE', $search);
            });
        }

        $sortField = $filters['sort'] ?? 'ahg_vendor_transactions.created_at';
        $sortDir = $filters['direction'] ?? 'desc';
        $query->orderBy($sortField, $sortDir);

        return $query->get();
    }

    public function getActiveTransactions(): Collection
    {
        return $this->getAllTransactions([
            'status' => ['pending_approval', 'approved', 'dispatched', 'received_by_vendor', 'in_progress', 'completed', 'ready_for_collection'],
        ]);
    }

    public function getOverdueTransactions(): Collection
    {
        return $this->getAllTransactions(['overdue' => true]);
    }

    public function getTransactionById(int $id): ?object
    {
        return DB::table($this->transactionsTable)
            ->join($this->vendorsTable, 'ahg_vendor_transactions.vendor_id', '=', 'ahg_vendors.id')
            ->join($this->serviceTypesTable, 'ahg_vendor_transactions.service_type_id', '=', 'ahg_vendor_service_types.id')
            ->where('ahg_vendor_transactions.id', $id)
            ->select([
                'ahg_vendor_transactions.*',
                'ahg_vendors.name as vendor_name',
                'ahg_vendors.slug as vendor_slug',
                'ahg_vendors.email as vendor_email',
                'ahg_vendors.phone as vendor_phone',
                'ahg_vendor_service_types.name as service_name',
                'ahg_vendor_service_types.requires_insurance',
                'ahg_vendor_service_types.requires_valuation',
            ])
            ->first();
    }

    public function getTransactionByNumber(string $transactionNumber): ?object
    {
        $transaction = DB::table($this->transactionsTable)
            ->where('transaction_number', $transactionNumber)
            ->first();

        return $transaction ? $this->getTransactionById($transaction->id) : null;
    }

    public function createTransaction(array $data): int
    {
        $data['transaction_number'] = $this->generateTransactionNumber();
        $data['created_at'] = date('Y-m-d H:i:s');
        $data['updated_at'] = date('Y-m-d H:i:s');

        $transactionId = DB::table($this->transactionsTable)->insertGetId($data);

        $this->logTransactionHistory($transactionId, null, $data['status'] ?? 'pending_approval', $data['requested_by'], 'Transaction created');

        return $transactionId;
    }

    public function updateTransaction(int $id, array $data, int $userId, ?string $notes = null): bool
    {
        $current = DB::table($this->transactionsTable)->where('id', $id)->first();

        if (!$current) {
            return false;
        }

        $data['updated_at'] = date('Y-m-d H:i:s');

        if (isset($data['status']) && $data['status'] !== $current->status) {
            $this->logTransactionHistory($id, $current->status, $data['status'], $userId, $notes);
        }

        return DB::table($this->transactionsTable)->where('id', $id)->update($data) >= 0;
    }

    public function updateTransactionStatus(int $id, string $status, int $userId, ?string $notes = null): bool
    {
        $data = [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        switch ($status) {
            case 'approved':
                $data['approval_date'] = date('Y-m-d');
                $data['approved_by'] = $userId;
                break;
            case 'dispatched':
                $data['dispatch_date'] = date('Y-m-d');
                $data['dispatched_by'] = $userId;
                break;
            case 'returned':
                $data['actual_return_date'] = date('Y-m-d');
                $data['received_by'] = $userId;
                break;
        }

        return $this->updateTransaction($id, $data, $userId, $notes);
    }

    protected function generateTransactionNumber(): string
    {
        $prefix = 'TXN';
        $year = date('Y');
        $month = date('m');

        $lastNumber = DB::table($this->transactionsTable)
            ->where('transaction_number', 'LIKE', "{$prefix}-{$year}{$month}%")
            ->orderBy('transaction_number', 'desc')
            ->value('transaction_number');

        if ($lastNumber) {
            $num = (int) substr($lastNumber, -4) + 1;
        } else {
            $num = 1;
        }

        return $prefix . '-' . $year . $month . '-' . str_pad($num, 4, '0', STR_PAD_LEFT);
    }

    protected function logTransactionHistory(int $transactionId, ?string $statusFrom, string $statusTo, int $userId, ?string $notes = null): void
    {
        DB::table($this->historyTable)->insert([
            'transaction_id' => $transactionId,
            'status_from' => $statusFrom,
            'status_to' => $statusTo,
            'changed_by' => $userId,
            'notes' => $notes,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function getTransactionHistory(int $transactionId): Collection
    {
        return DB::table($this->historyTable)
            ->leftJoin('user', 'ahg_vendor_transaction_history.changed_by', '=', 'user.id')
            ->where('transaction_id', $transactionId)
            ->select([
                'ahg_vendor_transaction_history.*',
                'user.username as changed_by_name',
            ])
            ->orderByDesc('created_at')
            ->get();
    }

    // =========================================================================
    // TRANSACTION ITEMS
    // =========================================================================

    public function getTransactionItems(int $transactionId): Collection
    {
        return DB::table($this->transactionItemsTable)
            ->leftJoin('information_object', 'ahg_vendor_transaction_items.information_object_id', '=', 'information_object.id')
            ->leftJoin('slug', 'information_object.id', '=', 'slug.object_id')
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', 'en');
            })
            ->where('ahg_vendor_transaction_items.transaction_id', $transactionId)
            ->select([
                'ahg_vendor_transaction_items.*',
                'information_object.identifier',
                'slug.slug as io_slug',
                'information_object_i18n.title as io_title',
            ])
            ->get();
    }

    public function addTransactionItem(int $transactionId, int $informationObjectId, array $data = []): int
    {
        $io = DB::table('information_object')
            ->leftJoin('information_object_i18n', function ($join) {
                $join->on('information_object.id', '=', 'information_object_i18n.id')
                    ->where('information_object_i18n.culture', '=', 'en');
            })
            ->where('information_object.id', $informationObjectId)
            ->select(['information_object.identifier', 'information_object_i18n.title'])
            ->first();

        $data['transaction_id'] = $transactionId;
        $data['information_object_id'] = $informationObjectId;
        $data['item_title'] = $io->title ?? null;
        $data['item_reference'] = $io->identifier ?? null;
        $data['created_at'] = date('Y-m-d H:i:s');

        return DB::table($this->transactionItemsTable)->insertGetId($data);
    }

    public function updateTransactionItem(int $itemId, array $data): bool
    {
        $data['updated_at'] = date('Y-m-d H:i:s');

        return DB::table($this->transactionItemsTable)->where('id', $itemId)->update($data) >= 0;
    }

    public function removeTransactionItem(int $itemId): bool
    {
        return DB::table($this->transactionItemsTable)->where('id', $itemId)->delete() > 0;
    }

    public function getItemTransactionHistory(int $informationObjectId): Collection
    {
        return DB::table($this->transactionItemsTable)
            ->join($this->transactionsTable, 'ahg_vendor_transaction_items.transaction_id', '=', 'ahg_vendor_transactions.id')
            ->join($this->vendorsTable, 'ahg_vendor_transactions.vendor_id', '=', 'ahg_vendors.id')
            ->join($this->serviceTypesTable, 'ahg_vendor_transactions.service_type_id', '=', 'ahg_vendor_service_types.id')
            ->where('ahg_vendor_transaction_items.information_object_id', $informationObjectId)
            ->select([
                'ahg_vendor_transactions.*',
                'ahg_vendor_transaction_items.condition_before',
                'ahg_vendor_transaction_items.condition_after',
                'ahg_vendor_transaction_items.service_description',
                'ahg_vendor_transaction_items.item_cost',
                'ahg_vendors.name as vendor_name',
                'ahg_vendor_service_types.name as service_name',
            ])
            ->orderByDesc('ahg_vendor_transactions.request_date')
            ->get();
    }

    // =========================================================================
    // ATTACHMENTS
    // =========================================================================

    public function getTransactionAttachments(int $transactionId, ?string $type = null): Collection
    {
        $query = DB::table($this->attachmentsTable)
            ->where('transaction_id', $transactionId);

        if ($type) {
            $query->where('attachment_type', $type);
        }

        return $query->orderBy('created_at')->get();
    }

    public function addAttachment(int $transactionId, array $data): int
    {
        $data['transaction_id'] = $transactionId;
        $data['created_at'] = date('Y-m-d H:i:s');

        $attachmentId = DB::table($this->attachmentsTable)->insertGetId($data);

        $updateField = 'has_' . $data['attachment_type'] . 's';
        if (in_array($updateField, ['has_quotes', 'has_invoices', 'has_condition_reports'])) {
            DB::table($this->transactionsTable)->where('id', $transactionId)->update([$updateField => 1]);
        }

        return $attachmentId;
    }

    public function deleteAttachment(int $attachmentId): bool
    {
        return DB::table($this->attachmentsTable)->where('id', $attachmentId)->delete() > 0;
    }

    // =========================================================================
    // STATISTICS & METRICS
    // =========================================================================

    public function getVendorStats(int $vendorId): object
    {
        $stats = DB::table($this->transactionsTable)
            ->where('vendor_id', $vendorId)
            ->select([
                DB::raw('COUNT(*) as total_transactions'),
                DB::raw('SUM(CASE WHEN status = "returned" THEN 1 ELSE 0 END) as completed_transactions'),
                DB::raw('SUM(CASE WHEN status NOT IN ("returned", "cancelled") THEN 1 ELSE 0 END) as active_transactions'),
                DB::raw('SUM(CASE WHEN actual_return_date <= expected_return_date THEN 1 ELSE 0 END) as on_time_count'),
                DB::raw('SUM(actual_cost) as total_cost'),
                DB::raw('AVG(DATEDIFF(actual_return_date, dispatch_date)) as avg_turnaround'),
            ])
            ->first();

        $stats->items_handled = DB::table($this->transactionItemsTable)
            ->join($this->transactionsTable, 'ahg_vendor_transaction_items.transaction_id', '=', 'ahg_vendor_transactions.id')
            ->where('ahg_vendor_transactions.vendor_id', $vendorId)
            ->count();

        return $stats;
    }

    public function getDashboardStats(): array
    {
        $today = date('Y-m-d');

        return [
            'active_vendors' => DB::table($this->vendorsTable)->where('status', 'active')->count(),
            'active_transactions' => DB::table($this->transactionsTable)
                ->whereNotIn('status', ['returned', 'cancelled'])
                ->count(),
            'overdue_items' => DB::table($this->transactionsTable)
                ->where('expected_return_date', '<', $today)
                ->whereNull('actual_return_date')
                ->whereNotIn('status', ['returned', 'cancelled'])
                ->count(),
            'pending_approval' => DB::table($this->transactionsTable)
                ->where('status', 'pending_approval')
                ->count(),
            'items_out' => DB::table($this->transactionItemsTable)
                ->join($this->transactionsTable, 'ahg_vendor_transaction_items.transaction_id', '=', 'ahg_vendor_transactions.id')
                ->whereNotIn('ahg_vendor_transactions.status', ['returned', 'cancelled'])
                ->count(),
            'this_month_cost' => DB::table($this->transactionsTable)
                ->whereYear('request_date', date('Y'))
                ->whereMonth('request_date', date('m'))
                ->sum('actual_cost') ?? 0,
        ];
    }

    public function getTransactionsByStatus(): Collection
    {
        return DB::table($this->transactionsTable)
            ->select([
                'status',
                DB::raw('COUNT(*) as count'),
            ])
            ->groupBy('status')
            ->get();
    }

    public function getMonthlyTransactionCounts(int $months = 12): Collection
    {
        return DB::table($this->transactionsTable)
            ->where('request_date', '>=', date('Y-m-d', strtotime("-{$months} months")))
            ->select([
                DB::raw('DATE_FORMAT(request_date, "%Y-%m") as month'),
                DB::raw('COUNT(*) as transaction_count'),
                DB::raw('SUM(actual_cost) as total_cost'),
            ])
            ->groupBy(DB::raw('DATE_FORMAT(request_date, "%Y-%m")'))
            ->orderBy('month')
            ->get();
    }

    // =========================================================================
    // LOOKUPS
    // =========================================================================

    public function getStatusOptions(): array
    {
        return [
            'pending_approval' => 'Pending Approval',
            'approved' => 'Approved',
            'dispatched' => 'Dispatched',
            'received_by_vendor' => 'Received by Vendor',
            'in_progress' => 'In Progress',
            'completed' => 'Completed',
            'ready_for_collection' => 'Ready for Collection',
            'returned' => 'Returned',
            'cancelled' => 'Cancelled',
        ];
    }

    public function getConditionRatings(): array
    {
        return [
            'excellent' => 'Excellent',
            'good' => 'Good',
            'fair' => 'Fair',
            'poor' => 'Poor',
            'critical' => 'Critical',
        ];
    }

    public function getVendorTypes(): array
    {
        return [
            'company' => 'Company',
            'individual' => 'Individual',
            'institution' => 'Institution',
            'government' => 'Government Agency',
        ];
    }

    public function getPaymentStatuses(): array
    {
        return [
            'not_invoiced' => 'Not Invoiced',
            'invoiced' => 'Invoiced',
            'paid' => 'Paid',
            'disputed' => 'Disputed',
        ];
    }
}
