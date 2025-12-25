<?php
declare(strict_types=1);

namespace App\Contracts;

interface SavedSearchContract
{
    public function findById(int $id): ?object;
    public function findByToken(string $token): ?object;
    public function getUserSearches(int $userId, int $limit = 25): array;
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
    public function incrementRunCount(int $id): void;
    public function getSearchesForNotification(string $frequency): array;
}
