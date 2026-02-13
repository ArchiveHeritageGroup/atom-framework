<?php

namespace AtomFramework\Services\Write;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * PropelAdapter: Accession write operations.
 *
 * Uses Propel (QubitAccession, QubitRelation, QubitEvent, QubitInformationObject)
 * when available (Symfony mode). Falls back to Laravel Query Builder for
 * standalone Heratio mode.
 *
 * The createRelatedInformationObject() method requires Propel because it
 * triggers complex Propel relationship chains (rights copying, event
 * creation, publication status, MPTT tree positioning).
 */
class PropelAccessionWriteService implements AccessionWriteServiceInterface
{
    private bool $hasPropel;

    public function __construct()
    {
        $this->hasPropel = class_exists('QubitAccession', false)
            || class_exists('QubitAccession');
    }

    public function createAccession(array $attributes, string $culture = 'en'): object
    {
        if ($this->hasPropel) {
            return $this->propelCreateAccession($attributes, $culture);
        }

        return $this->dbCreateAccession($attributes, $culture);
    }

    public function updateAccession(int $id, array $attributes, string $culture = 'en'): void
    {
        if ($this->hasPropel) {
            $this->propelUpdateAccession($id, $attributes, $culture);

            return;
        }

        $this->dbUpdateAccession($id, $attributes, $culture);
    }

    public function createRelatedInformationObject(object $accession): object
    {
        if (!$this->hasPropel) {
            throw new \RuntimeException(
                'Creating related information objects from accessions requires Propel. '
                . 'This operation is not yet supported in standalone Heratio mode.'
            );
        }

        return $this->propelCreateRelatedInformationObject($accession);
    }

    public function createRelation(int $subjectId, int $objectId, int $typeId, bool $indexOnSave = true): object
    {
        if ($this->hasPropel) {
            return $this->propelCreateRelation($subjectId, $objectId, $typeId, $indexOnSave);
        }

        return $this->dbCreateRelation($subjectId, $objectId, $typeId);
    }

    public function linkDonor(int $accessionId, int $donorId): void
    {
        $this->createRelation($accessionId, $donorId, \QubitTerm::DONOR_ID);
    }

    public function createEvent(int $objectId, array $attributes, string $culture = 'en'): object
    {
        if ($this->hasPropel) {
            return $this->propelCreateEvent($objectId, $attributes, $culture);
        }

        return $this->dbCreateEvent($objectId, $attributes, $culture);
    }

    // ─── Propel Implementation ──────────────────────────────────────

    private function propelCreateAccession(array $attributes, string $culture): object
    {
        $accession = new \QubitAccession();
        $accession->sourceCulture = $culture;

        foreach ($attributes as $key => $value) {
            $accession->{$key} = $value;
        }

        $accession->save();

        return $accession;
    }

    private function propelUpdateAccession(int $id, array $attributes, string $culture): void
    {
        $accession = \QubitAccession::getById($id);
        if (null === $accession) {
            return;
        }

        foreach ($attributes as $key => $value) {
            $accession->{$key} = $value;
        }

        $accession->save();
    }

    private function propelCreateRelatedInformationObject(object $accession): object
    {
        $informationObject = new \QubitInformationObject();
        $informationObject->setRoot();

        // Copy fields from accession
        $informationObject->title = $accession->title;
        $informationObject->physicalCharacteristics = $accession->physicalCharacteristics;
        $informationObject->scopeAndContent = $accession->scopeAndContent;
        $informationObject->archivalHistory = $accession->archivalHistory;

        // Copy (not link) rights
        foreach (\QubitRelation::getRelationsBySubjectId($accession->id, ['typeId' => \QubitTerm::RIGHT_ID]) as $item) {
            $sourceRights = $item->object;
            $newRights = $sourceRights->copy();

            $relation = new \QubitRelation();
            $relation->object = $newRights;
            $relation->typeId = \QubitTerm::RIGHT_ID;

            $informationObject->relationsRelatedBysubjectId[] = $relation;
        }

        // Populate creators (from QubitRelation to QubitEvent)
        foreach (\QubitRelation::getRelationsByObjectId($accession->id, ['typeId' => \QubitTerm::CREATION_ID]) as $item) {
            $event = new \QubitEvent();
            $event->actor = $item->subject;
            $event->typeId = \QubitTerm::CREATION_ID;

            $informationObject->eventsRelatedByobjectId[] = $event;
        }

        // Populate dates
        foreach ($accession->getDates() as $accessionEvent) {
            $event = new \QubitEvent();
            $event->date = $accessionEvent->date;
            $event->startDate = $accessionEvent->startDate;
            $event->endDate = $accessionEvent->endDate;
            $event->typeId = $accessionEvent->typeId;

            $informationObject->eventsRelatedByobjectId[] = $event;
        }

        // Relationship between the information object and accession record
        $relation = new \QubitRelation();
        $relation->object = $accession;
        $relation->typeId = \QubitTerm::ACCESSION_ID;

        $informationObject->relationsRelatedBysubjectId[] = $relation;

        // Set publication status
        $informationObject->setPublicationStatus(
            \sfConfig::get('app_defaultPubStatus', \QubitTerm::PUBLICATION_STATUS_DRAFT_ID)
        );

        $informationObject->save();

        return $informationObject;
    }

    private function propelCreateRelation(int $subjectId, int $objectId, int $typeId, bool $indexOnSave): object
    {
        $relation = new \QubitRelation();
        $relation->subjectId = $subjectId;
        $relation->objectId = $objectId;
        $relation->typeId = $typeId;
        $relation->indexOnSave = $indexOnSave;
        $relation->save();

        return $relation;
    }

    private function propelCreateEvent(int $objectId, array $attributes, string $culture): object
    {
        $event = new \QubitEvent();
        $event->objectId = $objectId;

        foreach ($attributes as $key => $value) {
            $event->{$key} = $value;
        }

        $event->save();

        return $event;
    }

    // ─── Laravel DB Fallback ────────────────────────────────────────

    private function dbCreateAccession(array $attributes, string $culture): object
    {
        $now = date('Y-m-d H:i:s');

        // Separate i18n attributes
        $i18nFields = [
            'appraisal', 'archival_history', 'location_information',
            'physical_characteristics', 'processing_notes', 'received_extent_units',
            'scope_and_content', 'source_of_acquisition', 'title',
        ];

        $i18nData = [];
        $coreData = [];

        foreach ($attributes as $key => $value) {
            $snakeKey = $this->toSnakeCase($key);
            if (in_array($snakeKey, $i18nFields)) {
                $i18nData[$snakeKey] = $value;
            } else {
                $coreData[$snakeKey] = $value;
            }
        }

        // Insert into object table
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitAccession',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Insert into accession table
        $coreData['id'] = $objectId;
        $coreData['source_culture'] = $culture;
        DB::table('accession')->insert($coreData);

        // Insert i18n row
        if (!empty($i18nData)) {
            $i18nData['id'] = $objectId;
            $i18nData['culture'] = $culture;
            DB::table('accession_i18n')->insert($i18nData);
        }

        $result = new \stdClass();
        $result->id = $objectId;
        foreach ($attributes as $key => $value) {
            $result->{$key} = $value;
        }

        return $result;
    }

    private function dbUpdateAccession(int $id, array $attributes, string $culture): void
    {
        $i18nFields = [
            'appraisal', 'archival_history', 'location_information',
            'physical_characteristics', 'processing_notes', 'received_extent_units',
            'scope_and_content', 'source_of_acquisition', 'title',
        ];

        $i18nUpdates = [];
        $coreUpdates = [];

        foreach ($attributes as $key => $value) {
            $snakeKey = $this->toSnakeCase($key);
            if (in_array($snakeKey, $i18nFields)) {
                $i18nUpdates[$snakeKey] = $value;
            } else {
                $coreUpdates[$snakeKey] = $value;
            }
        }

        if (!empty($coreUpdates)) {
            DB::table('accession')->where('id', $id)->update($coreUpdates);
        }

        if (!empty($i18nUpdates)) {
            DB::table('accession_i18n')
                ->where('id', $id)
                ->where('culture', $culture)
                ->update($i18nUpdates);
        }

        DB::table('object')->where('id', $id)->update([
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function dbCreateRelation(int $subjectId, int $objectId, int $typeId): object
    {
        $now = date('Y-m-d H:i:s');

        $relObjectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitRelation',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('relation')->insert([
            'id' => $relObjectId,
            'subject_id' => $subjectId,
            'object_id' => $objectId,
            'type_id' => $typeId,
        ]);

        $result = new \stdClass();
        $result->id = $relObjectId;
        $result->subjectId = $subjectId;
        $result->objectId = $objectId;
        $result->typeId = $typeId;

        return $result;
    }

    private function dbCreateEvent(int $objectId, array $attributes, string $culture): object
    {
        $now = date('Y-m-d H:i:s');

        $eventObjectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitEvent',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $eventData = ['id' => $eventObjectId, 'object_id' => $objectId];
        $i18nData = ['id' => $eventObjectId, 'culture' => $culture];

        $i18nFields = ['date', 'description'];

        foreach ($attributes as $key => $value) {
            $snakeKey = $this->toSnakeCase($key);
            if (in_array($snakeKey, $i18nFields)) {
                $i18nData[$snakeKey] = $value;
            } else {
                $eventData[$snakeKey] = $value;
            }
        }

        DB::table('event')->insert($eventData);
        DB::table('event_i18n')->insert($i18nData);

        $result = new \stdClass();
        $result->id = $eventObjectId;
        $result->objectId = $objectId;

        return $result;
    }

    /**
     * Convert camelCase to snake_case.
     */
    private function toSnakeCase(string $input): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($input)));
    }

    // ─── Factory Methods (unsaved objects) ──────────────────────────

    public function newAccession(): object
    {
        if ($this->hasPropel) {
            return new \QubitAccession();
        }

        return new \stdClass();
    }

    public function newRelation(): object
    {
        if ($this->hasPropel) {
            return new \QubitRelation();
        }

        return new \stdClass();
    }

    public function newInformationObject(): object
    {
        if ($this->hasPropel) {
            return new \QubitInformationObject();
        }

        return new \stdClass();
    }

    public function newEvent(): object
    {
        if ($this->hasPropel) {
            return new \QubitEvent();
        }

        return new \stdClass();
    }
}
