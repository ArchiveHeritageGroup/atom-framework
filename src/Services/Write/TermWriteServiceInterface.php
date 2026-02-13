<?php

namespace AtomFramework\Services\Write;

/**
 * Contract for term/taxonomy write operations.
 *
 * Covers: creating terms, updating terms, deleting terms,
 * creating notes, creating relations, and managing other-names
 * (use-for / alternative labels).
 *
 * The PropelAdapter wraps QubitTerm, QubitRelation, QubitOtherName
 * for Symfony mode. Falls back to Laravel DB for standalone mode.
 */
interface TermWriteServiceInterface
{
    /**
     * Create a new term in a taxonomy.
     *
     * @param int         $taxonomyId Taxonomy ID to create term in
     * @param string      $name       Term name
     * @param string      $culture    Culture code (e.g., 'en')
     * @param int|null    $parentId   Parent term ID (null = ROOT_ID)
     *
     * @return object The created term (QubitTerm or stdClass with ->id)
     */
    public function createTerm(int $taxonomyId, string $name, string $culture = 'en', ?int $parentId = null): object;

    /**
     * Update term attributes.
     *
     * @param int    $id         Term ID
     * @param array  $attributes Column => value pairs to update
     * @param string $culture    Culture code for i18n attributes
     */
    public function updateTerm(int $id, array $attributes, string $culture = 'en'): void;

    /**
     * Delete a term by ID.
     *
     * @param int $id Term ID
     *
     * @return bool True if deleted
     */
    public function deleteTerm(int $id): bool;

    /**
     * Create a note on an object (scope note, source note, display note, etc.).
     *
     * @param int    $objectId Object (term) ID
     * @param int    $typeId   Note type term ID
     * @param string $content  Note content
     * @param string $culture  Culture code
     *
     * @return int The new note ID
     */
    public function createNote(int $objectId, int $typeId, string $content, string $culture = 'en'): int;

    /**
     * Create a relation between two objects.
     *
     * @param int $subjectId Subject object ID
     * @param int $objectId  Object ID (related to)
     * @param int $typeId    Relation type term ID
     *
     * @return object The created relation (QubitRelation or stdClass with ->id)
     */
    public function createRelation(int $subjectId, int $objectId, int $typeId): object;

    /**
     * Create an other-name (alternative label / use-for) on an object.
     *
     * @param int    $objectId Object ID
     * @param string $name     The alternative name
     * @param int    $typeId   Other-name type term ID
     * @param string $culture  Culture code
     *
     * @return object The created other-name (QubitOtherName or stdClass with ->id)
     */
    public function createOtherName(int $objectId, string $name, int $typeId, string $culture = 'en'): object;

    // ─── Factory Methods (unsaved objects for Propel relationship collections) ──

    /**
     * Create a new unsaved term object for use in Propel relationship collections.
     *
     * Returns a QubitTerm (Propel) or stdClass suitable for deferred save via
     * parent relationship collections (e.g., $parent->termsRelatedByparentId[]).
     *
     * @return object Unsaved QubitTerm or stdClass
     */
    public function newTerm(): object;

    /**
     * Create a new unsaved relation object for use in Propel relationship collections.
     *
     * Returns a QubitRelation (Propel) or stdClass suitable for deferred save.
     *
     * @return object Unsaved QubitRelation or stdClass
     */
    public function newRelation(): object;

    /**
     * Create a new unsaved other-name object for use in Propel relationship collections.
     *
     * Returns a QubitOtherName (Propel) or stdClass suitable for deferred save.
     *
     * @return object Unsaved QubitOtherName or stdClass
     */
    public function newOtherName(): object;
}
