<?php

/**
 * QubitPager Compatibility Stub.
 *
 * Reimplements sfPropelPager pagination using Criteria -> Laravel Query Builder.
 * Used by locked/stable plugins that contain legacy Propel pagination patterns.
 *
 * Plugin code typically checks: if (class_exists('QubitPager')) { ... }
 *
 * Only loaded if the real QubitPager class is not available.
 */

use Illuminate\Database\Capsule\Manager as DB;

if (!class_exists('QubitPager', false)) {
    class QubitPager implements \Countable, \IteratorAggregate
    {
        private string $class;
        private ?Criteria $criteria = null;
        private int $maxPerPage = 25;
        private int $page = 1;
        private ?int $nbResults = null;
        private ?array $results = null;

        /**
         * @param string $class The Qubit model class name (e.g. 'QubitPhysicalObject')
         */
        public function __construct(string $class)
        {
            $this->class = $class;
        }

        /**
         * Set the Criteria for this pager.
         *
         * @param Criteria $criteria
         */
        public function setCriteria(Criteria $criteria): void
        {
            $this->criteria = $criteria;
            $this->nbResults = null;
            $this->results = null;
        }

        /**
         * Get the Criteria.
         *
         * @return Criteria|null
         */
        public function getCriteria(): ?Criteria
        {
            return $this->criteria;
        }

        /**
         * Set max results per page.
         *
         * @param int $max
         */
        public function setMaxPerPage(int $max): void
        {
            $this->maxPerPage = max(1, $max);
            $this->results = null;
        }

        /**
         * Get max results per page.
         *
         * @return int
         */
        public function getMaxPerPage(): int
        {
            return $this->maxPerPage;
        }

        /**
         * Set current page number (1-indexed).
         *
         * @param int $page
         */
        public function setPage(int $page): void
        {
            $this->page = max(1, $page);
            $this->results = null;
        }

        /**
         * Get current page number.
         *
         * @return int
         */
        public function getPage(): int
        {
            return $this->page;
        }

        /**
         * Initialize the pager (run count query).
         *
         * Propel pagers require init() before use. Here we run the
         * count query to determine total results.
         */
        public function init(): void
        {
            $this->nbResults = null;
            $this->results = null;

            if (null === $this->criteria) {
                $this->nbResults = 0;

                return;
            }

            $query = $this->criteria->toQueryBuilder();
            $this->nbResults = $query->count();

            // Clamp page to valid range
            $maxPage = $this->getNbPages();
            if ($this->page > $maxPage && $maxPage > 0) {
                $this->page = $maxPage;
            }
        }

        /**
         * Get total number of results.
         *
         * @return int
         */
        public function getNbResults(): int
        {
            if (null === $this->nbResults) {
                $this->init();
            }

            return $this->nbResults;
        }

        /**
         * Get total number of pages.
         *
         * @return int
         */
        public function getNbPages(): int
        {
            if (0 === $this->getNbResults()) {
                return 1;
            }

            return (int) ceil($this->getNbResults() / $this->maxPerPage);
        }

        /**
         * Check if pagination is needed.
         *
         * @return bool
         */
        public function haveToPaginate(): bool
        {
            return $this->getNbResults() > $this->maxPerPage;
        }

        /**
         * Get paginated results, hydrated as model instances.
         *
         * @return array
         */
        public function getResults(): array
        {
            if (null !== $this->results) {
                return $this->results;
            }

            if (null === $this->criteria) {
                $this->results = [];

                return $this->results;
            }

            if (null === $this->nbResults) {
                $this->init();
            }

            $query = $this->criteria->toQueryBuilder();

            // Apply pagination
            $offset = ($this->page - 1) * $this->maxPerPage;
            $query->offset($offset)->limit($this->maxPerPage);

            $rows = $query->get();
            $this->results = [];

            $class = $this->class;
            foreach ($rows as $row) {
                if (method_exists($class, 'hydrate')) {
                    $this->results[] = $class::hydrate($row);
                } else {
                    $this->results[] = $row;
                }
            }

            return $this->results;
        }

        /**
         * Get first page number.
         *
         * @return int
         */
        public function getFirstPage(): int
        {
            return 1;
        }

        /**
         * Get last page number.
         *
         * @return int
         */
        public function getLastPage(): int
        {
            return $this->getNbPages();
        }

        /**
         * Get next page number.
         *
         * @return int
         */
        public function getNextPage(): int
        {
            return min($this->page + 1, $this->getNbPages());
        }

        /**
         * Get previous page number.
         *
         * @return int
         */
        public function getPreviousPage(): int
        {
            return max($this->page - 1, 1);
        }

        /**
         * Get the first indice (1-indexed position of first item on page).
         *
         * @return int
         */
        public function getFirstIndice(): int
        {
            if (0 === $this->getNbResults()) {
                return 0;
            }

            return ($this->page - 1) * $this->maxPerPage + 1;
        }

        /**
         * Get the last indice (1-indexed position of last item on page).
         *
         * @return int
         */
        public function getLastIndice(): int
        {
            if (0 === $this->getNbResults()) {
                return 0;
            }

            return min($this->page * $this->maxPerPage, $this->getNbResults());
        }

        /**
         * Countable interface — returns total number of results.
         *
         * @return int
         */
        public function count(): int
        {
            return $this->getNbResults();
        }

        /**
         * IteratorAggregate interface — iterate over current page results.
         *
         * @return \ArrayIterator
         */
        public function getIterator(): \ArrayIterator
        {
            return new \ArrayIterator($this->getResults());
        }

        /**
         * Check if we're on the first page.
         *
         * @return bool
         */
        public function isFirstPage(): bool
        {
            return 1 === $this->page;
        }

        /**
         * Check if we're on the last page.
         *
         * @return bool
         */
        public function isLastPage(): bool
        {
            return $this->page === $this->getNbPages();
        }

        /**
         * Get page links for pagination UI.
         * Returns an array of page numbers to display.
         *
         * @param int $nbLinks Number of page links to show
         *
         * @return array
         */
        public function getLinks(int $nbLinks = 5): array
        {
            $nbPages = $this->getNbPages();

            if ($nbPages <= $nbLinks) {
                return range(1, $nbPages);
            }

            $half = (int) floor($nbLinks / 2);
            $start = max(1, $this->page - $half);
            $end = min($nbPages, $start + $nbLinks - 1);

            // Adjust start if end is capped
            if ($end - $start + 1 < $nbLinks) {
                $start = max(1, $end - $nbLinks + 1);
            }

            return range($start, $end);
        }
    }
}
