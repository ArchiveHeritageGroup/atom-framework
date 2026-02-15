<?php

declare(strict_types=1);

namespace AtomFramework\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Lightweight digital object wrapper for template compatibility.
 *
 * Wraps a stdClass from digital_object table and provides __get/__call/__isset
 * so that sfOutputEscaperObjectDecorator can call methods like getName(), getFullPath().
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class LightweightDigitalObject
{
    private object $data;

    public function __construct(object $data)
    {
        $this->data = $data;
    }

    public function __get(string $name)
    {
        return $this->data->{$name} ?? null;
    }

    public function __isset(string $name): bool
    {
        return isset($this->data->{$name});
    }

    /**
     * Handle Propel-style getter calls (getName, getFullPath, etc.)
     */
    public function __call(string $name, array $arguments)
    {
        if (str_starts_with($name, 'get') && strlen($name) > 3) {
            $property = lcfirst(substr($name, 3));

            if (isset($this->data->{$property})) {
                return $this->data->{$property};
            }

            // snake_case fallback
            $snakeCase = strtolower(preg_replace('/[A-Z]/', '_$0', $property));
            $snakeCase = ltrim($snakeCase, '_');
            if (isset($this->data->{$snakeCase})) {
                return $this->data->{$snakeCase};
            }

            return null;
        }

        return null;
    }

    public function __toString(): string
    {
        return $this->data->name ?? '';
    }

    /**
     * Get the full filesystem path to this digital object.
     * Mimics QubitDigitalObject::getFullPath().
     */
    public function getFullPath(): string
    {
        return ($this->data->path ?? '') . ($this->data->name ?? '');
    }

    /**
     * Get a derivative (thumbnail, reference) by usage ID.
     * Mimics QubitDigitalObject::getRepresentationByUsage().
     */
    public function getRepresentationByUsage(int $usageId): ?self
    {
        $id = $this->data->id ?? null;
        if (!$id) {
            return null;
        }

        $row = DB::table('digital_object')
            ->where('parent_id', $id)
            ->where('usage_id', $usageId)
            ->first();

        if (!$row) {
            return null;
        }

        $row->mimeType = $row->mime_type ?? null;
        $row->mediaTypeId = $row->media_type_id ?? null;
        $row->objectId = $row->object_id ?? null;
        $row->usageId = $row->usage_id ?? null;
        $row->parentId = $row->parent_id ?? null;

        return new self($row);
    }

    /**
     * Get raw data object.
     */
    public function getRawData(): object
    {
        return $this->data;
    }
}
