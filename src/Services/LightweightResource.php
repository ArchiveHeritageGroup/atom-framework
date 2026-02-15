<?php

declare(strict_types=1);

namespace AtomFramework\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Lightweight resource wrapper for template compatibility.
 *
 * Templates access: $resource->title, $resource->slug, $resource->id
 * This wraps a stdClass from EntityQueryService and provides __get/__isset/__toString
 * plus Propel-compatible methods (getTitle, getChildren, getDigitalObject, etc.)
 * so that plugin templates work without Propel.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class LightweightResource implements \ArrayAccess
{
    private object $data;

    /** @var LightweightResource|null cached parent */
    private $parentResource = false;

    public function __construct(object $data)
    {
        $this->data = $data;
    }

    // ArrayAccess — required for url_for([$resource, 'module' => '...']) compatibility
    public function offsetExists($offset): bool
    {
        return $this->__isset((string) $offset);
    }

    public function offsetGet($offset): mixed
    {
        return $this->__get((string) $offset);
    }

    public function offsetSet($offset, $value): void
    {
        $this->data->{$offset} = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->data->{$offset});
    }

    public function __get(string $name)
    {
        // Special property: parent (returns a LightweightResource)
        if ($name === 'parent') {
            return $this->getParent();
        }

        // Special property: levelOfDescription (returns object with name)
        if ($name === 'levelOfDescription') {
            return $this->getLevelOfDescription();
        }

        // Special property: digitalObjectsRelatedByobjectId
        if ($name === 'digitalObjectsRelatedByobjectId') {
            $do = $this->getDigitalObject();

            return $do ? [$do] : [];
        }

        return $this->data->{$name} ?? null;
    }

    public function __isset(string $name): bool
    {
        if (in_array($name, ['parent', 'levelOfDescription', 'digitalObjectsRelatedByobjectId'])) {
            return true;
        }

        return isset($this->data->{$name});
    }

    /**
     * Handle Propel-style getter calls (e.g. getTitle() → $data->title).
     * Required because sfOutputEscaperObjectDecorator calls methods, not properties.
     */
    public function __call(string $name, array $arguments)
    {
        // Convert getPropertyName() → property_name
        if (str_starts_with($name, 'get') && strlen($name) > 3) {
            $property = lcfirst(substr($name, 3));

            // Try camelCase first (e.g. getTitle → title)
            if (isset($this->data->{$property})) {
                return $this->data->{$property};
            }

            // Try snake_case (e.g. getScopeAndContent → scope_and_content)
            $snakeCase = strtolower(preg_replace('/[A-Z]/', '_$0', $property));
            $snakeCase = ltrim($snakeCase, '_');
            if (isset($this->data->{$snakeCase})) {
                return $this->data->{$snakeCase};
            }

            // i18n field lookup (e.g. getTitle with cultureFallback)
            $id = $this->data->id ?? null;
            if ($id) {
                $culture = $this->resolveCulture($arguments[0] ?? []);
                $row = DB::table('information_object_i18n')
                    ->where('id', $id)
                    ->where('culture', $culture)
                    ->first();
                if ($row && isset($row->{$snakeCase})) {
                    return $row->{$snakeCase};
                }

                // cultureFallback: try source culture
                if (!empty($arguments[0]['cultureFallback'])) {
                    $srcRow = DB::table('information_object')
                        ->where('id', $id)
                        ->value('source_culture');
                    if ($srcRow && $srcRow !== $culture) {
                        $fallback = DB::table('information_object_i18n')
                            ->where('id', $id)
                            ->where('culture', $srcRow)
                            ->first();
                        if ($fallback && isset($fallback->{$snakeCase})) {
                            return $fallback->{$snakeCase};
                        }
                    }
                }
            }

            return null;
        }

        return null;
    }

    public function __toString(): string
    {
        return $this->data->title
            ?? $this->data->authorized_form_of_name
            ?? $this->data->name
            ?? $this->data->slug
            ?? '';
    }

    /**
     * Get the raw underlying stdClass data.
     */
    public function getRawData(): object
    {
        return $this->data;
    }

    /**
     * Convert to array for serialization or debugging.
     */
    public function toArray(): array
    {
        return (array) $this->data;
    }

    /**
     * Get direct children of this resource as LightweightResource array.
     * Mimics QubitObject::getChildren().
     */
    public function getChildren(array $options = []): array
    {
        $id = $this->data->id ?? null;
        if (!$id) {
            return [];
        }

        $culture = $this->resolveCulture($options);

        $children = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($join) use ($culture) {
                $join->on('io.id', '=', 'i18n.id')
                    ->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('slug', 'slug.object_id', '=', 'io.id')
            ->where('io.parent_id', $id)
            ->orderBy('io.lft')
            ->select([
                'io.id', 'io.parent_id', 'io.lft', 'io.rgt',
                'io.level_of_description_id', 'io.identifier',
                'io.source_culture',
                'i18n.title', 'i18n.culture',
                'slug.slug',
            ])
            ->get();

        return $children->map(function ($row) {
            return new self($row);
        })->all();
    }

    /**
     * Get all descendants of this resource.
     * Uses nested set (lft/rgt) for efficient lookup.
     */
    public function getDescendants(array $options = []): array
    {
        $id = $this->data->id ?? null;
        $lft = $this->data->lft ?? null;
        $rgt = $this->data->rgt ?? null;
        if (!$id || !$lft || !$rgt) {
            return [];
        }

        $culture = $this->resolveCulture($options);

        $descendants = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($join) use ($culture) {
                $join->on('io.id', '=', 'i18n.id')
                    ->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('slug', 'slug.object_id', '=', 'io.id')
            ->where('io.lft', '>', $lft)
            ->where('io.rgt', '<', $rgt)
            ->orderBy('io.lft')
            ->select([
                'io.id', 'io.parent_id', 'io.lft', 'io.rgt',
                'io.level_of_description_id', 'io.identifier',
                'io.source_culture',
                'i18n.title', 'i18n.culture',
                'slug.slug',
            ])
            ->get();

        return $descendants->map(function ($row) {
            return new self($row);
        })->all();
    }

    /**
     * Get the parent resource.
     */
    public function getParent(): ?self
    {
        if ($this->parentResource !== false) {
            return $this->parentResource;
        }

        $parentId = $this->data->parent_id ?? null;
        if (!$parentId || $parentId == 1) {
            $this->parentResource = null;

            return null;
        }

        $culture = $this->resolveCulture([]);

        $parent = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function ($join) use ($culture) {
                $join->on('io.id', '=', 'i18n.id')
                    ->where('i18n.culture', '=', $culture);
            })
            ->leftJoin('slug', 'slug.object_id', '=', 'io.id')
            ->where('io.id', $parentId)
            ->select([
                'io.id', 'io.parent_id', 'io.lft', 'io.rgt',
                'io.level_of_description_id', 'io.identifier',
                'io.source_culture',
                'i18n.title', 'i18n.culture',
                'slug.slug',
            ])
            ->first();

        $this->parentResource = $parent ? new self($parent) : null;

        return $this->parentResource;
    }

    /**
     * Get level of description as an object with a name property.
     */
    public function getLevelOfDescription(): ?object
    {
        $lodId = $this->data->level_of_description_id ?? $this->data->levelOfDescriptionId ?? null;
        if (!$lodId) {
            return null;
        }

        $culture = $this->resolveCulture([]);

        $term = DB::table('term_i18n')
            ->where('id', $lodId)
            ->where('culture', $culture)
            ->value('name');

        if (!$term) {
            $term = DB::table('term_i18n')
                ->where('id', $lodId)
                ->value('name');
        }

        return $term ? new LightweightObject((object) ['id' => $lodId, 'name' => $term]) : null;
    }

    /**
     * Get the digital object related to this resource.
     * Returns a LightweightDigitalObject with __call support for getName() etc.
     */
    public function getDigitalObject()
    {
        $id = $this->data->id ?? null;
        if (!$id) {
            return null;
        }

        $row = DB::table('digital_object')
            ->where('object_id', $id)
            ->orderBy('id', 'asc')
            ->first();

        if (!$row) {
            return null;
        }

        // Add camelCase aliases for template compatibility
        $row->mimeType = $row->mime_type ?? null;
        $row->mediaTypeId = $row->media_type_id ?? null;
        $row->byteSize = $row->byte_size ?? null;
        $row->checksumType = $row->checksum_type ?? null;
        $row->objectId = $row->object_id ?? null;
        $row->usageId = $row->usage_id ?? null;
        $row->parentId = $row->parent_id ?? null;

        return new LightweightDigitalObject($row);
    }

    /**
     * Get the i18n title with culture fallback.
     */
    public function getTitle(array $options = []): ?string
    {
        // Direct property first
        if (!empty($this->data->title)) {
            return $this->data->title;
        }

        $id = $this->data->id ?? null;
        if (!$id) {
            return null;
        }

        $culture = $this->resolveCulture($options);

        $title = DB::table('information_object_i18n')
            ->where('id', $id)
            ->where('culture', $culture)
            ->value('title');

        if (!$title && !empty($options['cultureFallback'])) {
            $srcCulture = $this->data->source_culture ?? null;
            if ($srcCulture && $srcCulture !== $culture) {
                $title = DB::table('information_object_i18n')
                    ->where('id', $id)
                    ->where('culture', $srcCulture)
                    ->value('title');
            }
        }

        return $title;
    }

    /**
     * Resolve culture from options or global config.
     */
    private function resolveCulture(array $options): string
    {
        if (!empty($options['culture'])) {
            return $options['culture'];
        }

        if (class_exists('sfContext', false) && \sfContext::hasInstance()) {
            try {
                return \sfContext::getInstance()->getUser()->getCulture();
            } catch (\Exception $e) {
                // fallback
            }
        }

        return $this->data->source_culture ?? $this->data->culture ?? 'en';
    }
}
