<?php

namespace AtomFramework\Core\Database;

use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Builder as SchemaBuilder;

/**
 * Database Manager for Framework v2.
 *
 * Provides a clean interface to Laravel's database components.
 */
class DatabaseManager
{
    private Capsule $capsule;

    public function __construct(Capsule $capsule)
    {
        $this->capsule = $capsule;
    }

    /**
     * Get a query builder for a table.
     *
     * @param string $table Table name
     *
     * @return Builder
     */
    public function table(string $table): Builder
    {
        return $this->capsule->table($table);
    }

    /**
     * Get the schema builder.
     *
     * @return SchemaBuilder
     */
    public function getSchemaBuilder(): SchemaBuilder
    {
        return $this->capsule->schema();
    }

    /**
     * Get the underlying connection.
     *
     * @return \Illuminate\Database\Connection
     */
    public function getConnection()
    {
        return $this->capsule->getConnection();
    }

    /**
     * Begin a database transaction.
     *
     * @return void
     */
    public function beginTransaction(): void
    {
        $this->capsule->getConnection()->beginTransaction();
    }

    /**
     * Commit the active database transaction.
     *
     * @return void
     */
    public function commit(): void
    {
        $this->capsule->getConnection()->commit();
    }

    /**
     * Rollback the active database transaction.
     *
     * @return void
     */
    public function rollback(): void
    {
        $this->capsule->getConnection()->rollBack();
    }

    /**
     * Execute a raw SQL query.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return bool
     */
    public function statement(string $query, array $bindings = []): bool
    {
        return $this->capsule->getConnection()->statement($query, $bindings);
    }

    /**
     * Run a select query and return results.
     *
     * @param string $query
     * @param array  $bindings
     *
     * @return array
     */
    public function select(string $query, array $bindings = []): array
    {
        return $this->capsule->getConnection()->select($query, $bindings);
    }
}
