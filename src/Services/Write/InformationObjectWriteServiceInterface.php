<?php

namespace AtomFramework\Services\Write;

/**
 * Contract for information object write operations.
 *
 * Covers: creating new information objects, updating, and saving Propel objects.
 *
 * The PropelAdapter wraps QubitInformationObject for Symfony mode.
 * Falls back to Laravel DB for standalone Heratio mode, handling
 * the AtoM entity inheritance chain:
 *   object -> information_object -> information_object_i18n
 */
interface InformationObjectWriteServiceInterface
{
    /**
     * Create a new, empty information object instance.
     *
     * In Propel mode, returns new QubitInformationObject().
     * In standalone mode, returns a stdClass with matching properties.
     *
     * @return object The new information object instance
     */
    public function newInformationObject(): object;

    /**
     * Create an information object with the given data. Returns the new ID.
     *
     * Handles the AtoM entity inheritance:
     *   INSERT object -> INSERT information_object -> INSERT information_object_i18n.
     *
     * @param array  $data    Column => value pairs
     * @param string $culture Culture code (e.g., 'en')
     *
     * @return int The new information_object.id
     */
    public function createInformationObject(array $data, string $culture = 'en'): int;

    /**
     * Update an existing information object.
     *
     * @param int    $id      Information object ID
     * @param array  $data    Column => value pairs to update
     * @param string $culture Culture code for i18n attributes
     */
    public function updateInformationObject(int $id, array $data, string $culture = 'en'): void;

    /**
     * Save an information object with properties already set (Propel mode).
     *
     * In Propel mode, calls $io->save().
     * In standalone mode, extracts properties and does DB inserts.
     *
     * @param object $informationObject The IO instance (QubitInformationObject or stdClass)
     *
     * @return int The information object ID
     */
    public function saveInformationObject(object $informationObject): int;
}
