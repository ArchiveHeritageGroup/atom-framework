<?php
declare(strict_types=1);

namespace App\Contracts;

interface SearchHistoryContract
{
    public function record(array $data): int;
    public function getUserHistory(?int $userId, ?string $sessionId, int $limit = 10): array;
    public function clearUserHistory(?int $userId, ?string $sessionId): bool;
    public function getRecentSearches(int $limit = 10): array;
}
