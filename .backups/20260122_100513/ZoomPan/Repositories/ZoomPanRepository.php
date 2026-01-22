<?php

declare(strict_types=1);

namespace AtomExtensions\Extensions\ZoomPan\Repositories;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Zoom-Pan Repository.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class ZoomPanRepository
{
    /**
     * Get digital object by ID.
     */
    public function getDigitalObject(int $id): ?object
    {
        return DB::table('digital_object as do')
            ->join('object as o', 'do.id', '=', 'o.id')
            ->where('do.id', $id)
            ->select(
                'do.id',
                'do.information_object_id',
                'do.mime_type',
                'do.name',
                'do.path',
                'do.byte_size'
            )
            ->first();
    }

    /**
     * Get image dimensions.
     */
    public function getImageDimensions(string $path): ?array
    {
        $fullPath = sfConfig::get('sf_web_dir') . '/' . $path;

        if (!file_exists($fullPath)) {
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
