<?php

namespace App\Contracts\Rights;

use Illuminate\Support\Collection;

interface EmbargoServiceInterface
{
    public function getEmbargo(int $embargoId): ?array;
    public function getObjectEmbargoes(int $objectId): Collection;
    public function getActiveEmbargoes(): Collection;
    public function getExpiringEmbargoes(int $days = 30): Collection;
    public function createEmbargo(int $objectId, array $data): array;
    public function updateEmbargo(int $embargoId, array $data): array;
    public function liftEmbargo(int $embargoId, ?string $reason = null): bool;
    public function addException(int $embargoId, array $data): array;
    public function removeException(int $exceptionId): bool;
    public function checkAccess(int $objectId, ?int $userId = null, ?string $ipAddress = null): bool;
    public function processExpiredEmbargoes(): int;
}
