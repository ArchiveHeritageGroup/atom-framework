
    /**
     * Get global searches (visible to all users)
     */
    public function getGlobalSearches(?string $entityType = null, int $limit = 20): array
    {
        return $this->repository->getGlobal($entityType, $limit);
    }

    /**
     * Set search as global (admin only)
     */
    public function setGlobal(int $id, bool $isGlobal, int $displayOrder = 100): bool
    {
        return $this->repository->update($id, [
            'is_global' => $isGlobal ? 1 : 0,
            'display_order' => $displayOrder,
        ]);
    }
