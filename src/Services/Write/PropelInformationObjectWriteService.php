<?php

namespace AtomFramework\Services\Write;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * PropelAdapter: Information Object write operations.
 *
 * Uses Propel (QubitInformationObject) when available (Symfony mode).
 * Falls back to Laravel Query Builder for standalone Heratio mode,
 * handling the AtoM entity inheritance chain:
 *   object -> information_object -> information_object_i18n
 */
class PropelInformationObjectWriteService implements InformationObjectWriteServiceInterface
{
    private bool $hasPropel;

    public function __construct()
    {
        $this->hasPropel = class_exists('QubitInformationObject', false)
            || class_exists('QubitInformationObject');
    }

    public function newInformationObject(): object
    {
        if ($this->hasPropel) {
            return new \QubitInformationObject();
        }

        // Standalone mode: return a stdClass with standard properties
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
        if ($this->hasPropel) {
            return $this->propelCreate($data, $culture);
        }

        return $this->dbCreate($data, $culture);
    }

    public function updateInformationObject(int $id, array $data, string $culture = 'en'): void
    {
        if ($this->hasPropel) {
            $this->propelUpdate($id, $data, $culture);

            return;
        }

        $this->dbUpdate($id, $data, $culture);
    }

    public function saveInformationObject(object $informationObject): int
    {
        if ($this->hasPropel) {
            return $this->propelSave($informationObject);
        }

        return $this->dbSave($informationObject);
    }

    // ─── Propel Implementation ──────────────────────────────────────

    private function propelCreate(array $data, string $culture): int
    {
        $io = new \QubitInformationObject();
        $io->parentId = $data['parent_id'] ?? $data['parentId'] ?? \QubitInformationObject::ROOT_ID;
        $io->sourceCulture = $culture;

        $i18nFields = [
            'title' => 'setTitle',
            'alternate_title' => 'setAlternateTitle',
            'scope_and_content' => 'setScopeAndContent',
            'arrangement' => 'setArrangement',
            'archival_history' => 'setArchivalHistory',
            'acquisition' => 'setAcquisition',
            'appraisal' => 'setAppraisal',
            'accruals' => 'setAccruals',
            'physical_characteristics' => 'setPhysicalCharacteristics',
            'finding_aids' => 'setFindingAids',
            'access_conditions' => 'setAccessConditions',
            'reproduction_conditions' => 'setReproductionConditions',
            'location_of_originals' => 'setLocationOfOriginals',
            'location_of_copies' => 'setLocationOfCopies',
            'related_units_of_description' => 'setRelatedUnitsOfDescription',
            'rules' => 'setRules',
            'sources' => 'setSources',
            'revision_history' => 'setRevisionHistory',
        ];

        foreach ($data as $key => $value) {
            $snakeKey = $this->toSnakeCase($key);

            if (isset($i18nFields[$snakeKey])) {
                $setter = $i18nFields[$snakeKey];
                $io->{$setter}($value, ['culture' => $culture]);
            } elseif ('identifier' === $snakeKey) {
                $io->identifier = $value;
            } elseif ('level_of_description_id' === $snakeKey) {
                $io->levelOfDescriptionId = $value;
            } elseif ('repository_id' === $snakeKey) {
                $io->repositoryId = $value;
            } elseif ('source_standard' === $snakeKey) {
                $io->sourceStandard = $value;
            } elseif ('display_standard_id' === $snakeKey) {
                $io->displayStandardId = $value;
            }
        }

        $io->save();

        return $io->id;
    }

    private function propelUpdate(int $id, array $data, string $culture): void
    {
        $io = \QubitInformationObject::getById($id);
        if (null === $io) {
            return;
        }

        $i18nFields = [
            'title' => 'setTitle',
            'scope_and_content' => 'setScopeAndContent',
            'arrangement' => 'setArrangement',
            'archival_history' => 'setArchivalHistory',
            'acquisition' => 'setAcquisition',
            'appraisal' => 'setAppraisal',
            'access_conditions' => 'setAccessConditions',
            'reproduction_conditions' => 'setReproductionConditions',
            'rules' => 'setRules',
            'sources' => 'setSources',
            'revision_history' => 'setRevisionHistory',
        ];

        foreach ($data as $key => $value) {
            $snakeKey = $this->toSnakeCase($key);

            if (isset($i18nFields[$snakeKey])) {
                $setter = $i18nFields[$snakeKey];
                $io->{$setter}($value, ['culture' => $culture]);
            } else {
                $io->{$key} = $value;
            }
        }

        $io->save();
    }

    private function propelSave(object $io): int
    {
        $io->save();

        return $io->id;
    }

    // ─── Laravel DB Fallback ────────────────────────────────────────

    private function dbCreate(array $data, string $culture): int
    {
        $now = date('Y-m-d H:i:s');

        $i18nFieldNames = [
            'title', 'alternate_title', 'scope_and_content', 'arrangement',
            'archival_history', 'acquisition', 'appraisal', 'accruals',
            'physical_characteristics', 'finding_aids', 'access_conditions',
            'reproduction_conditions', 'location_of_originals', 'location_of_copies',
            'related_units_of_description', 'rules', 'sources', 'revision_history',
        ];

        $i18nData = ['culture' => $culture];
        $ioData = [
            'source_culture' => $culture,
            'parent_id' => $data['parent_id'] ?? $data['parentId'] ?? \QubitInformationObject::ROOT_ID,
        ];

        foreach ($data as $key => $value) {
            $snakeKey = $this->toSnakeCase($key);

            if (in_array($snakeKey, $i18nFieldNames, true)) {
                $i18nData[$snakeKey] = $value;
            } elseif ('identifier' === $snakeKey) {
                $ioData['identifier'] = $value;
            } elseif ('level_of_description_id' === $snakeKey) {
                $ioData['level_of_description_id'] = $value;
            } elseif ('repository_id' === $snakeKey) {
                $ioData['repository_id'] = $value;
            } elseif ('source_standard' === $snakeKey) {
                $ioData['source_standard'] = $value;
            } elseif ('display_standard_id' === $snakeKey) {
                $ioData['display_standard_id'] = $value;
            }
        }

        // Insert into object table
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitInformationObject',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Insert into information_object table
        $ioData['id'] = $objectId;
        DB::table('information_object')->insert($ioData);

        // Insert into information_object_i18n table
        $i18nData['id'] = $objectId;
        DB::table('information_object_i18n')->insert($i18nData);

        return $objectId;
    }

    private function dbUpdate(int $id, array $data, string $culture): void
    {
        $i18nFieldNames = [
            'title', 'alternate_title', 'scope_and_content', 'arrangement',
            'archival_history', 'acquisition', 'appraisal', 'accruals',
            'physical_characteristics', 'finding_aids', 'access_conditions',
            'reproduction_conditions', 'location_of_originals', 'location_of_copies',
            'related_units_of_description', 'rules', 'sources', 'revision_history',
        ];

        $i18nUpdates = [];
        $ioUpdates = [];

        foreach ($data as $key => $value) {
            $snakeKey = $this->toSnakeCase($key);

            if (in_array($snakeKey, $i18nFieldNames, true)) {
                $i18nUpdates[$snakeKey] = $value;
            } else {
                $ioUpdates[$snakeKey] = $value;
            }
        }

        if (!empty($ioUpdates)) {
            DB::table('information_object')->where('id', $id)->update($ioUpdates);
        }

        if (!empty($i18nUpdates)) {
            DB::table('information_object_i18n')
                ->where('id', $id)
                ->where('culture', $culture)
                ->update($i18nUpdates);
        }

        DB::table('object')->where('id', $id)->update([
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    private function dbSave(object $io): int
    {
        $data = [];
        foreach (get_object_vars($io) as $key => $value) {
            if (null !== $value && 'id' !== $key) {
                $data[$key] = $value;
            }
        }

        $culture = $data['sourceCulture'] ?? $data['source_culture'] ?? 'en';
        unset($data['sourceCulture'], $data['source_culture']);

        if (!empty($io->id)) {
            $this->dbUpdate($io->id, $data, $culture);

            return $io->id;
        }

        return $this->dbCreate($data, $culture);
    }

    /**
     * Convert camelCase to snake_case.
     */
    private function toSnakeCase(string $input): string
    {
        return strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($input)));
    }
}
