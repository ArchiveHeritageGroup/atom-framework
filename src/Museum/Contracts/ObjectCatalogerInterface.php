<?php


namespace AtomFramework\Museum\Contracts;

use AtomFramework\Museum\Models\MuseumObject;

interface ObjectCatalogerInterface
{
    /**
     * Create a new museum object record.
     *
     * @param int   $informationObjectId The related information object ID
     * @param array $properties          Museum object properties
     *
     * @return MuseumObject
     */
    public function create(int $informationObjectId, array $properties): MuseumObject;

    /**
     * Update an existing museum object record.
     *
     * @param int   $id         The museum object ID
     * @param array $properties Properties to update
     *
     * @return MuseumObject
     */
    public function update(int $id, array $properties): MuseumObject;

    /**
     * Retrieve a museum object by information object ID.
     *
     * @param int $informationObjectId The information object ID
     *
     * @return MuseumObject|null
     */
    public function findByInformationObjectId(int $informationObjectId): ?MuseumObject;

    /**
     * Delete a museum object record.
     *
     * @param int $id The museum object ID
     *
     * @return bool True if deletion was successful
     */
    public function delete(int $id): bool;
}
