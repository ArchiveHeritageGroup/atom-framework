<?php

namespace AtomFramework\Services;

use AtomFramework\Repositories\VendorRepository;
use Illuminate\Support\Collection;

class VendorService
{
    protected VendorRepository $repository;

    public function __construct()
    {
        $this->repository = new VendorRepository();
    }

    // =========================================================================
    // VENDOR OPERATIONS
    // =========================================================================

    public function listVendors(array $filters = []): Collection
    {
        return $this->repository->getAllVendors($filters);
    }

    public function getVendor(int $id): ?object
    {
        return $this->repository->getVendorById($id);
    }

    public function getVendorBySlug(string $slug): ?object
    {
        return $this->repository->getVendorBySlug($slug);
    }

    public function createVendor(array $data, int $userId): array
    {
        $errors = $this->validateVendor($data);

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $data['created_by'] = $userId;

        try {
            $vendorId = $this->repository->createVendor($data);

            return ['success' => true, 'vendor_id' => $vendorId];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => ['general' => $e->getMessage()]];
        }
    }

    public function updateVendor(int $id, array $data): array
    {
        $errors = $this->validateVendor($data, $id);

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        try {
            $this->repository->updateVendor($id, $data);

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => ['general' => $e->getMessage()]];
        }
    }

    public function deleteVendor(int $id): array
    {
        $deleted = $this->repository->deleteVendor($id);

        if (!$deleted) {
            return [
                'success' => false,
                'errors' => ['general' => 'Cannot delete vendor with active transactions'],
            ];
        }

        return ['success' => true];
    }

    protected function validateVendor(array $data, ?int $excludeId = null): array
    {
        $errors = [];

        if (empty($data['name'])) {
            $errors['name'] = 'Vendor name is required';
        }

        if (!empty($data['email']) && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Invalid email address';
        }

        return $errors;
    }

    // =========================================================================
    // CONTACT OPERATIONS
    // =========================================================================

    public function getVendorContacts(int $vendorId): Collection
    {
        return $this->repository->getVendorContacts($vendorId);
    }

    public function addContact(int $vendorId, array $data): array
    {
        if (empty($data['name'])) {
            return ['success' => false, 'errors' => ['name' => 'Contact name is required']];
        }

        try {
            $contactId = $this->repository->addContact($vendorId, $data);

            return ['success' => true, 'contact_id' => $contactId];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => ['general' => $e->getMessage()]];
        }
    }

    public function updateContact(int $contactId, array $data): array
    {
        try {
            $this->repository->updateContact($contactId, $data);

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => ['general' => $e->getMessage()]];
        }
    }

    public function deleteContact(int $contactId): array
    {
        $this->repository->deleteContact($contactId);

        return ['success' => true];
    }

    // =========================================================================
    // SERVICE TYPE OPERATIONS
    // =========================================================================

    public function listServiceTypes(bool $activeOnly = true): Collection
    {
        return $this->repository->getAllServiceTypes($activeOnly);
    }

    public function getServiceType(int $id): ?object
    {
        return $this->repository->getServiceTypeById($id);
    }

    public function getVendorServices(int $vendorId): Collection
    {
        return $this->repository->getVendorServices($vendorId);
    }

    public function getVendorsForService(int $serviceTypeId): Collection
    {
        return $this->repository->getVendorsForService($serviceTypeId);
    }

    public function assignService(int $vendorId, int $serviceTypeId, array $data = []): array
    {
        try {
            $this->repository->assignServiceToVendor($vendorId, $serviceTypeId, $data);

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => ['general' => $e->getMessage()]];
        }
    }

    public function removeService(int $vendorId, int $serviceTypeId): array
    {
        $this->repository->removeServiceFromVendor($vendorId, $serviceTypeId);

        return ['success' => true];
    }

    // =========================================================================
    // TRANSACTION OPERATIONS
    // =========================================================================

    public function listTransactions(array $filters = []): Collection
    {
        return $this->repository->getAllTransactions($filters);
    }

    public function getActiveTransactions(): Collection
    {
        return $this->repository->getActiveTransactions();
    }

    public function getOverdueTransactions(): Collection
    {
        return $this->repository->getOverdueTransactions();
    }

    public function getTransaction(int $id): ?object
    {
        return $this->repository->getTransactionById($id);
    }

    public function getTransactionByNumber(string $number): ?object
    {
        return $this->repository->getTransactionByNumber($number);
    }

    public function createTransaction(array $data, int $userId): array
    {
        $errors = $this->validateTransaction($data);

        if (!empty($errors)) {
            return ['success' => false, 'errors' => $errors];
        }

        $data['requested_by'] = $userId;
        $data['status'] = $data['status'] ?? 'pending_approval';

        try {
            $transactionId = $this->repository->createTransaction($data);

            return ['success' => true, 'transaction_id' => $transactionId];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => ['general' => $e->getMessage()]];
        }
    }

    public function updateTransaction(int $id, array $data, int $userId): array
    {
        try {
            $this->repository->updateTransaction($id, $data, $userId);

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => ['general' => $e->getMessage()]];
        }
    }

    public function updateTransactionStatus(int $id, string $status, int $userId, ?string $notes = null): array
    {
        $validStatuses = array_keys($this->repository->getStatusOptions());

        if (!in_array($status, $validStatuses)) {
            return ['success' => false, 'errors' => ['status' => 'Invalid status']];
        }

        try {
            $this->repository->updateTransactionStatus($id, $status, $userId, $notes);

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => ['general' => $e->getMessage()]];
        }
    }

    public function approveTransaction(int $id, int $userId, ?string $notes = null): array
    {
        return $this->updateTransactionStatus($id, 'approved', $userId, $notes);
    }

    public function dispatchTransaction(int $id, int $userId, ?string $notes = null): array
    {
        return $this->updateTransactionStatus($id, 'dispatched', $userId, $notes);
    }

    public function returnTransaction(int $id, int $userId, ?string $notes = null): array
    {
        return $this->updateTransactionStatus($id, 'returned', $userId, $notes);
    }

    public function cancelTransaction(int $id, int $userId, ?string $notes = null): array
    {
        return $this->updateTransactionStatus($id, 'cancelled', $userId, $notes);
    }

    protected function validateTransaction(array $data): array
    {
        $errors = [];

        if (empty($data['vendor_id'])) {
            $errors['vendor_id'] = 'Vendor is required';
        }

        if (empty($data['service_type_id'])) {
            $errors['service_type_id'] = 'Service type is required';
        }

        if (empty($data['request_date'])) {
            $errors['request_date'] = 'Request date is required';
        }

        return $errors;
    }

    public function getTransactionHistory(int $transactionId): Collection
    {
        return $this->repository->getTransactionHistory($transactionId);
    }

    // =========================================================================
    // TRANSACTION ITEMS
    // =========================================================================

    public function getTransactionItems(int $transactionId): Collection
    {
        return $this->repository->getTransactionItems($transactionId);
    }

    public function addItemToTransaction(int $transactionId, int $informationObjectId, array $data = []): array
    {
        try {
            $itemId = $this->repository->addTransactionItem($transactionId, $informationObjectId, $data);

            return ['success' => true, 'item_id' => $itemId];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => ['general' => $e->getMessage()]];
        }
    }

    public function updateTransactionItem(int $itemId, array $data): array
    {
        try {
            $this->repository->updateTransactionItem($itemId, $data);

            return ['success' => true];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => ['general' => $e->getMessage()]];
        }
    }

    public function removeItemFromTransaction(int $itemId): array
    {
        $this->repository->removeTransactionItem($itemId);

        return ['success' => true];
    }

    public function getItemVendorHistory(int $informationObjectId): Collection
    {
        return $this->repository->getItemTransactionHistory($informationObjectId);
    }

    // =========================================================================
    // ATTACHMENTS
    // =========================================================================

    public function getAttachments(int $transactionId, ?string $type = null): Collection
    {
        return $this->repository->getTransactionAttachments($transactionId, $type);
    }

    public function addAttachment(int $transactionId, array $data): array
    {
        try {
            $attachmentId = $this->repository->addAttachment($transactionId, $data);

            return ['success' => true, 'attachment_id' => $attachmentId];
        } catch (\Exception $e) {
            return ['success' => false, 'errors' => ['general' => $e->getMessage()]];
        }
    }

    public function deleteAttachment(int $attachmentId): array
    {
        $this->repository->deleteAttachment($attachmentId);

        return ['success' => true];
    }

    // =========================================================================
    // STATISTICS & DASHBOARD
    // =========================================================================

    public function getDashboardStats(): array
    {
        return $this->repository->getDashboardStats();
    }

    public function getVendorStats(int $vendorId): object
    {
        return $this->repository->getVendorStats($vendorId);
    }

    public function getTransactionsByStatus(): Collection
    {
        return $this->repository->getTransactionsByStatus();
    }

    public function getMonthlyStats(int $months = 12): Collection
    {
        return $this->repository->getMonthlyTransactionCounts($months);
    }

    // =========================================================================
    // LOOKUPS
    // =========================================================================

    public function getStatusOptions(): array
    {
        return $this->repository->getStatusOptions();
    }

    public function getConditionRatings(): array
    {
        return $this->repository->getConditionRatings();
    }

    public function getVendorTypes(): array
    {
        return $this->repository->getVendorTypes();
    }

    public function getPaymentStatuses(): array
    {
        return $this->repository->getPaymentStatuses();
    }
}
