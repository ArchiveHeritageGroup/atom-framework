<?php

declare(strict_types=1);

namespace AtomFramework\Services\Pagination;

/**
 * Framework-level SimplePager - replaces per-plugin SimplePager implementations.
 *
 * Compatible with QubitPager interface used by ahgThemeB5Plugin/_pager.php template.
 * All methods match the interface expected by the template partial:
 *   haveToPaginate(), getNbResults(), getFirstIndice(), getLastIndice(),
 *   getPage(), getLastPage(), getLinks(), getResults(), etc.
 *
 * Results are arrays (not objects) - templates access via $doc['slug'].
 */
class SimplePager
{
    protected int $page;

    protected int $maxPerPage;

    protected int $nbResults;

    protected int $lastPage;

    protected array $results;

    protected int $currentMaxLink = 1;

    /**
     * @param array $results  Pre-fetched results for the current page
     * @param int   $total    Total number of results across all pages
     * @param int   $page     Current page number (1-based)
     * @param int   $maxPerPage Items per page
     */
    public function __construct(array $results, int $total, int $page = 1, int $maxPerPage = 30)
    {
        $this->results = $results;
        $this->nbResults = max(0, $total);
        $this->maxPerPage = max(1, $maxPerPage);
        $this->lastPage = max(1, (int) ceil($this->nbResults / $this->maxPerPage));
        $this->page = max(1, min($page, $this->lastPage));
    }

    /**
     * Get the results for the current page.
     */
    public function getResults(): array
    {
        return $this->results;
    }

    /**
     * Get the total number of results across all pages.
     */
    public function getNbResults(): int
    {
        return $this->nbResults;
    }

    /**
     * Get the current page number (1-based).
     */
    public function getPage(): int
    {
        return $this->page;
    }

    /**
     * Set the current page number.
     */
    public function setPage(int $page): void
    {
        $this->page = max(1, min($page, $this->lastPage));
    }

    /**
     * Get the number of items per page.
     */
    public function getMaxPerPage(): int
    {
        return $this->maxPerPage;
    }

    /**
     * Set the number of items per page.
     */
    public function setMaxPerPage(int $max): void
    {
        $this->maxPerPage = max(1, $max);
        $this->lastPage = max(1, (int) ceil($this->nbResults / $this->maxPerPage));
        $this->page = max(1, min($this->page, $this->lastPage));
    }

    /**
     * Get the first page number (always 1).
     */
    public function getFirstPage(): int
    {
        return 1;
    }

    /**
     * Get the last page number.
     */
    public function getLastPage(): int
    {
        return $this->lastPage;
    }

    /**
     * Get the 1-based index of the first item on the current page.
     */
    public function getFirstIndice(): int
    {
        if (0 === $this->page) {
            return 1;
        }

        return ($this->page - 1) * $this->maxPerPage + 1;
    }

    /**
     * Get the 1-based index of the last item on the current page.
     */
    public function getLastIndice(): int
    {
        if ($this->page * $this->maxPerPage >= $this->nbResults) {
            return $this->nbResults;
        }

        return $this->page * $this->maxPerPage;
    }

    /**
     * Get the next page number.
     */
    public function getNextPage(): int
    {
        return min($this->page + 1, $this->lastPage);
    }

    /**
     * Get the previous page number.
     */
    public function getPreviousPage(): int
    {
        return max($this->page - 1, 1);
    }

    /**
     * Check if this is the first page.
     */
    public function isFirstPage(): bool
    {
        return 1 === $this->page;
    }

    /**
     * Check if this is the last page.
     */
    public function isLastPage(): bool
    {
        return $this->page === $this->lastPage;
    }

    /**
     * Whether pagination is needed (total exceeds items per page).
     */
    public function haveToPaginate(): bool
    {
        return $this->maxPerPage > 0 && $this->nbResults > $this->maxPerPage;
    }

    /**
     * Get an array of page numbers centered on the current page.
     *
     * Used by _pager.php to render page links. The algorithm matches
     * the sfPager/QubitPager implementation exactly.
     *
     * @param int $nbLinks Maximum number of page links to show
     *
     * @return array Array of page numbers (e.g., [3, 4, 5, 6, 7])
     */
    public function getLinks(int $nbLinks = 5): array
    {
        $links = [];
        $tmp = $this->page - (int) floor($nbLinks / 2);
        $check = $this->lastPage - $nbLinks + 1;
        $limit = $check > 0 ? $check : 1;
        $begin = $tmp > 0 ? ($tmp > $limit ? $limit : $tmp) : 1;

        $i = (int) $begin;
        while ($i < $begin + $nbLinks && $i <= $this->lastPage) {
            $links[] = $i++;
        }

        $this->currentMaxLink = count($links) ? $links[count($links) - 1] : 1;

        return $links;
    }
}
