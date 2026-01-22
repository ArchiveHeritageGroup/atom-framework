<?php

declare(strict_types=1);

namespace AtomExtensions\Reports\Results;

use Illuminate\Support\Collection;

/**
 * Authority Record Report Result.
 * Clean data object replacing QubitPager.
 *
 * @author Johan Pieterse <pieterse.johan3@gmail.com>
 */
final class AuthorityRecordReportResult
{
    public function __construct(
        private readonly Collection $items,
        private readonly int $total,
        private readonly int $perPage,
        private readonly int $currentPage,
        private readonly string $sort,
        private readonly string $dateOf,
        private readonly ?string $dateStart,
        private readonly ?string $dateEnd
    ) {
    }

    /**
     * Get result items.
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    /**
     * Get total count.
     */
    public function getTotal(): int
    {
        return $this->total;
    }

    /**
     * Get items per page.
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }

    /**
     * Get current page number.
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * Get last page number.
     */
    public function getLastPage(): int
    {
        return (int) ceil($this->total / $this->perPage);
    }

    /**
     * Check if there's a next page.
     */
    public function hasNextPage(): bool
    {
        return $this->currentPage < $this->getLastPage();
    }

    /**
     * Check if there's a previous page.
     */
    public function hasPreviousPage(): bool
    {
        return $this->currentPage > 1;
    }

    /**
     * Get next page number.
     */
    public function getNextPage(): ?int
    {
        return $this->hasNextPage() ? $this->currentPage + 1 : null;
    }

    /**
     * Get previous page number.
     */
    public function getPreviousPage(): ?int
    {
        return $this->hasPreviousPage() ? $this->currentPage - 1 : null;
    }

    /**
     * Get sort parameter.
     */
    public function getSort(): string
    {
        return $this->sort;
    }

    /**
     * Get date field being filtered.
     */
    public function getDateOf(): string
    {
        return $this->dateOf;
    }

    /**
     * Get start date.
     */
    public function getDateStart(): ?string
    {
        return $this->dateStart;
    }

    /**
     * Get end date.
     */
    public function getDateEnd(): ?string
    {
        return $this->dateEnd;
    }

    /**
     * Convert to array for template.
     */
    public function toArray(): array
    {
        return [
            'items' => $this->items->all(),
            'total' => $this->total,
            'per_page' => $this->perPage,
            'current_page' => $this->currentPage,
            'last_page' => $this->getLastPage(),
            'has_next' => $this->hasNextPage(),
            'has_previous' => $this->hasPreviousPage(),
            'next_page' => $this->getNextPage(),
            'previous_page' => $this->getPreviousPage(),
            'sort' => $this->sort,
            'date_of' => $this->dateOf,
            'date_start' => $this->dateStart,
            'date_end' => $this->dateEnd,
        ];
    }
}
