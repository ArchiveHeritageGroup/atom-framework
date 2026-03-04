<?php

/**
 * Criteria Compatibility Stub.
 *
 * Translates Propel's Criteria API to Laravel Query Builder.
 * Used by locked/stable plugins that contain legacy Propel patterns.
 *
 * Only loaded if the real Propel Criteria class is not available.
 */

use Illuminate\Database\Capsule\Manager as DB;

if (!class_exists('Criteria', false)) {
    class Criteria
    {
        // Comparison constants
        public const EQUAL = '=';
        public const NOT_EQUAL = '!=';
        public const GREATER_THAN = '>';
        public const LESS_THAN = '<';
        public const GREATER_EQUAL = '>=';
        public const LESS_EQUAL = '<=';
        public const LIKE = 'LIKE';
        public const NOT_LIKE = 'NOT LIKE';
        public const ISNOTNULL = 'IS NOT NULL';
        public const ISNULL = 'IS NULL';
        public const IN = 'IN';
        public const NOT_IN = 'NOT IN';
        public const ASC = 'ASC';
        public const DESC = 'DESC';

        // Internal state
        private string $baseTable = '';
        private array $conditions = [];
        private array $joins = [];
        private array $orderBy = [];
        private ?int $limit = null;
        private ?int $offset = null;
        private array $orGroups = [];
        private array $selectColumns = [];

        /**
         * Add a WHERE condition.
         *
         * @param string      $column   Propel column constant (e.g. 'actor_i18n.authorized_form_of_name')
         * @param mixed       $value    Value to compare against
         * @param string      $operator Comparison operator
         *
         * @return self
         */
        public function add($column, $value = null, $operator = self::EQUAL): self
        {
            [$table, $col] = self::resolveColumn($column);

            if ('' === $this->baseTable && $table) {
                $this->baseTable = $table;
            }

            $this->conditions[] = [
                'column' => $table ? "{$table}.{$col}" : $col,
                'value' => $value,
                'operator' => $operator,
            ];

            return $this;
        }

        /**
         * Add a JOIN clause.
         *
         * @param string $leftCol  Left column (e.g. 'actor.id')
         * @param string $rightCol Right column (e.g. 'actor_i18n.id')
         *
         * @return self
         */
        public function addJoin($leftCol, $rightCol): self
        {
            [$leftTable, $leftField] = self::resolveColumn($leftCol);
            [$rightTable, $rightField] = self::resolveColumn($rightCol);

            if ('' === $this->baseTable && $leftTable) {
                $this->baseTable = $leftTable;
            }

            $this->joins[] = [
                'left' => "{$leftTable}.{$leftField}",
                'right' => "{$rightTable}.{$rightField}",
                'rightTable' => $rightTable,
            ];

            return $this;
        }

        /**
         * Add ascending order by column.
         *
         * @param string $column Propel column constant
         *
         * @return self
         */
        public function addAscendingOrderByColumn($column): self
        {
            [$table, $col] = self::resolveColumn($column);

            $this->orderBy[] = [
                'column' => $table ? "{$table}.{$col}" : $col,
                'direction' => 'asc',
            ];

            return $this;
        }

        /**
         * Add descending order by column.
         *
         * @param string $column Propel column constant
         *
         * @return self
         */
        public function addDescendingOrderByColumn($column): self
        {
            [$table, $col] = self::resolveColumn($column);

            $this->orderBy[] = [
                'column' => $table ? "{$table}.{$col}" : $col,
                'direction' => 'desc',
            ];

            return $this;
        }

        /**
         * Set the maximum number of rows to return.
         *
         * @param int $limit
         *
         * @return self
         */
        public function setLimit(int $limit): self
        {
            $this->limit = $limit;

            return $this;
        }

        /**
         * Set the offset.
         *
         * @param int $offset
         *
         * @return self
         */
        public function setOffset(int $offset): self
        {
            $this->offset = $offset;

            return $this;
        }

        /**
         * Get the limit.
         *
         * @return int|null
         */
        public function getLimit(): ?int
        {
            return $this->limit;
        }

        /**
         * Get the offset.
         *
         * @return int|null
         */
        public function getOffset(): ?int
        {
            return $this->offset;
        }

        /**
         * Add select columns (Propel pattern).
         *
         * @param string $column Propel column constant
         *
         * @return self
         */
        public function addSelectColumn($column): self
        {
            [$table, $col] = self::resolveColumn($column);
            $this->selectColumns[] = $table ? "{$table}.{$col}" : $col;

            return $this;
        }

        /**
         * Clear select columns.
         *
         * @return self
         */
        public function clearSelectColumns(): self
        {
            $this->selectColumns = [];

            return $this;
        }

        /**
         * Create a new CriterionStub for OR grouping.
         *
         * @param string $column   Propel column constant
         * @param mixed  $value    Value
         * @param string $operator Comparison operator
         *
         * @return CriterionStub
         */
        public function getNewCriterion($column, $value, $operator = self::EQUAL): CriterionStub
        {
            [$table, $col] = self::resolveColumn($column);

            $criterion = new CriterionStub();
            $criterion->column = $table ? "{$table}.{$col}" : $col;
            $criterion->value = $value;
            $criterion->operator = $operator;

            return $criterion;
        }

        /**
         * Add an OR criterion group.
         *
         * @param CriterionStub $criterion
         *
         * @return self
         */
        public function addOr($criterion): self
        {
            $this->orGroups[] = $criterion;

            return $this;
        }

        /**
         * Add a criterion (from getNewCriterion) to this Criteria.
         * Adds it as a WHERE condition, respecting any OR chain.
         *
         * @param CriterionStub $criterion
         *
         * @return self
         */
        public function addCriterion($criterion): self
        {
            if ($criterion instanceof CriterionStub) {
                $this->orGroups[] = $criterion;
            }

            return $this;
        }

        /**
         * Get the base table name (inferred from conditions/joins).
         *
         * @return string
         */
        public function getBaseTable(): string
        {
            return $this->baseTable;
        }

        /**
         * Build a Laravel Query Builder from accumulated state.
         *
         * The base table is determined by: (1) the left side of the first join,
         * or (2) the first add() column's table if no joins exist.
         *
         * @return \Illuminate\Database\Query\Builder
         */
        public function toQueryBuilder(): \Illuminate\Database\Query\Builder
        {
            // Prefer the left side of the first join as base table
            $base = $this->baseTable;
            if (!empty($this->joins)) {
                $leftParts = explode('.', $this->joins[0]['left'], 2);
                if (count($leftParts) === 2 && $leftParts[0]) {
                    $base = $leftParts[0];
                }
            }

            $query = DB::table($base);

            // Apply joins
            foreach ($this->joins as $join) {
                $query->join($join['rightTable'], $join['left'], '=', $join['right']);
            }

            // Apply WHERE conditions
            foreach ($this->conditions as $cond) {
                $this->applyCondition($query, $cond['column'], $cond['value'], $cond['operator']);
            }

            // Apply OR groups (from getNewCriterion/addOr/addCriterion)
            foreach ($this->orGroups as $criterion) {
                $this->applyOrGroup($query, $criterion);
            }

            // Apply ORDER BY
            foreach ($this->orderBy as $order) {
                $query->orderBy($order['column'], $order['direction']);
            }

            // Apply SELECT columns
            if (!empty($this->selectColumns)) {
                $query->select($this->selectColumns);
            }

            // Apply LIMIT/OFFSET
            if (null !== $this->limit) {
                $query->limit($this->limit);
            }
            if (null !== $this->offset) {
                $query->offset($this->offset);
            }

            return $query;
        }

        /**
         * Apply a single condition to a query builder.
         *
         * @param \Illuminate\Database\Query\Builder $query
         * @param string                             $column
         * @param mixed                              $value
         * @param string                             $operator
         */
        private function applyCondition($query, string $column, $value, string $operator): void
        {
            switch ($operator) {
                case self::ISNULL:
                    $query->whereNull($column);
                    break;

                case self::ISNOTNULL:
                    $query->whereNotNull($column);
                    break;

                case self::IN:
                    $query->whereIn($column, (array) $value);
                    break;

                case self::NOT_IN:
                    $query->whereNotIn($column, (array) $value);
                    break;

                default:
                    $query->where($column, $operator, $value);
                    break;
            }
        }

        /**
         * Apply an OR criterion group (CriterionStub with optional orCriteria chain).
         *
         * @param \Illuminate\Database\Query\Builder $query
         * @param CriterionStub                      $criterion
         */
        private function applyOrGroup($query, CriterionStub $criterion): void
        {
            if (empty($criterion->orCriteria)) {
                // Single condition, no OR chain
                $this->applyCondition($query, $criterion->column, $criterion->value, $criterion->operator);

                return;
            }

            // Group: (condition1 OR condition2 OR ...)
            $query->where(function ($q) use ($criterion) {
                $this->applyCondition($q, $criterion->column, $criterion->value, $criterion->operator);

                foreach ($criterion->orCriteria as $orCrit) {
                    $q->orWhere(function ($sq) use ($orCrit) {
                        $this->applyCondition($sq, $orCrit->column, $orCrit->value, $orCrit->operator);
                    });
                }
            });
        }

        /**
         * Resolve a Propel column constant (e.g. 'actor_i18n.authorized_form_of_name')
         * into [table, column].
         *
         * @param string $propelColumn
         *
         * @return array [table, column]
         */
        public static function resolveColumn(string $propelColumn): array
        {
            // Propel constants are 'table_name.COLUMN_NAME' (uppercase in base AtoM)
            // or 'table_name.column_name' (lowercase in our stubs)
            if (str_contains($propelColumn, '.')) {
                $parts = explode('.', $propelColumn, 2);

                return [$parts[0], strtolower($parts[1])];
            }

            return ['', strtolower($propelColumn)];
        }
    }

    /**
     * Stub for Propel's Criterion object, used in OR grouping.
     */
    class CriterionStub
    {
        public string $column = '';
        public $value;
        public string $operator = Criteria::EQUAL;
        public array $orCriteria = [];

        /**
         * Chain an OR condition to this criterion.
         *
         * @param CriterionStub $criterion
         *
         * @return self
         */
        public function addOr($criterion): self
        {
            if ($criterion instanceof self) {
                $this->orCriteria[] = $criterion;
            }

            return $this;
        }
    }
}
