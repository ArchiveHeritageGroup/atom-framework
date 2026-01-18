<?php
declare(strict_types=1);

namespace AtomFramework\Services;

use AtomExtensions\Helpers\CultureHelper;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

/**
 * Service for managing IIIF Collections (manifest groupings).
 * Supports IIIF Presentation API 3.0 collection format.
 */
class IiifCollectionService
{
    private Logger $logger;
    private string $baseUrl;

    public function __construct()
    {
        $this->logger = new Logger('iiif-collection');
        $this->logger->pushHandler(new RotatingFileHandler(
            '/var/log/atom/iiif-collection.log',
            30,
            Logger::INFO
        ));
        $this->baseUrl = \sfConfig::get('app_siteBaseUrl', 'https://archives.theahg.co.za');
    }

    /**
     * Get all collections (optionally filtered by parent).
     */
    public function getAllCollections(?int $parentId = null, bool $publicOnly = false): array
    {
        $query = DB::table('iiif_collection as c')
            ->leftJoin('iiif_collection_i18n as i18n', function ($join) {
                $join->on('c.id', '=', 'i18n.collection_id')
                    ->where('i18n.culture', '=', CultureHelper::getCulture());
            })
            ->select(
                'c.*',
                'i18n.name as i18n_name',
                'i18n.description as i18n_description'
            );

        if ($parentId === null) {
            $query->whereNull('c.parent_id');
        } else {
            $query->where('c.parent_id', $parentId);
        }

        if ($publicOnly) {
            $query->where('c.is_public', 1);
        }

        return $query->orderBy('c.sort_order')->orderBy('c.name')->get()->map(function ($c) {
            $c->display_name = $c->i18n_name ?: $c->name;
            $c->display_description = $c->i18n_description ?: $c->description;
            $c->item_count = $this->getItemCount($c->id);
            return $c;
        })->all();
    }

    /**
     * Get a single collection by ID or slug.
     */
    public function getCollection($identifier): ?object
    {
        $query = DB::table('iiif_collection as c')
            ->leftJoin('iiif_collection_i18n as i18n', function ($join) {
                $join->on('c.id', '=', 'i18n.collection_id')
                    ->where('i18n.culture', '=', CultureHelper::getCulture());
            })
            ->select(
                'c.*',
                'i18n.name as i18n_name',
                'i18n.description as i18n_description'
            );

        if (is_numeric($identifier)) {
            $query->where('c.id', $identifier);
        } else {
            $query->where('c.slug', $identifier);
        }

        $collection = $query->first();

        if ($collection) {
            $collection->display_name = $collection->i18n_name ?: $collection->name;
            $collection->display_description = $collection->i18n_description ?: $collection->description;
            $collection->items = $this->getCollectionItems($collection->id);
            $collection->subcollections = $this->getAllCollections($collection->id);
        }

        return $collection;
    }

    /**
     * Get items in a collection.
     */
    public function getCollectionItems(int $collectionId): array
    {
        return DB::table('iiif_collection_item as ci')
            ->leftJoin('information_object as io', 'ci.object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as i18n', function ($join) {
                $join->on('io.id', '=', 'i18n.id')
                    ->where('i18n.culture', '=', CultureHelper::getCulture());
            })
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('ci.collection_id', $collectionId)
            ->select(
                'ci.*',
                'io.identifier',
                'i18n.title as object_title',
                'slug.slug'
            )
            ->orderBy('ci.sort_order')
            ->get()
            ->all();
    }

    /**
     * Get item count for a collection.
     */
    public function getItemCount(int $collectionId): int
    {
        return DB::table('iiif_collection_item')
            ->where('collection_id', $collectionId)
            ->count();
    }

    /**
     * Create a new collection.
     */
    public function createCollection(array $data): int
    {
        $slug = $this->generateSlug($data['name']);

        $id = DB::table('iiif_collection')->insertGetId([
            'name' => $data['name'],
            'slug' => $slug,
            'description' => $data['description'] ?? null,
            'attribution' => $data['attribution'] ?? null,
            'logo_url' => $data['logo_url'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'viewing_hint' => $data['viewing_hint'] ?? 'individuals',
            'parent_id' => $data['parent_id'] ?? null,
            'is_public' => $data['is_public'] ?? 1,
            'created_by' => $data['created_by'] ?? null,
        ]);

        $this->logger->info('Collection created', ['id' => $id, 'name' => $data['name']]);

        return $id;
    }

    /**
     * Update a collection.
     */
    public function updateCollection(int $id, array $data): bool
    {
        $update = [];

        if (isset($data['name'])) {
            $update['name'] = $data['name'];
        }
        if (isset($data['description'])) {
            $update['description'] = $data['description'];
        }
        if (isset($data['attribution'])) {
            $update['attribution'] = $data['attribution'];
        }
        if (isset($data['logo_url'])) {
            $update['logo_url'] = $data['logo_url'];
        }
        if (isset($data['thumbnail_url'])) {
            $update['thumbnail_url'] = $data['thumbnail_url'];
        }
        if (isset($data['viewing_hint'])) {
            $update['viewing_hint'] = $data['viewing_hint'];
        }
        if (isset($data['parent_id'])) {
            $update['parent_id'] = $data['parent_id'];
        }
        if (isset($data['is_public'])) {
            $update['is_public'] = $data['is_public'];
        }

        if (!empty($update)) {
            DB::table('iiif_collection')->where('id', $id)->update($update);
            $this->logger->info('Collection updated', ['id' => $id]);
            return true;
        }

        return false;
    }

    /**
     * Delete a collection.
     */
    public function deleteCollection(int $id): bool
    {
        DB::table('iiif_collection')->where('id', $id)->delete();
        $this->logger->info('Collection deleted', ['id' => $id]);
        return true;
    }

    /**
     * Add an item (manifest) to a collection.
     */
    public function addItem(int $collectionId, array $data): int
    {
        $maxOrder = DB::table('iiif_collection_item')
            ->where('collection_id', $collectionId)
            ->max('sort_order') ?? 0;

        return DB::table('iiif_collection_item')->insertGetId([
            'collection_id' => $collectionId,
            'object_id' => $data['object_id'] ?? null,
            'manifest_uri' => $data['manifest_uri'] ?? null,
            'item_type' => $data['item_type'] ?? 'manifest',
            'label' => $data['label'] ?? null,
            'description' => $data['description'] ?? null,
            'thumbnail_url' => $data['thumbnail_url'] ?? null,
            'sort_order' => $data['sort_order'] ?? ($maxOrder + 1),
        ]);
    }

    /**
     * Remove an item from a collection.
     */
    public function removeItem(int $itemId): bool
    {
        return DB::table('iiif_collection_item')->where('id', $itemId)->delete() > 0;
    }

    /**
     * Reorder items in a collection.
     */
    public function reorderItems(int $collectionId, array $itemIds): bool
    {
        foreach ($itemIds as $order => $itemId) {
            DB::table('iiif_collection_item')
                ->where('id', $itemId)
                ->where('collection_id', $collectionId)
                ->update(['sort_order' => $order]);
        }
        return true;
    }

    /**
     * Generate IIIF Collection JSON (Presentation API 3.0).
     */
    public function generateCollectionJson(int $collectionId): array
    {
        $collection = $this->getCollection($collectionId);
        if (!$collection) {
            throw new \Exception('Collection not found');
        }

        $json = [
            '@context' => 'http://iiif.io/api/presentation/3/context.json',
            'id' => $this->baseUrl . '/iiif/collection/' . $collection->slug,
            'type' => 'Collection',
            'label' => ['en' => [$collection->display_name]],
        ];

        if ($collection->display_description) {
            $json['summary'] = ['en' => [$collection->display_description]];
        }

        if ($collection->attribution) {
            $json['requiredStatement'] = [
                'label' => ['en' => ['Attribution']],
                'value' => ['en' => [$collection->attribution]],
            ];
        }

        if ($collection->logo_url) {
            $json['logo'] = [
                [
                    'id' => $collection->logo_url,
                    'type' => 'Image',
                ],
            ];
        }

        if ($collection->thumbnail_url) {
            $json['thumbnail'] = [
                [
                    'id' => $collection->thumbnail_url,
                    'type' => 'Image',
                ],
            ];
        }

        if ($collection->viewing_hint) {
            $json['behavior'] = [$collection->viewing_hint];
        }

        // Add items
        $json['items'] = [];

        // Add subcollections first
        foreach ($collection->subcollections as $sub) {
            $json['items'][] = [
                'id' => $this->baseUrl . '/iiif/collection/' . $sub->slug,
                'type' => 'Collection',
                'label' => ['en' => [$sub->display_name]],
            ];
        }

        // Add manifests
        foreach ($collection->items as $item) {
            if ($item->item_type === 'collection') {
                $json['items'][] = [
                    'id' => $item->manifest_uri,
                    'type' => 'Collection',
                    'label' => ['en' => [$item->label ?: 'Collection']],
                ];
            } else {
                $manifestUri = $item->manifest_uri;
                if (!$manifestUri && $item->slug) {
                    $manifestUri = $this->baseUrl . '/iiif-manifest.php?slug=' . $item->slug;
                }

                $manifestItem = [
                    'id' => $manifestUri,
                    'type' => 'Manifest',
                    'label' => ['en' => [$item->label ?: $item->object_title ?: 'Untitled']],
                ];

                if ($item->thumbnail_url) {
                    $manifestItem['thumbnail'] = [
                        [
                            'id' => $item->thumbnail_url,
                            'type' => 'Image',
                        ],
                    ];
                }

                $json['items'][] = $manifestItem;
            }
        }

        return $json;
    }

    /**
     * Search collections.
     */
    public function searchCollections(string $query, bool $publicOnly = true): array
    {
        $builder = DB::table('iiif_collection as c')
            ->leftJoin('iiif_collection_i18n as i18n', function ($join) {
                $join->on('c.id', '=', 'i18n.collection_id')
                    ->where('i18n.culture', '=', CultureHelper::getCulture());
            })
            ->where(function ($q) use ($query) {
                $q->where('c.name', 'LIKE', "%{$query}%")
                    ->orWhere('c.description', 'LIKE', "%{$query}%")
                    ->orWhere('i18n.name', 'LIKE', "%{$query}%")
                    ->orWhere('i18n.description', 'LIKE', "%{$query}%");
            })
            ->select('c.*', 'i18n.name as i18n_name', 'i18n.description as i18n_description');

        if ($publicOnly) {
            $builder->where('c.is_public', 1);
        }

        return $builder->orderBy('c.name')->get()->all();
    }

    /**
     * Get collections containing a specific object.
     */
    public function getCollectionsForObject(int $objectId): array
    {
        return DB::table('iiif_collection as c')
            ->join('iiif_collection_item as ci', 'c.id', '=', 'ci.collection_id')
            ->where('ci.object_id', $objectId)
            ->select('c.*')
            ->distinct()
            ->get()
            ->all();
    }

    /**
     * Generate unique slug.
     */
    private function generateSlug(string $name): string
    {
        $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name), '-'));
        $baseSlug = $slug;
        $counter = 1;

        while (DB::table('iiif_collection')->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }
}
