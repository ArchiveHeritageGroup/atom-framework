<?php

declare(strict_types=1);

namespace AtomExtensions\Database;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;

/**
 * Base Repository providing Laravel Query Builder interface.
 * Replaces hardcoded SQL and Propel Criteria with modern, fluent queries.
 *
 * @author Johan Pieterse <pieterse.johan3@gmail.com>
 */
abstract class Repository
{
    protected string $table;

    protected string $primaryKey = 'id';

    /**
     * Get a fresh query builder instance.
     */
    protected function query(): Builder
    {
        return DB::table($this->table);
    }

    /**
     * Find a single record by ID.
     */
    public function find(int $id): ?array
    {
        $result = $this->query()
            ->where($this->primaryKey, $id)
            ->first();

        return $result ? (array) $result : null;
    }

    /**
     * Find all records matching criteria.
     *
     * @param  array<string, mixed>  $criteria
     */
    public function findWhere(array $criteria): Collection
    {
        $query = $this->query();

        foreach ($criteria as $field => $value) {
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }

        return collect($query->get())->map(fn ($item) => (array) $item);
    }

    /**
     * Get all records from the table.
     */
    public function all(): Collection
    {
        return collect($this->query()->get())->map(fn ($item) => (array) $item);
    }

    /**
     * Create a new record and return its ID.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): int
    {
        return $this->query()->insertGetId($data);
    }

    /**
     * Update a record by ID.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(int $id, array $data): bool
    {
        return $this->query()
            ->where($this->primaryKey, $id)
            ->update($data) > 0;
    }

    /**
     * Delete a record by ID.
     */
    public function delete(int $id): bool
    {
        return $this->query()
            ->where($this->primaryKey, $id)
            ->delete() > 0;
    }

    /**
     * Count records matching criteria.
     *
     * @param  array<string, mixed>  $criteria
     */
    public function count(array $criteria = []): int
    {
        $query = $this->query();

        foreach ($criteria as $field => $value) {
            $query->where($field, $value);
        }

        return $query->count();
    }

    /**
     * Check if a record exists.
     *
     * @param  array<string, mixed>  $criteria
     */
    public function exists(array $criteria): bool
    {
        return $this->count($criteria) > 0;
    }

    /**
     * Find records with pagination support.
     *
     * @param  array<string, mixed>  $criteria
     */
    public function paginate(array $criteria = [], int $perPage = 15, int $page = 1): array
    {
        $query = $this->query();

        foreach ($criteria as $field => $value) {
            if (is_array($value)) {
                $query->whereIn($field, $value);
            } else {
                $query->where($field, $value);
            }
        }

        $total = $query->count();
        $items = collect($query
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get())
            ->map(fn ($item) => (array) $item);

        return [
            'items' => $items,
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => (int) ceil($total / $perPage),
        ];
    }

    /**
     * Get SQL query string for debugging.
     */
    public function toSql(Builder $query): string
    {
        return $query->toSql();
    }

    /**
     * Get bindings for debugging.
     *
     * @return array<int, mixed>
     */
    public function getBindings(Builder $query): array
    {
        return $query->getBindings();
    }
}
