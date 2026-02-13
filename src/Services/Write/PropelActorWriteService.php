<?php

namespace AtomFramework\Services\Write;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * PropelAdapter: Actor write operations.
 *
 * Uses Propel (QubitActor, QubitRelation) when available (Symfony mode).
 * Falls back to Laravel Query Builder for standalone Heratio mode,
 * handling the AtoM entity inheritance chain:
 *   object -> actor -> actor_i18n
 */
class PropelActorWriteService implements ActorWriteServiceInterface
{
    private bool $hasPropel;

    public function __construct()
    {
        $this->hasPropel = class_exists('QubitActor', false)
            || class_exists('QubitActor');
    }

    public function createActor(array $data, string $culture = 'en'): int
    {
        if ($this->hasPropel) {
            return $this->propelCreateActor($data, $culture);
        }

        return $this->dbCreateActor($data, $culture);
    }

    public function updateActor(int $id, array $data, string $culture = 'en'): void
    {
        if ($this->hasPropel) {
            $this->propelUpdateActor($id, $data, $culture);

            return;
        }

        $this->dbUpdateActor($id, $data, $culture);
    }

    public function createRelation(int $subjectId, int $objectId, int $typeId): int
    {
        if ($this->hasPropel) {
            return $this->propelCreateRelation($subjectId, $objectId, $typeId);
        }

        return $this->dbCreateRelation($subjectId, $objectId, $typeId);
    }

    public function saveActor(object $actor): int
    {
        if ($this->hasPropel) {
            return $this->propelSaveActor($actor);
        }

        return $this->dbSaveActor($actor);
    }

    // ─── Propel Implementation ──────────────────────────────────────

    private function propelCreateActor(array $data, string $culture): int
    {
        $actor = new \QubitActor();
        $actor->parentId = \QubitActor::ROOT_ID;
        $actor->sourceCulture = $culture;

        // i18n fields handled via setters
        $i18nFields = [
            'authorized_form_of_name' => 'setAuthorizedFormOfName',
            'dates_of_existence' => 'setDatesOfExistence',
            'history' => 'setHistory',
            'places' => 'setPlaces',
            'legal_status' => 'setLegalStatus',
            'functions' => 'setFunctions',
            'mandates' => 'setMandates',
            'internal_structures' => 'setInternalStructures',
            'general_context' => 'setGeneralContext',
            'institution_responsible_identifier' => 'setInstitutionResponsibleIdentifier',
            'rules' => 'setRules',
            'sources' => 'setSources',
            'revision_history' => 'setRevisionHistory',
        ];

        // Also accept camelCase keys
        $camelToSnake = [
            'authorizedFormOfName' => 'authorized_form_of_name',
            'datesOfExistence' => 'dates_of_existence',
            'legalStatus' => 'legal_status',
            'internalStructures' => 'internal_structures',
            'generalContext' => 'general_context',
            'institutionResponsibleIdentifier' => 'institution_responsible_identifier',
            'revisionHistory' => 'revision_history',
        ];

        foreach ($data as $key => $value) {
            // Normalize camelCase to snake_case
            $snakeKey = $camelToSnake[$key] ?? $key;

            if (isset($i18nFields[$snakeKey])) {
                $setter = $i18nFields[$snakeKey];
                $actor->{$setter}($value, ['culture' => $culture]);
            } elseif ('entity_type_id' === $snakeKey || 'entityTypeId' === $key) {
                $actor->entityTypeId = $value;
            } elseif ('parent_id' === $snakeKey || 'parentId' === $key) {
                $actor->parentId = $value;
            } elseif ('description_identifier' === $snakeKey || 'descriptionIdentifier' === $key) {
                $actor->descriptionIdentifier = $value;
            } elseif ('description_status_id' === $snakeKey || 'descriptionStatusId' === $key) {
                $actor->descriptionStatusId = $value;
            } elseif ('description_detail_id' === $snakeKey || 'descriptionDetailId' === $key) {
                $actor->descriptionDetailId = $value;
            } elseif ('corporate_body_identifiers' === $snakeKey || 'corporateBodyIdentifiers' === $key) {
                $actor->corporateBodyIdentifiers = $value;
            } else {
                // Try direct property assignment for any other fields
                $actor->{$key} = $value;
            }
        }

        $actor->save();

        return $actor->id;
    }

    private function propelUpdateActor(int $id, array $data, string $culture): void
    {
        $actor = \QubitActor::getById($id);
        if (null === $actor) {
            return;
        }

        // i18n fields handled via setters
        $i18nFields = [
            'authorized_form_of_name' => 'setAuthorizedFormOfName',
            'dates_of_existence' => 'setDatesOfExistence',
            'history' => 'setHistory',
            'places' => 'setPlaces',
            'legal_status' => 'setLegalStatus',
            'functions' => 'setFunctions',
            'mandates' => 'setMandates',
            'internal_structures' => 'setInternalStructures',
            'general_context' => 'setGeneralContext',
            'institution_responsible_identifier' => 'setInstitutionResponsibleIdentifier',
            'rules' => 'setRules',
            'sources' => 'setSources',
            'revision_history' => 'setRevisionHistory',
        ];

        $camelToSnake = [
            'authorizedFormOfName' => 'authorized_form_of_name',
            'datesOfExistence' => 'dates_of_existence',
            'legalStatus' => 'legal_status',
            'internalStructures' => 'internal_structures',
            'generalContext' => 'general_context',
            'institutionResponsibleIdentifier' => 'institution_responsible_identifier',
            'revisionHistory' => 'revision_history',
        ];

        foreach ($data as $key => $value) {
            $snakeKey = $camelToSnake[$key] ?? $key;

            if (isset($i18nFields[$snakeKey])) {
                $setter = $i18nFields[$snakeKey];
                $actor->{$setter}($value, ['culture' => $culture]);
            } else {
                $actor->{$key} = $value;
            }
        }

        $actor->save();
    }

    private function propelCreateRelation(int $subjectId, int $objectId, int $typeId): int
    {
        $relation = new \QubitRelation();
        $relation->subjectId = $subjectId;
        $relation->objectId = $objectId;
        $relation->typeId = $typeId;
        $relation->save();

        return $relation->id;
    }

    private function propelSaveActor(object $actor): int
    {
        $actor->save();

        return $actor->id;
    }

    // ─── Laravel DB Fallback ────────────────────────────────────────

    private function dbCreateActor(array $data, string $culture): int
    {
        $now = date('Y-m-d H:i:s');

        // Separate i18n fields from core fields
        $i18nFieldNames = [
            'authorized_form_of_name', 'authorizedFormOfName',
            'dates_of_existence', 'datesOfExistence',
            'history',
            'places',
            'legal_status', 'legalStatus',
            'functions',
            'mandates',
            'internal_structures', 'internalStructures',
            'general_context', 'generalContext',
            'institution_responsible_identifier', 'institutionResponsibleIdentifier',
            'rules',
            'sources',
            'revision_history', 'revisionHistory',
        ];

        $i18nData = ['culture' => $culture];
        $actorData = [
            'source_culture' => $culture,
            'parent_id' => \QubitActor::ROOT_ID,
        ];

        foreach ($data as $key => $value) {
            $snakeKey = $this->toSnakeCase($key);

            if (in_array($key, $i18nFieldNames) || in_array($snakeKey, $i18nFieldNames)) {
                $i18nData[$snakeKey] = $value;
            } elseif ('parent_id' === $snakeKey) {
                $actorData['parent_id'] = $value;
            } elseif ('entity_type_id' === $snakeKey) {
                $actorData['entity_type_id'] = $value;
            } elseif ('description_identifier' === $snakeKey) {
                $actorData['description_identifier'] = $value;
            } elseif ('description_status_id' === $snakeKey) {
                $actorData['description_status_id'] = $value;
            } elseif ('description_detail_id' === $snakeKey) {
                $actorData['description_detail_id'] = $value;
            } elseif ('corporate_body_identifiers' === $snakeKey) {
                $actorData['corporate_body_identifiers'] = $value;
            }
        }

        // Insert into object table
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitActor',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Insert into actor table
        $actorData['id'] = $objectId;
        DB::table('actor')->insert($actorData);

        // Insert into actor_i18n table
        $i18nData['id'] = $objectId;
        DB::table('actor_i18n')->insert($i18nData);

        return $objectId;
    }

    private function dbUpdateActor(int $id, array $data, string $culture): void
    {
        $i18nFieldNames = [
            'authorized_form_of_name', 'authorizedFormOfName',
            'dates_of_existence', 'datesOfExistence',
            'history',
            'places',
            'legal_status', 'legalStatus',
            'functions',
            'mandates',
            'internal_structures', 'internalStructures',
            'general_context', 'generalContext',
            'institution_responsible_identifier', 'institutionResponsibleIdentifier',
            'rules',
            'sources',
            'revision_history', 'revisionHistory',
        ];

        $i18nUpdates = [];
        $actorUpdates = [];

        foreach ($data as $key => $value) {
            $snakeKey = $this->toSnakeCase($key);

            if (in_array($key, $i18nFieldNames) || in_array($snakeKey, $i18nFieldNames)) {
                $i18nUpdates[$snakeKey] = $value;
            } else {
                $actorUpdates[$snakeKey] = $value;
            }
        }

        if (!empty($actorUpdates)) {
            DB::table('actor')->where('id', $id)->update($actorUpdates);
        }

        if (!empty($i18nUpdates)) {
            DB::table('actor_i18n')
                ->where('id', $id)
                ->where('culture', $culture)
                ->update($i18nUpdates);
        }

        DB::table('object')->where('id', $id)->update([
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function dbCreateRelation(int $subjectId, int $objectId, int $typeId): int
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
            'source_culture' => 'en',
        ]);

        return $relObjectId;
    }

    private function dbSaveActor(object $actor): int
    {
        // Extract properties from the stdClass/object
        $data = [];
        $i18nFieldNames = [
            'authorizedFormOfName', 'authorized_form_of_name',
            'datesOfExistence', 'dates_of_existence',
            'history', 'places', 'legalStatus', 'legal_status',
            'functions', 'mandates',
            'internalStructures', 'internal_structures',
            'generalContext', 'general_context',
            'institutionResponsibleIdentifier', 'institution_responsible_identifier',
            'rules', 'sources',
            'revisionHistory', 'revision_history',
        ];

        foreach (get_object_vars($actor) as $key => $value) {
            if (null !== $value && 'id' !== $key) {
                $data[$key] = $value;
            }
        }

        $culture = $data['sourceCulture'] ?? $data['source_culture'] ?? 'en';
        unset($data['sourceCulture'], $data['source_culture']);

        // If actor has an ID, this is an update
        if (!empty($actor->id)) {
            $this->dbUpdateActor($actor->id, $data, $culture);

            return $actor->id;
        }

        // Otherwise create a new actor
        return $this->dbCreateActor($data, $culture);
    }

    /**
     * Convert camelCase to snake_case.
     */
    private function toSnakeCase(string $input): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($input)));
    }
}
