<?php

namespace AtomFramework\Services\Write;

/**
 * Contract for rights holder write operations.
 *
 * RightsHolder extends Actor in AtoM's entity hierarchy:
 *   object -> actor -> rights_holder
 *
 * The Propel adapter wraps QubitRightsHolder.
 * The standalone adapter uses Laravel Query Builder.
 */
interface RightsHolderWriteServiceInterface
{
    /**
     * Create a new rights holder. Returns the new ID.
     *
     * Handles the entity inheritance:
     *   INSERT object -> INSERT actor -> INSERT rights_holder
     *
     * @param array  $data    Rights holder data (authorized_form_of_name, etc.)
     * @param string $culture Culture code (e.g., 'en')
     *
     * @return int The new rights_holder.id
     */
    public function createRightsHolder(array $data, string $culture = 'en'): int;

    /**
     * Update an existing rights holder.
     *
     * @param int    $id      Rights holder ID
     * @param array  $data    Column => value pairs to update
     * @param string $culture Culture code for i18n attributes
     */
    public function updateRightsHolder(int $id, array $data, string $culture = 'en'): void;

    /**
     * Delete a rights holder.
     *
     * @param int $id Rights holder ID
     */
    public function deleteRightsHolder(int $id): void;

    /**
     * Create a new, empty rights holder object for form binding.
     *
     * In Propel mode, returns a new QubitRightsHolder instance.
     * In standalone mode, returns a stdClass with expected properties.
     *
     * @return object
     */
    public function newRightsHolder(): object;
}
