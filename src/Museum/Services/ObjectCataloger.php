<?php

namespace AtomFramework\Museum\Services;

use AtomFramework\Core\Database\DatabaseManager;
use AtomFramework\Museum\Contracts\ObjectCatalogerInterface;
use AtomFramework\Museum\Models\MuseumObject;
use Psr\Log\LoggerInterface;

class ObjectCataloger implements ObjectCatalogerInterface
{
    private DatabaseManager $db;
    private LoggerInterface $logger;

    public function __construct(DatabaseManager $db, LoggerInterface $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    public function create(int $informationObjectId, array $properties): MuseumObject
    {
        $this->logger->info('Creating museum object record', [
            'information_object_id' => $informationObjectId,
        ]);

        // Ensure required fields
        $properties['information_object_id'] = $informationObjectId;

        // Convert arrays to JSON for storage
        $data = $this->prepareForStorage($properties);

        // Add timestamps
        $now = date('Y-m-d H:i:s');
        $data['created_at'] = $now;
        $data['updated_at'] = $now;

        try {
            $id = $this->db->table('museum_object_properties')->insertGetId($data);

            $this->logger->info('Museum object created successfully', [
                'id' => $id,
                'information_object_id' => $informationObjectId,
            ]);

            // Fetch and return the created object
            return $this->findById($id);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create museum object', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);

            throw $e;
        }
    }

    public function update(int $id, array $properties): MuseumObject
    {
        $this->logger->info('Updating museum object record', [
            'id' => $id,
        ]);

        // Prepare data for storage
        $data = $this->prepareForStorage($properties);

        // Update timestamp
        $data['updated_at'] = date('Y-m-d H:i:s');

        try {
            $updated = $this->db->table('museum_object_properties')
                ->where('id', $id)
                ->update($data);

            if (!$updated) {
                $this->logger->warning('No rows updated', ['id' => $id]);
            }

            $this->logger->info('Museum object updated successfully', [
                'id' => $id,
                'updated_fields' => array_keys($properties),
            ]);

            // Fetch and return the updated object
            return $this->findById($id);
        } catch (\Exception $e) {
            $this->logger->error('Failed to update museum object', [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data,
            ]);

            throw $e;
        }
    }

    public function findByInformationObjectId(int $informationObjectId): ?MuseumObject
    {
        $this->logger->debug('Finding museum object by information object ID', [
            'information_object_id' => $informationObjectId,
        ]);

        try {
            $data = $this->db->table('museum_object_properties')
                ->where('information_object_id', $informationObjectId)
                ->first();

            if (!$data) {
                $this->logger->debug('No museum object found', [
                    'information_object_id' => $informationObjectId,
                ]);

                return null;
            }

            // Convert stdClass to array
            $data = (array) $data;

            return new MuseumObject($data);
        } catch (\Exception $e) {
            $this->logger->error('Error finding museum object', [
                'error' => $e->getMessage(),
                'information_object_id' => $informationObjectId,
            ]);

            throw $e;
        }
    }

    public function delete(int $id): bool
    {
        $this->logger->info('Deleting museum object record', [
            'id' => $id,
        ]);

        try {
            $deleted = $this->db->table('museum_object_properties')
                ->where('id', $id)
                ->delete();

            if ($deleted) {
                $this->logger->info('Museum object deleted successfully', [
                    'id' => $id,
                ]);

                return true;
            }

            $this->logger->warning('Museum object not found for deletion', [
                'id' => $id,
            ]);

            return false;
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete museum object', [
                'error' => $e->getMessage(),
                'id' => $id,
            ]);

            throw $e;
        }
    }

    /**
     * Find museum object by ID.
     *
     * @param int $id
     *
     * @return MuseumObject
     *
     * @throws \Exception if not found
     */
    public function findById(int $id): MuseumObject
    {
        $this->logger->debug('Finding museum object by ID', ['id' => $id]);

        $data = $this->db->table('museum_object_properties')
            ->where('id', $id)
            ->first();

        if (!$data) {
            throw new \Exception("Museum object not found with ID: {$id}");
        }

        // Convert stdClass to array
        $data = (array) $data;

        return new MuseumObject($data);
    }

    /**
     * Get all museum objects for a repository.
     *
     * @param int|null $repositoryId
     * @param int      $limit
     * @param int      $offset
     *
     * @return array
     */
    public function findAll(?int $repositoryId = null, int $limit = 100, int $offset = 0): array
    {
        $this->logger->debug('Finding all museum objects', [
            'repository_id' => $repositoryId,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        try {
            $query = $this->db->table('museum_object_properties as mop')
                ->join('information_object as io', 'mop.information_object_id', '=', 'io.id')
                ->select('mop.*');

            if ($repositoryId !== null) {
                $query->where('io.repository_id', $repositoryId);
            }

            $results = $query->limit($limit)
                ->offset($offset)
                ->get();

            $objects = [];
            foreach ($results as $data) {
                $data = (array) $data;
                $objects[] = new MuseumObject($data);
            }

            $this->logger->debug('Found museum objects', [
                'count' => count($objects),
            ]);

            return $objects;
        } catch (\Exception $e) {
            $this->logger->error('Error finding museum objects', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Search museum objects by work type.
     *
     * @param string $workType
     * @param int    $limit
     * @param int    $offset
     *
     * @return array
     */
    public function findByWorkType(string $workType, int $limit = 100, int $offset = 0): array
    {
        $this->logger->debug('Finding museum objects by work type', [
            'work_type' => $workType,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        try {
            $results = $this->db->table('museum_object_properties')
                ->where('work_type', $workType)
                ->limit($limit)
                ->offset($offset)
                ->get();

            $objects = [];
            foreach ($results as $data) {
                $data = (array) $data;
                $objects[] = new MuseumObject($data);
            }

            return $objects;
        } catch (\Exception $e) {
            $this->logger->error('Error finding museum objects by work type', [
                'error' => $e->getMessage(),
                'work_type' => $workType,
            ]);

            throw $e;
        }
    }

    /**
     * Search museum objects by material.
     *
     * @param string $material
     * @param int    $limit
     * @param int    $offset
     *
     * @return array
     */
    public function findByMaterial(string $material, int $limit = 100, int $offset = 0): array
    {
        $this->logger->debug('Finding museum objects by material', [
            'material' => $material,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        try {
            // Search in JSON field - MySQL JSON_CONTAINS
            $results = $this->db->table('museum_object_properties')
                ->whereRaw('JSON_CONTAINS(materials, ?)', [json_encode($material)])
                ->limit($limit)
                ->offset($offset)
                ->get();

            $objects = [];
            foreach ($results as $data) {
                $data = (array) $data;
                $objects[] = new MuseumObject($data);
            }

            return $objects;
        } catch (\Exception $e) {
            $this->logger->error('Error finding museum objects by material', [
                'error' => $e->getMessage(),
                'material' => $material,
            ]);

            throw $e;
        }
    }

    /**
     * Count museum objects.
     *
     * @param int|null $repositoryId
     *
     * @return int
     */
    public function count(?int $repositoryId = null): int
    {
        try {
            $query = $this->db->table('museum_object_properties as mop')
                ->join('information_object as io', 'mop.information_object_id', '=', 'io.id');

            if ($repositoryId !== null) {
                $query->where('io.repository_id', $repositoryId);
            }

            return $query->count();
        } catch (\Exception $e) {
            $this->logger->error('Error counting museum objects', [
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Check if a museum object exists for an information object.
     *
     * @param int $informationObjectId
     *
     * @return bool
     */
    public function exists(int $informationObjectId): bool
    {
        try {
            $count = $this->db->table('museum_object_properties')
                ->where('information_object_id', $informationObjectId)
                ->count();

            return $count > 0;
        } catch (\Exception $e) {
            $this->logger->error('Error checking museum object existence', [
                'error' => $e->getMessage(),
                'information_object_id' => $informationObjectId,
            ]);

            return false;
        }
    }

    /**
     * Prepare data for database storage.
     *
     * @param array $properties
     *
     * @return array
     */
    private function prepareForStorage(array $properties): array
    {
        $data = [];

        // Copy scalar fields directly
        $scalarFields = [
            'information_object_id',
            'work_type',
            'creation_date_earliest',
            'creation_date_latest',
            'inscription',
            'condition_notes',
            'provenance',
            'style_period',
            'cultural_context',
        ];

        foreach ($scalarFields as $field) {
            if (isset($properties[$field])) {
                $data[$field] = $properties[$field];
            }
        }

        // Convert array fields to JSON
        $arrayFields = ['materials', 'techniques', 'measurements'];

        foreach ($arrayFields as $field) {
            if (isset($properties[$field])) {
                if (is_array($properties[$field])) {
                    $data[$field] = json_encode($properties[$field]);
                } else {
                    $data[$field] = $properties[$field];
                }
            }
        }

        return $data;
    }
}
