<?php

namespace AtomFramework\Services\Write;

/**
 * Contract for import write operations.
 *
 * Covers: creating information objects, actors, terms, events,
 * notes, properties, and slugs during CSV/bulk import operations.
 *
 * The PropelAdapter wraps Qubit* classes for Symfony mode.
 * Falls back to Laravel DB for standalone Heratio mode,
 * handling the AtoM entity inheritance chain:
 *   object -> information_object -> information_object_i18n
 *   object -> actor -> actor_i18n
 *   object -> term -> term_i18n
 */
interface ImportWriteServiceInterface
{
    /**
     * Create a new information object with i18n attributes.
     *
     * Handles the inheritance chain: object -> information_object -> information_object_i18n.
     *
     * @param array  $attributes     Core attributes (parent_id, repository_id, level_of_description_id, etc.)
     * @param array  $i18nAttributes Localized attributes (title, scope_and_content, etc.)
     * @param string $culture        Culture code
     *
     * @return int The new information_object.id
     */
    public function createInformationObject(array $attributes, array $i18nAttributes, string $culture = 'en'): int;

    /**
     * Create an event linked to an object.
     *
     * @param int    $objectId   Object (information_object) ID
     * @param array  $attributes Event attributes (actor_id, type_id, date, start_date, end_date)
     * @param string $culture    Culture code
     *
     * @return int The new event.id
     */
    public function createEvent(int $objectId, array $attributes, string $culture = 'en'): int;

    /**
     * Create or find an actor by authorized form of name.
     *
     * @param string $name    Actor name (authorized form)
     * @param string $culture Culture code
     *
     * @return int The actor.id (existing or newly created)
     */
    public function createOrFindActor(string $name, string $culture = 'en'): int;

    /**
     * Create or find a term by name within a taxonomy.
     *
     * @param int    $taxonomyId Taxonomy ID
     * @param string $name       Term name
     * @param string $culture    Culture code
     *
     * @return int The term.id (existing or newly created)
     */
    public function createOrFindTerm(int $taxonomyId, string $name, string $culture = 'en'): int;

    /**
     * Create a note on an object.
     *
     * @param int    $objectId Object ID
     * @param int    $typeId   Note type term ID
     * @param string $content  Note content
     * @param string $culture  Culture code
     *
     * @return int The new note.id
     */
    public function createNote(int $objectId, int $typeId, string $content, string $culture = 'en'): int;

    /**
     * Create a property on an object.
     *
     * @param int    $objectId Object ID
     * @param string $name     Property name
     * @param string $value    Property value
     * @param string $culture  Culture code
     *
     * @return int The new property.id
     */
    public function createProperty(int $objectId, string $name, string $value, string $culture = 'en'): int;

    /**
     * Create a slug for an object.
     *
     * @param int    $objectId Object ID
     * @param string $slug     URL slug
     */
    public function createSlug(int $objectId, string $slug): void;

    /**
     * Create a relation between two objects.
     *
     * @param int $subjectId Subject object ID
     * @param int $objectId  Related object ID
     * @param int $typeId    Relation type term ID
     *
     * @return int The new relation.id
     */
    public function createRelation(int $subjectId, int $objectId, int $typeId): int;
}
