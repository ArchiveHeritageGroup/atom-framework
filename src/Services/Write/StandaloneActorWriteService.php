<?php

namespace AtomFramework\Services\Write;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Standalone actor write service using Laravel Query Builder only.
 *
 * Clean implementation without Propel references or class_exists checks.
 * Handles the AtoM entity inheritance chain:
 *   object -> actor -> actor_i18n
 *
 * Actor has 13 i18n fields in the actor_i18n table.
 */
class StandaloneActorWriteService implements ActorWriteServiceInterface
{
    use EntityWriteTrait;

    private const I18N_FIELDS = [
        'authorized_form_of_name', 'dates_of_existence', 'history',
        'places', 'legal_status', 'functions', 'mandates',
        'internal_structures', 'general_context',
        'institution_responsible_identifier',
        'rules', 'sources', 'revision_history',
    ];

    public function createActor(array $data, string $culture = 'en'): int
    {
        [$core, $i18n] = $this->splitI18nFields($data, self::I18N_FIELDS);

        if (!isset($core['parent_id'])) {
            $core['parent_id'] = \QubitActor::ROOT_ID;
        }

        $objectId = $this->insertEntity(
            'QubitActor',
            'actor',
            $core,
            'actor_i18n',
            $i18n,
            $culture
        );

        $this->autoSlug($objectId, $i18n, 'authorized_form_of_name');

        return $objectId;
    }

    public function updateActor(int $id, array $data, string $culture = 'en'): void
    {
        [$core, $i18n] = $this->splitI18nFields($data, self::I18N_FIELDS);
        $this->updateEntity($id, 'actor', $core, 'actor_i18n', $i18n, $culture);
    }

    public function createRelation(int $subjectId, int $objectId, int $typeId): int
    {
        return $this->createRelationRecord($subjectId, $objectId, $typeId);
    }

    public function saveActor(object $actor): int
    {
        $data = [];
        foreach (get_object_vars($actor) as $key => $value) {
            if (null !== $value && 'id' !== $key) {
                $data[$key] = $value;
            }
        }

        $culture = $data['sourceCulture'] ?? $data['source_culture'] ?? 'en';
        unset($data['sourceCulture'], $data['source_culture']);

        if (!empty($actor->id)) {
            $this->updateActor($actor->id, $data, $culture);

            return $actor->id;
        }

        return $this->createActor($data, $culture);
    }
}
