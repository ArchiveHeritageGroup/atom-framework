<?php

namespace AtomFramework\Services\Write;

/**
 * Contract for PhysicalObject write operations.
 *
 * Covers: creating new physical objects, updating existing ones,
 * and saving Propel-bound resources.
 *
 * The PropelAdapter wraps QubitPhysicalObject for Symfony mode.
 * Falls back to Laravel Query Builder for standalone Heratio mode.
 */
interface PhysicalObjectWriteServiceInterface
{
    /**
     * Get a new unsaved PhysicalObject (for form binding in Propel mode).
     * In standalone mode, returns stdClass.
     *
     * @return object Unsaved QubitPhysicalObject or stdClass
     */
    public function newPhysicalObject(): object;

    /**
     * Create a new physical object. Returns the new object ID.
     *
     * Handles entity inheritance:
     * INSERT object -> INSERT physical_object -> INSERT physical_object_i18n.
     *
     * @param array  $data    Physical object attributes (name, location, type_id, etc.)
     * @param string $culture Culture code
     *
     * @return int The new object ID
     */
    public function createPhysicalObject(array $data, string $culture = 'en'): int;

    /**
     * Update an existing physical object.
     *
     * @param int    $id      Physical object ID
     * @param array  $data    Column => value pairs to update
     * @param string $culture Culture code for i18n attributes
     */
    public function updatePhysicalObject(int $id, array $data, string $culture = 'en'): void;

    /**
     * Save a physical object with properties already set (Propel mode).
     *
     * In Propel mode, calls $resource->save(). In standalone mode,
     * extracts properties and performs INSERT/UPDATE via Laravel DB.
     *
     * @param object $resource The physical object to save (QubitPhysicalObject or stdClass)
     *
     * @return int The saved object ID
     */
    public function savePhysicalObject(object $resource): int;
}
