<?php

declare(strict_types=1);

namespace AtomExtensions\Extensions\Ar3dViewer\Repositories;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * AR3D Repository.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class Ar3dRepository
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
     * Check if file exists.
     */
    public function fileExists(string $path): bool
    {
        $fullPath = sfConfig::get('sf_web_dir') . '/' . $path;

        return file_exists($fullPath) && is_readable($fullPath);
    }
}
