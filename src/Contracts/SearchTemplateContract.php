<?php
declare(strict_types=1);

namespace AtomFramework\Contracts;

interface SearchTemplateContract
{
    public function findById(int $id): ?object;
    public function findBySlug(string $slug): ?object;
    public function getAll(bool $activeOnly = true): array;
    public function getFeatured(): array;
    public function getByCategory(?string $category = null): array;
    public function create(array $data): int;
    public function update(int $id, array $data): bool;
    public function delete(int $id): bool;
}
