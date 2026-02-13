<?php

namespace AtomFramework\Services\Write;

/**
 * Contract for accession write operations.
 *
 * Covers: creating accessions, updating accessions, creating
 * related information objects from accessions, linking donors,
 * and managing accession relations.
 *
 * The PropelAdapter wraps QubitAccession, QubitRelation, QubitEvent
 * for Symfony mode. Falls back to Laravel DB for standalone mode.
 */
interface AccessionWriteServiceInterface
{
    /**
     * Create a new accession record.
     *
     * @param array  $attributes Accession attributes (identifier, title, etc.)
     * @param string $culture    Culture code
     *
     * @return object The created accession (QubitAccession or stdClass with ->id)
     */
    public function createAccession(array $attributes, string $culture = 'en'): object;

    /**
     * Update accession attributes.
     *
     * @param int    $id         Accession ID
     * @param array  $attributes Column => value pairs to update
     * @param string $culture    Culture code for i18n attributes
     */
    public function updateAccession(int $id, array $attributes, string $culture = 'en'): void;

    /**
     * Create an information object from accession data and link it.
     *
     * Copies title, scope, archival history, physical characteristics,
     * rights, creators, and dates from the accession to a new IO.
     *
     * @param object $accession The source accession (QubitAccession or stdClass)
     *
     * @return object The created information object (QubitInformationObject or stdClass with ->id)
     */
    public function createRelatedInformationObject(object $accession): object;

    /**
     * Create a relation between two objects.
     *
     * @param int  $subjectId    Subject object ID
     * @param int  $objectId     Object ID (related to)
     * @param int  $typeId       Relation type term ID
     * @param bool $indexOnSave  Whether to index on save (default true)
     *
     * @return object The created relation (QubitRelation or stdClass with ->id)
     */
    public function createRelation(int $subjectId, int $objectId, int $typeId, bool $indexOnSave = true): object;

    /**
     * Link a donor to an accession.
     *
     * @param int $accessionId Accession ID
     * @param int $donorId     Donor (actor) ID
     */
    public function linkDonor(int $accessionId, int $donorId): void;

    /**
     * Create an event linked to an information object.
     *
     * @param int   $objectId   Information object ID
     * @param array $attributes Event attributes (actor_id, type_id, date, etc.)
     * @param string $culture   Culture code
     *
     * @return object The created event (QubitEvent or stdClass with ->id)
     */
    public function createEvent(int $objectId, array $attributes, string $culture = 'en'): object;

    // ─── Factory Methods (unsaved objects for Propel relationship collections) ──

    /**
     * Create a new unsaved accession object.
     *
     * Returns a QubitAccession (Propel) or stdClass for form binding.
     *
     * @return object Unsaved QubitAccession or stdClass
     */
    public function newAccession(): object;

    /**
     * Create a new unsaved relation object for use in Propel relationship collections.
     *
     * Returns a QubitRelation (Propel) or stdClass suitable for deferred save.
     *
     * @return object Unsaved QubitRelation or stdClass
     */
    public function newRelation(): object;

    /**
     * Create a new unsaved information object for use in Propel relationship collections.
     *
     * Returns a QubitInformationObject (Propel) or stdClass suitable for deferred save.
     *
     * @return object Unsaved QubitInformationObject or stdClass
     */
    public function newInformationObject(): object;

    /**
     * Create a new unsaved event object for use in Propel relationship collections.
     *
     * Returns a QubitEvent (Propel) or stdClass suitable for deferred save.
     *
     * @return object Unsaved QubitEvent or stdClass
     */
    public function newEvent(): object;
}
