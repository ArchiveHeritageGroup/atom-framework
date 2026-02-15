<?php

namespace AtomFramework\Services\Write;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Standalone information object write service using Laravel Query Builder only.
 *
 * Clean implementation without Propel references or class_exists checks.
 * Handles the AtoM entity inheritance chain:
 *   object -> information_object -> information_object_i18n
 *
 * Information objects have 18 i18n fields.
 */
class StandaloneInformationObjectWriteService implements InformationObjectWriteServiceInterface
{
    use EntityWriteTrait;

    private const I18N_FIELDS = [
        'title', 'alternate_title', 'scope_and_content', 'arrangement',
        'archival_history', 'acquisition', 'appraisal', 'accruals',
        'physical_characteristics', 'finding_aids', 'access_conditions',
        'reproduction_conditions', 'location_of_originals', 'location_of_copies',
        'related_units_of_description', 'rules', 'sources', 'revision_history',
    ];

    public function newInformationObject(): object
    {
        $io = new \stdClass();
        $io->id = null;
        $io->parentId = null;
        $io->identifier = null;
        $io->title = null;
        $io->levelOfDescriptionId = null;
        $io->sourceStandard = null;
        $io->sourceCulture = 'en';
        $io->displayStandardId = null;

        return $io;
    }

    public function createInformationObject(array $data, string $culture = 'en'): int
    {
        [$core, $i18n] = $this->splitI18nFields($data, self::I18N_FIELDS);

        // Default parent to root
        if (!isset($core['parent_id'])) {
            $parentId = $data['parentId'] ?? null;
            $core['parent_id'] = $parentId ?? \QubitInformationObject::ROOT_ID;
        }

        $objectId = $this->insertEntity(
            'QubitInformationObject',
            'information_object',
            $core,
            'information_object_i18n',
            $i18n,
            $culture
        );

        $this->autoSlug($objectId, $i18n, 'title');

        return $objectId;
    }

    public function updateInformationObject(int $id, array $data, string $culture = 'en'): void
    {
        [$core, $i18n] = $this->splitI18nFields($data, self::I18N_FIELDS);
        $this->updateEntity($id, 'information_object', $core, 'information_object_i18n', $i18n, $culture);
    }

    public function saveInformationObject(object $informationObject): int
    {
        $data = [];
        foreach (get_object_vars($informationObject) as $key => $value) {
            if (null !== $value && 'id' !== $key) {
                $data[$key] = $value;
            }
        }

        $culture = $data['sourceCulture'] ?? $data['source_culture'] ?? 'en';
        unset($data['sourceCulture'], $data['source_culture']);

        if (!empty($informationObject->id)) {
            $this->updateInformationObject($informationObject->id, $data, $culture);

            return $informationObject->id;
        }

        return $this->createInformationObject($data, $culture);
    }
}
