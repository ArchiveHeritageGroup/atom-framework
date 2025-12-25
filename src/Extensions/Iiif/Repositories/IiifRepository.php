<?php

declare(strict_types=1);

namespace AtomExtensions\Extensions\Iiif\Repositories;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * IIIF Repository.
 *
 * Provides data access for IIIF functionality.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class IiifRepository
{
    /**
     * Get digital object by ID.
     */
    public function getDigitalObject(int $id): ?object
    {
        return DB::table('digital_object as do')
            ->join('object as o', 'do.id', '=', 'o.id')
            ->where('do.id', $id)
            ->where('o.class_name', 'QubitDigitalObject')
            ->select(
                'do.id',
                'do.information_object_id',
                'do.usage_id',
                'do.media_type_id',
                'do.mime_type',
                'do.name',
                'do.path',
                'do.byte_size',
                'o.created_at',
                'o.updated_at'
            )
            ->first();
    }

    /**
     * Get information object for digital object.
     */
    public function getInformationObject(int $digitalObjectId, string $culture = 'en'): ?object
    {
        return DB::table('digital_object as do')
            ->join('information_object as io', 'do.information_object_id', '=', 'io.id')
            ->join('information_object_i18n as i18n', function ($join) use ($culture) {
                $join->on('io.id', '=', 'i18n.id')
                     ->where('i18n.culture', $culture);
            })
            ->where('do.id', $digitalObjectId)
            ->select(
                'io.id',
                'io.identifier',
                'i18n.title',
                'i18n.scope_and_content',
                'io.level_of_description_id'
            )
            ->first();
    }

    /**
     * Check if image exists on filesystem.
     */
    public function imageExists(string $path): bool
    {
        $fullPath = sfConfig::get('sf_web_dir') . '/' . $path;

        return file_exists($fullPath) && is_readable($fullPath);
    }

    /**
     * Get image dimensions.
     */
    public function getImageDimensions(string $path): ?array
    {
        $fullPath = sfConfig::get('sf_web_dir') . '/' . $path;

        if (!$this->imageExists($path)) {
            return null;
        }

        $info = @getimagesize($fullPath);
        if ($info === false) {
            return null;
        }

        return [
            'width' => $info[0],
            'height' => $info[1],
            'mime_type' => $info['mime'],
        ];
    }
}
