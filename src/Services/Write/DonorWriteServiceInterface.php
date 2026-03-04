<?php

namespace AtomFramework\Services\Write;

/**
 * Contract for donor write operations.
 *
 * Donor extends Actor in AtoM's entity hierarchy:
 *   object -> actor -> actor_i18n -> donor
 *
 * The donor table only has an `id` column (FK to actor.id).
 * All name/description fields live in actor_i18n.
 */
interface DonorWriteServiceInterface
{
    /**
     * Create a new donor. Returns the new ID.
     *
     * Handles the entity inheritance:
     *   INSERT object -> INSERT actor -> INSERT actor_i18n -> INSERT donor
     *
     * @param array  $data    Donor data (authorized_form_of_name, etc.)
     * @param string $culture Culture code (e.g., 'en')
     *
     * @return int The new donor.id
     */
    public function createDonor(array $data, string $culture = 'en'): int;

    /**
     * Update an existing donor.
     *
     * @param int    $id      Donor ID
     * @param array  $data    Column => value pairs to update
     * @param string $culture Culture code for i18n attributes
     */
    public function updateDonor(int $id, array $data, string $culture = 'en'): void;

    /**
     * Delete a donor.
     *
     * @param int $id Donor ID
     */
    public function deleteDonor(int $id): void;

    /**
     * Create a new, empty donor object for form binding.
     *
     * @return object
     */
    public function newDonor(): object;
}
