<?php


namespace AtomFramework\Museum\Contracts;

interface MaterialTaxonomyInterface
{
    /**
     * Get all materials in the taxonomy.
     *
     * @return array Array of material terms
     */
    public function getAllMaterials(): array;

    /**
     * Get materials by category.
     *
     * @param string $category The material category (metal, textile, etc.)
     *
     * @return array Array of material terms in that category
     */
    public function getMaterialsByCategory(string $category): array;

    /**
     * Validate if a material exists in the taxonomy.
     *
     * @param string $material The material term to validate
     *
     * @return bool True if material exists
     */
    public function isValidMaterial(string $material): bool;

    /**
     * Get all techniques in the taxonomy.
     *
     * @return array Array of technique terms
     */
    public function getAllTechniques(): array;

    /**
     * Validate if a technique exists in the taxonomy.
     *
     * @param string $technique The technique term to validate
     *
     * @return bool True if technique exists
     */
    public function isValidTechnique(string $technique): bool;
}
