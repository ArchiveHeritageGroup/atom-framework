<?php

namespace AtomFramework\Services\Write;

/**
 * Contract for actor write operations.
 *
 * Covers: creating actors, updating actors, creating relations,
 * and saving Propel actor objects.
 *
 * The PropelAdapter wraps QubitActor, QubitRelation for Symfony mode.
 * Falls back to Laravel DB for standalone Heratio mode, handling
 * the AtoM entity inheritance chain:
 *   object -> actor -> actor_i18n
 */
interface ActorWriteServiceInterface
{
    /**
     * Create a new actor. Returns the new actor ID.
     *
     * Handles the AtoM entity inheritance:
     *   INSERT object -> INSERT actor -> INSERT actor_i18n.
     *
     * @param array  $data    Actor data (entity_type_id, authorized_form_of_name, etc.)
     * @param string $culture Culture code (e.g., 'en')
     *
     * @return int The new actor.id
     */
    public function createActor(array $data, string $culture = 'en'): int;

    /**
     * Update an existing actor.
     *
     * @param int    $id      Actor ID
     * @param array  $data    Column => value pairs to update
     * @param string $culture Culture code for i18n attributes
     */
    public function updateActor(int $id, array $data, string $culture = 'en'): void;

    /**
     * Create a relation between two objects.
     *
     * relation table: subject_id, object_id, type_id
     *
     * @param int $subjectId Subject object ID
     * @param int $objectId  Related object ID
     * @param int $typeId    Relation type term ID
     *
     * @return int The new relation.id
     */
    public function createRelation(int $subjectId, int $objectId, int $typeId): int;

    /**
     * Save an actor with properties already set (Propel mode).
     *
     * In Propel mode, calls $actor->save().
     * In standalone mode, extracts properties and does DB inserts.
     *
     * @param object $actor The actor object (QubitActor or stdClass)
     *
     * @return int The actor ID
     */
    public function saveActor(object $actor): int;
}
