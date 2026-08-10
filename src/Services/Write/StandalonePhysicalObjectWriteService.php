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

    /**
     * A blank physical object for an edit form.
     *
     * Returns a QubitPhysicalObject where AtoM is present, and only falls back
     * to stdClass in a standalone context where that class does not exist.
     *
     * It returned stdClass unconditionally, and that made creating a physical
     * object impossible on every install. The edit form hands the resource to
     * base AtoM's own helpers, which call __get() on it - undefined on stdClass,
     * so the render died mid-page. Because the fatal happened after headers were
     * sent, /physicalobject/add answered HTTP 200 with a 39-byte body: no error
     * page, no 500, a blank screen and nothing in the AtoM log. The only trace
     * was "Call to undefined method stdClass::__get()" in nginx's error log.
     *
     * A blank object of the right class is what every caller was already
     * assuming it had.
     */
    public function newPhysicalObject(): object
    {
        if (class_exists('\QubitPhysicalObject')) {
            return new \QubitPhysicalObject();
        }

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
