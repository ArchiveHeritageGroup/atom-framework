<?php

declare(strict_types=1);

namespace AtomFramework\Services\Donor;

use AtomFramework\Repositories\Donor\DonorRepository;

class DonorService
{
    protected DonorRepository $repository;

    public function __construct(?DonorRepository $repository = null)
    {
        $this->repository = $repository ?? new DonorRepository();
    }

    /**
     * Get paginated donor list
     */
    public function browse(array $filters = [], int $page = 1, int $perPage = 25): array
    {
        return $this->repository->browse($filters, $page, $perPage);
    }

    /**
     * Get donor by ID
     */
    public function getDonor(int $id): ?object
    {
        return $this->repository->find($id);
    }

    /**
     * Get entity types
     */
    public function getEntityTypes(): array
    {
        return $this->repository->getEntityTypes()->toArray();
    }

    /**
     * Search donors
     */
    public function autocomplete(string $term): array
    {
        return $this->repository->autocomplete($term)->toArray();
    }
}
