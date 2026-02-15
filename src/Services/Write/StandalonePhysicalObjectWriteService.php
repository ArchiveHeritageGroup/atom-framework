<?php

namespace AtomFramework\Services\Write;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Standalone physical object write service using Laravel Query Builder only.
 *
 * Clean implementation without Propel references or class_exists checks.
 * Handles the AtoM entity inheritance chain:
 *   object -> physical_object -> physical_object_i18n
 */
class StandalonePhysicalObjectWriteService implements PhysicalObjectWriteServiceInterface
{
    use EntityWriteTrait;

    private const I18N_FIELDS = ['name', 'description', 'location'];

    public function newPhysicalObject(): object
    {
        return new \stdClass();
    }

    public function createPhysicalObject(array $data, string $culture = 'en'): int
    {
        [$core, $i18n] = $this->splitI18nFields($data, self::I18N_FIELDS);

        $objectId = $this->insertEntity(
            'QubitPhysicalObject',
            'physical_object',
            $core,
            'physical_object_i18n',
            $i18n,
            $culture
        );

        $this->autoSlug($objectId, $i18n);

        return $objectId;
    }

    public function updatePhysicalObject(int $id, array $data, string $culture = 'en'): void
    {
        [$core, $i18n] = $this->splitI18nFields($data, self::I18N_FIELDS);
        $this->updateEntity($id, 'physical_object', $core, 'physical_object_i18n', $i18n, $culture);
    }

    public function savePhysicalObject(object $resource): int
    {
        $data = [];
        foreach (get_object_vars($resource) as $key => $value) {
            if (null !== $value && 'id' !== $key) {
                $data[$key] = $value;
            }
        }

        $culture = $data['sourceCulture'] ?? $data['source_culture'] ?? 'en';
        unset($data['sourceCulture'], $data['source_culture']);

        if (!empty($resource->id)) {
            $this->updatePhysicalObject((int) $resource->id, $data, $culture);

            return (int) $resource->id;
        }

        return $this->createPhysicalObject($data, $culture);
    }
}
