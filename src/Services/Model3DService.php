<?php

declare(strict_types=1);

namespace AtomFramework\Services;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

/**
 * Model3DService
 * 
 * Handles 3D model operations including upload, viewing, hotspots, and IIIF 3D manifest generation
 * 
 * @package AtomFramework\Services
 * @author Johan Pieterse - The Archive and Heritage Group
 */
class Model3DService
{
    private Logger $logger;
    private array $settings = [];
    
    // Supported 3D formats with MIME types
    private const SUPPORTED_FORMATS = [
        'glb' => 'model/gltf-binary',
        'gltf' => 'model/gltf+json',
        'obj' => 'model/obj',
        'stl' => 'model/stl',
        'fbx' => 'application/octet-stream',
        'ply' => 'application/x-ply',
        'usdz' => 'model/vnd.usdz+zip',
    ];
    
    // Hotspot type colors
    private const HOTSPOT_COLORS = [
        'annotation' => '#1a73e8',
        'info' => '#34a853',
        'link' => '#4285f4',
        'damage' => '#ea4335',
        'detail' => '#fbbc04',
    ];
    
    public function __construct()
    {
        $this->initLogger();
        $this->loadSettings();
    }
    
    private function initLogger(): void
    {
        $this->logger = new Logger('model3d');
        $logPath = '/var/log/atom/model3d.log';
        
        if (!file_exists(dirname($logPath))) {
            mkdir(dirname($logPath), 0755, true);
        }
        
        $this->logger->pushHandler(new RotatingFileHandler($logPath, 30, Logger::DEBUG));
    }
    
    private function loadSettings(): void
    {
        try {
            $settings = DB::table('viewer_3d_settings')->get();
            foreach ($settings as $setting) {
                $value = $setting->setting_value;
                if ($setting->setting_type === 'boolean') {
                    $value = (bool)$value;
                } elseif ($setting->setting_type === 'integer') {
                    $value = (int)$value;
                } elseif ($setting->setting_type === 'json') {
                    $value = json_decode($value, true);
                }
                $this->settings[$setting->setting_key] = $value;
            }
        } catch (\Exception $e) {
            $this->logger->warning('Could not load 3D settings: ' . $e->getMessage());
            $this->settings = $this->getDefaultSettings();
        }
    }
    
    private function getDefaultSettings(): array
    {
        return [
            'default_viewer' => 'model-viewer',
            'enable_ar' => true,
            'enable_fullscreen' => true,
            'enable_download' => false,
            'default_background' => '#f5f5f5',
            'default_exposure' => '1.0',
            'default_shadow_intensity' => '1.0',
            'max_file_size_mb' => 100,
            'allowed_formats' => ['glb', 'gltf', 'usdz'],
            'enable_annotations' => true,
            'enable_auto_rotate' => true,
            'rotation_speed' => 30,
        ];
    }
    
    public function getSetting(string $key, $default = null)
    {
        return $this->settings[$key] ?? $default;
    }
    
    public function updateSetting(string $key, $value, string $type = 'string'): bool
    {
        try {
            if ($type === 'json' && is_array($value)) {
                $value = json_encode($value);
            } elseif ($type === 'boolean') {
                $value = $value ? '1' : '0';
            }
            
            DB::table('viewer_3d_settings')
                ->updateOrInsert(
                    ['setting_key' => $key],
                    ['setting_value' => $value, 'setting_type' => $type]
                );
            
            $this->loadSettings();
            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to update setting: ' . $e->getMessage());
            return false;
        }
    }
    
    // ===== MODEL CRUD OPERATIONS =====
    
    /**
     * Get all 3D models for an information object
     */
    public function getModelsForObject(int $objectId, string $culture = 'en'): array
    {
        return DB::table('object_3d_model as m')
            ->leftJoin('object_3d_model_i18n as i18n', function($join) use ($culture) {
                $join->on('m.id', '=', 'i18n.model_id')
                     ->where('i18n.culture', '=', $culture);
            })
            ->where('m.object_id', $objectId)
            ->where('m.is_public', 1)
            ->orderBy('m.display_order')
            ->orderBy('m.is_primary', 'desc')
            ->select(
                'm.*',
                'i18n.title',
                'i18n.description',
                'i18n.alt_text'
            )
            ->get()
            ->toArray();
    }
    
    /**
     * Get a single 3D model by ID
     */
    public function getModel(int $modelId, string $culture = 'en'): ?object
    {
        return DB::table('object_3d_model as m')
            ->leftJoin('object_3d_model_i18n as i18n', function($join) use ($culture) {
                $join->on('m.id', '=', 'i18n.model_id')
                     ->where('i18n.culture', '=', $culture);
            })
            ->where('m.id', $modelId)
            ->select(
                'm.*',
                'i18n.title',
                'i18n.description',
                'i18n.alt_text'
            )
            ->first();
    }
    
    /**
     * Get primary 3D model for an object
     */
    public function getPrimaryModel(int $objectId, string $culture = 'en'): ?object
    {
        return DB::table('object_3d_model as m')
            ->leftJoin('object_3d_model_i18n as i18n', function($join) use ($culture) {
                $join->on('m.id', '=', 'i18n.model_id')
                     ->where('i18n.culture', '=', $culture);
            })
            ->where('m.object_id', $objectId)
            ->where('m.is_public', 1)
            ->orderBy('m.is_primary', 'desc')
            ->orderBy('m.display_order')
            ->select(
                'm.*',
                'i18n.title',
                'i18n.description',
                'i18n.alt_text'
            )
            ->first();
    }
    
    /**
     * Upload and save a new 3D model
     */
    public function uploadModel(array $data, array $file, int $userId): ?int
    {
        try {
            // Validate file
            $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($extension, $this->getSetting('allowed_formats', ['glb', 'gltf']))) {
                throw new \InvalidArgumentException("Unsupported format: {$extension}");
            }
            
            $maxSize = $this->getSetting('max_file_size_mb', 100) * 1024 * 1024;
            if ($file['size'] > $maxSize) {
                throw new \InvalidArgumentException("File too large. Maximum: {$this->getSetting('max_file_size_mb')}MB");
            }
            
            // Generate storage path
            $objectId = $data['object_id'];
            $hash = md5($file['name'] . time());
            $filename = $hash . '.' . $extension;
            $relativePath = "3d/{$objectId}/{$filename}";
            $fullPath = sfConfig::get('sf_upload_dir') . '/' . $relativePath;
            
            // Create directory
            $dir = dirname($fullPath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $fullPath)) {
                throw new \RuntimeException('Failed to move uploaded file');
            }
            
            // Analyze model (basic metadata)
            $metadata = $this->analyzeModel($fullPath, $extension);
            
            // Insert into database
            $modelId = DB::table('object_3d_model')->insertGetId([
                'object_id' => $objectId,
                'filename' => $filename,
                'original_filename' => $file['name'],
                'file_path' => $relativePath,
                'file_size' => $file['size'],
                'mime_type' => self::SUPPORTED_FORMATS[$extension] ?? 'application/octet-stream',
                'format' => $extension,
                'vertex_count' => $metadata['vertices'] ?? null,
                'face_count' => $metadata['faces'] ?? null,
                'texture_count' => $metadata['textures'] ?? 0,
                'animation_count' => $metadata['animations'] ?? 0,
                'auto_rotate' => $this->getSetting('enable_auto_rotate', true) ? 1 : 0,
                'background_color' => $this->getSetting('default_background', '#f5f5f5'),
                'exposure' => $this->getSetting('default_exposure', 1.0),
                'ar_enabled' => $this->getSetting('enable_ar', true) ? 1 : 0,
                'is_primary' => $data['is_primary'] ?? 0,
                'is_public' => $data['is_public'] ?? 1,
                'created_by' => $userId,
                'updated_by' => $userId,
            ]);
            
            // Add translation
            if (!empty($data['title']) || !empty($data['description'])) {
                DB::table('object_3d_model_i18n')->insert([
                    'model_id' => $modelId,
                    'culture' => $data['culture'] ?? 'en',
                    'title' => $data['title'] ?? $file['name'],
                    'description' => $data['description'] ?? null,
                    'alt_text' => $data['alt_text'] ?? null,
                ]);
            }
            
            // Generate poster image if enabled
            if ($this->getSetting('poster_auto_generate', true)) {
                // Note: Poster generation would require a headless browser or 3D rendering service
                // This is a placeholder for future implementation
            }
            
            // Log action
            $this->logAction($modelId, $objectId, $userId, 'upload', [
                'filename' => $file['name'],
                'size' => $file['size'],
                'format' => $extension,
            ]);
            
            $this->logger->info("Uploaded 3D model: {$modelId} for object: {$objectId}");
            
            return $modelId;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to upload 3D model: ' . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Update a 3D model's settings
     */
    public function updateModel(int $modelId, array $data, int $userId): bool
    {
        try {
            $updates = [];
            
            // Model settings
            $allowedFields = [
                'auto_rotate', 'rotation_speed', 'camera_orbit', 'min_camera_orbit',
                'max_camera_orbit', 'field_of_view', 'exposure', 'shadow_intensity',
                'shadow_softness', 'environment_image', 'skybox_image', 'background_color',
                'ar_enabled', 'ar_scale', 'ar_placement', 'is_primary', 'is_public', 'display_order'
            ];
            
            foreach ($allowedFields as $field) {
                if (isset($data[$field])) {
                    $updates[$field] = $data[$field];
                }
            }
            
            $updates['updated_by'] = $userId;
            $updates['updated_at'] = DB::raw('NOW()');
            
            DB::table('object_3d_model')
                ->where('id', $modelId)
                ->update($updates);
            
            // Update translation
            if (isset($data['title']) || isset($data['description']) || isset($data['alt_text'])) {
                $culture = $data['culture'] ?? 'en';
                DB::table('object_3d_model_i18n')
                    ->updateOrInsert(
                        ['model_id' => $modelId, 'culture' => $culture],
                        [
                            'title' => $data['title'] ?? null,
                            'description' => $data['description'] ?? null,
                            'alt_text' => $data['alt_text'] ?? null,
                        ]
                    );
            }
            
            // Clear manifest cache
            $this->clearManifestCache($modelId);
            
            // Log action
            $model = $this->getModel($modelId);
            $this->logAction($modelId, $model->object_id ?? null, $userId, 'update', $updates);
            
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to update 3D model: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete a 3D model
     */
    public function deleteModel(int $modelId, int $userId): bool
    {
        try {
            $model = $this->getModel($modelId);
            if (!$model) {
                return false;
            }
            
            // Delete file
            $fullPath = sfConfig::get('sf_upload_dir') . '/' . $model->file_path;
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            
            // Delete poster/thumbnail
            if ($model->poster_image && file_exists(sfConfig::get('sf_upload_dir') . '/' . $model->poster_image)) {
                unlink(sfConfig::get('sf_upload_dir') . '/' . $model->poster_image);
            }
            
            // Delete from database (cascades to i18n, hotspots, textures)
            DB::table('object_3d_model')->where('id', $modelId)->delete();
            
            // Log action
            $this->logAction($modelId, $model->object_id, $userId, 'delete', [
                'filename' => $model->original_filename,
            ]);
            
            $this->logger->info("Deleted 3D model: {$modelId}");
            
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete 3D model: ' . $e->getMessage());
            return false;
        }
    }
    
    // ===== HOTSPOT OPERATIONS =====
    
    /**
     * Get all hotspots for a model
     */
    public function getHotspots(int $modelId, string $culture = 'en'): array
    {
        return DB::table('object_3d_hotspot as h')
            ->leftJoin('object_3d_hotspot_i18n as i18n', function($join) use ($culture) {
                $join->on('h.id', '=', 'i18n.hotspot_id')
                     ->where('i18n.culture', '=', $culture);
            })
            ->where('h.model_id', $modelId)
            ->where('h.is_visible', 1)
            ->orderBy('h.display_order')
            ->select(
                'h.*',
                'i18n.title',
                'i18n.description'
            )
            ->get()
            ->toArray();
    }
    
    /**
     * Add a hotspot to a model
     */
    public function addHotspot(int $modelId, array $data, int $userId): ?int
    {
        try {
            $hotspotId = DB::table('object_3d_hotspot')->insertGetId([
                'model_id' => $modelId,
                'hotspot_type' => $data['hotspot_type'] ?? 'annotation',
                'position_x' => $data['position_x'],
                'position_y' => $data['position_y'],
                'position_z' => $data['position_z'],
                'normal_x' => $data['normal_x'] ?? 0,
                'normal_y' => $data['normal_y'] ?? 1,
                'normal_z' => $data['normal_z'] ?? 0,
                'icon' => $data['icon'] ?? 'info',
                'color' => $data['color'] ?? self::HOTSPOT_COLORS[$data['hotspot_type'] ?? 'annotation'],
                'link_url' => $data['link_url'] ?? null,
                'link_target' => $data['link_target'] ?? '_blank',
                'display_order' => $data['display_order'] ?? 0,
            ]);
            
            // Add translation
            if (!empty($data['title']) || !empty($data['description'])) {
                DB::table('object_3d_hotspot_i18n')->insert([
                    'hotspot_id' => $hotspotId,
                    'culture' => $data['culture'] ?? 'en',
                    'title' => $data['title'] ?? null,
                    'description' => $data['description'] ?? null,
                ]);
            }
            
            // Clear manifest cache
            $this->clearManifestCache($modelId);
            
            // Log action
            $model = $this->getModel($modelId);
            $this->logAction($modelId, $model->object_id ?? null, $userId, 'hotspot_add', [
                'hotspot_id' => $hotspotId,
                'type' => $data['hotspot_type'] ?? 'annotation',
            ]);
            
            return $hotspotId;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to add hotspot: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Delete a hotspot
     */
    public function deleteHotspot(int $hotspotId, int $userId): bool
    {
        try {
            $hotspot = DB::table('object_3d_hotspot')->where('id', $hotspotId)->first();
            if (!$hotspot) {
                return false;
            }
            
            DB::table('object_3d_hotspot')->where('id', $hotspotId)->delete();
            
            // Clear manifest cache
            $this->clearManifestCache($hotspot->model_id);
            
            // Log action
            $model = $this->getModel($hotspot->model_id);
            $this->logAction($hotspot->model_id, $model->object_id ?? null, $userId, 'hotspot_delete', [
                'hotspot_id' => $hotspotId,
            ]);
            
            return true;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete hotspot: ' . $e->getMessage());
            return false;
        }
    }
    
    // ===== IIIF 3D MANIFEST GENERATION =====
    
    /**
     * Generate IIIF 3D manifest for a model
     * Based on IIIF 3D TSG (Technical Specification Group) draft
     */
    public function generateIiif3DManifest(int $modelId, string $baseUrl, string $culture = 'en'): ?array
    {
        try {
            $model = $this->getModel($modelId, $culture);
            if (!$model) {
                return null;
            }
            
            // Check cache
            $cached = DB::table('iiif_3d_manifest')
                ->where('model_id', $modelId)
                ->first();
            
            if ($cached && $cached->manifest_json) {
                $cachedManifest = json_decode($cached->manifest_json, true);
                // Validate cache is still current
                if ($cachedManifest && $cached->manifest_hash === md5(json_encode($model))) {
                    return $cachedManifest;
                }
            }
            
            $hotspots = $this->getHotspots($modelId, $culture);
            
            // Build IIIF 3D manifest (draft spec based on IIIF Presentation API 3.0)
            $manifest = [
                '@context' => [
                    'http://iiif.io/api/presentation/3/context.json',
                    'http://iiif.io/api/extension/3d/context.json'
                ],
                'id' => "{$baseUrl}/iiif/3d/{$modelId}/manifest.json",
                'type' => 'Manifest',
                'label' => ['en' => [$model->title ?? $model->original_filename ?? 'Untitled 3D Model']],
                'metadata' => [
                    [
                        'label' => ['en' => ['Format']],
                        'value' => ['en' => [strtoupper($model->format)]]
                    ],
                    [
                        'label' => ['en' => ['File Size']],
                        'value' => ['en' => [$this->formatBytes($model->file_size)]]
                    ]
                ],
                'summary' => ['en' => [$model->description ?? '']],
                'thumbnail' => $model->thumbnail ? [
                    [
                        'id' => "{$baseUrl}/uploads/{$model->thumbnail}",
                        'type' => 'Image',
                        'format' => 'image/jpeg'
                    ]
                ] : [],
                'items' => [
                    [
                        'id' => "{$baseUrl}/iiif/3d/{$modelId}/scene/1",
                        'type' => 'Scene',
                        'label' => ['en' => ['Main Scene']],
                        'items' => [
                            [
                                'id' => "{$baseUrl}/iiif/3d/{$modelId}/annotation/1",
                                'type' => 'Annotation',
                                'motivation' => 'painting',
                                'body' => [
                                    'id' => "{$baseUrl}/uploads/{$model->file_path}",
                                    'type' => 'Model',
                                    'format' => self::SUPPORTED_FORMATS[$model->format] ?? 'model/gltf-binary',
                                    'label' => ['en' => [$model->title ?? 'Model']]
                                ],
                                'target' => "{$baseUrl}/iiif/3d/{$modelId}/scene/1"
                            ]
                        ],
                        'annotations' => []
                    ]
                ],
                'extensions' => [
                    'model-viewer' => [
                        'autoRotate' => (bool)$model->auto_rotate,
                        'rotationSpeed' => (float)$model->rotation_speed,
                        'cameraOrbit' => $model->camera_orbit,
                        'fieldOfView' => $model->field_of_view,
                        'exposure' => (float)$model->exposure,
                        'shadowIntensity' => (float)$model->shadow_intensity,
                        'backgroundColor' => $model->background_color,
                        'arEnabled' => (bool)$model->ar_enabled,
                        'arScale' => $model->ar_scale,
                        'arPlacement' => $model->ar_placement,
                    ]
                ]
            ];
            
            // Add hotspot annotations
            if (!empty($hotspots)) {
                $annotationPage = [
                    'id' => "{$baseUrl}/iiif/3d/{$modelId}/annotations/hotspots",
                    'type' => 'AnnotationPage',
                    'items' => []
                ];
                
                foreach ($hotspots as $index => $hotspot) {
                    $annotationPage['items'][] = [
                        'id' => "{$baseUrl}/iiif/3d/{$modelId}/annotation/hotspot/{$hotspot->id}",
                        'type' => 'Annotation',
                        'motivation' => 'commenting',
                        'body' => [
                            'type' => 'TextualBody',
                            'value' => $hotspot->description ?? '',
                            'format' => 'text/html',
                            'language' => $culture
                        ],
                        'target' => [
                            'type' => 'PointSelector',
                            'x' => (float)$hotspot->position_x,
                            'y' => (float)$hotspot->position_y,
                            'z' => (float)$hotspot->position_z,
                            'normal' => [
                                (float)$hotspot->normal_x,
                                (float)$hotspot->normal_y,
                                (float)$hotspot->normal_z
                            ]
                        ],
                        'label' => ['en' => [$hotspot->title ?? 'Annotation ' . ($index + 1)]],
                        'extensions' => [
                            'hotspot' => [
                                'type' => $hotspot->hotspot_type,
                                'icon' => $hotspot->icon,
                                'color' => $hotspot->color,
                                'linkUrl' => $hotspot->link_url,
                                'linkTarget' => $hotspot->link_target
                            ]
                        ]
                    ];
                }
                
                $manifest['items'][0]['annotations'][] = $annotationPage;
            }
            
            // Cache the manifest
            $hash = md5(json_encode($model));
            DB::table('iiif_3d_manifest')
                ->updateOrInsert(
                    ['model_id' => $modelId],
                    [
                        'manifest_json' => json_encode($manifest),
                        'manifest_hash' => $hash,
                        'generated_at' => DB::raw('NOW()')
                    ]
                );
            
            return $manifest;
            
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate IIIF 3D manifest: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Clear manifest cache for a model
     */
    private function clearManifestCache(int $modelId): void
    {
        DB::table('iiif_3d_manifest')->where('model_id', $modelId)->delete();
    }
    
    // ===== VIEWER RENDERING =====
    
    /**
     * Get HTML for model-viewer component (Google's WebXR viewer)
     */
    public function getModelViewerHtml(int $modelId, array $options = []): string
    {
        $model = $this->getModel($modelId);
        if (!$model) {
            return '<div class="alert alert-danger">3D model not found</div>';
        }
        
        $hotspots = $options['show_hotspots'] ?? true ? $this->getHotspots($modelId) : [];
        
        $baseUrl = $options['base_url'] ?? '';
        $modelUrl = "{$baseUrl}/uploads/{$model->file_path}";
        $posterUrl = $model->poster_image ? "{$baseUrl}/uploads/{$model->poster_image}" : '';
        $height = $options['height'] ?? '500px';
        $containerId = 'model-viewer-' . $modelId;
        
        $arAttrs = $model->ar_enabled ? 'ar ar-modes="webxr scene-viewer quick-look"' : '';
        $autoRotateAttr = $model->auto_rotate ? 'auto-rotate' : '';
        
        $html = <<<HTML
<div class="model-viewer-container" style="width:100%; height:{$height}; position:relative;">
    <model-viewer
        id="{$containerId}"
        src="{$modelUrl}"
        poster="{$posterUrl}"
        alt="{$model->alt_text}"
        camera-controls
        touch-action="pan-y"
        {$arAttrs}
        {$autoRotateAttr}
        rotation-per-second="{$model->rotation_speed}deg"
        camera-orbit="{$model->camera_orbit}"
        field-of-view="{$model->field_of_view}"
        exposure="{$model->exposure}"
        shadow-intensity="{$model->shadow_intensity}"
        shadow-softness="{$model->shadow_softness}"
        style="width:100%; height:100%; background-color:{$model->background_color};"
    >
HTML;
        
        // Add hotspots
        foreach ($hotspots as $hotspot) {
            $position = "{$hotspot->position_x}m {$hotspot->position_y}m {$hotspot->position_z}m";
            $normal = "{$hotspot->normal_x}m {$hotspot->normal_y}m {$hotspot->normal_z}m";
            $dataAttrs = '';
            
            if ($hotspot->link_url) {
                $dataAttrs = "data-link=\"{$hotspot->link_url}\" data-target=\"{$hotspot->link_target}\"";
            }
            
            $html .= <<<HTML
        <button class="hotspot" slot="hotspot-{$hotspot->id}" 
                data-position="{$position}" 
                data-normal="{$normal}"
                data-type="{$hotspot->hotspot_type}"
                style="--hotspot-color: {$hotspot->color};"
                {$dataAttrs}>
            <div class="hotspot-annotation">
                <strong>{$hotspot->title}</strong>
                <p>{$hotspot->description}</p>
            </div>
        </button>
HTML;
        }
        
        // Add controls
        $html .= <<<HTML
        <div class="model-viewer-controls">
            <button id="{$containerId}-fullscreen" class="mv-btn" title="Fullscreen">
                <i class="fas fa-expand"></i>
            </button>
HTML;
        
        if ($model->ar_enabled) {
            $html .= <<<HTML
            <button slot="ar-button" class="mv-btn mv-ar-btn" title="View in AR">
                <i class="fas fa-cube"></i> AR
            </button>
HTML;
        }
        
        $html .= <<<HTML
        </div>
        
        <div class="model-viewer-progress-bar" slot="progress-bar">
            <div class="update-bar"></div>
        </div>
    </model-viewer>
</div>

<style>
.model-viewer-container model-viewer {
    --poster-color: transparent;
}
.model-viewer-container .hotspot {
    display: block;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    border: 2px solid white;
    background-color: var(--hotspot-color, #1a73e8);
    box-shadow: 0 2px 4px rgba(0,0,0,0.3);
    cursor: pointer;
    transition: transform 0.2s;
}
.model-viewer-container .hotspot:hover {
    transform: scale(1.2);
}
.model-viewer-container .hotspot-annotation {
    display: none;
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: white;
    padding: 10px;
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    min-width: 150px;
    max-width: 250px;
    text-align: left;
    z-index: 10;
}
.model-viewer-container .hotspot:hover .hotspot-annotation {
    display: block;
}
.model-viewer-container .hotspot-annotation strong {
    display: block;
    margin-bottom: 4px;
    color: #333;
}
.model-viewer-container .hotspot-annotation p {
    margin: 0;
    font-size: 0.85em;
    color: #666;
}
.model-viewer-controls {
    position: absolute;
    bottom: 10px;
    right: 10px;
    display: flex;
    gap: 8px;
}
.mv-btn {
    width: 40px;
    height: 40px;
    border: none;
    border-radius: 4px;
    background: rgba(0,0,0,0.6);
    color: white;
    cursor: pointer;
    transition: background 0.2s;
}
.mv-btn:hover {
    background: rgba(0,0,0,0.8);
}
.mv-ar-btn {
    width: auto;
    padding: 0 12px;
}
.model-viewer-progress-bar {
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: rgba(0,0,0,0.1);
}
.model-viewer-progress-bar .update-bar {
    height: 100%;
    background: #1a73e8;
    transition: width 0.1s;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const viewer = document.getElementById('{$containerId}');
    const fullscreenBtn = document.getElementById('{$containerId}-fullscreen');
    
    // Fullscreen toggle
    fullscreenBtn?.addEventListener('click', function() {
        if (document.fullscreenElement) {
            document.exitFullscreen();
        } else {
            viewer.parentElement.requestFullscreen();
        }
    });
    
    // Hotspot link handling
    viewer.querySelectorAll('.hotspot[data-link]').forEach(hotspot => {
        hotspot.addEventListener('click', function(e) {
            e.preventDefault();
            const url = this.dataset.link;
            const target = this.dataset.target || '_blank';
            window.open(url, target);
        });
    });
});
</script>
HTML;
        
        return $html;
    }
    
    /**
     * Get HTML for Three.js fallback viewer
     */
    public function getThreeJsViewerHtml(int $modelId, array $options = []): string
    {
        $model = $this->getModel($modelId);
        if (!$model) {
            return '<div class="alert alert-danger">3D model not found</div>';
        }
        
        $baseUrl = $options['base_url'] ?? '';
        $modelUrl = "{$baseUrl}/uploads/{$model->file_path}";
        $height = $options['height'] ?? '500px';
        $containerId = 'threejs-viewer-' . $modelId;
        $bgColor = ltrim($model->background_color, '#');
        
        return <<<HTML
<div id="{$containerId}" class="threejs-viewer" style="width:100%; height:{$height}; background:#000; position:relative;">
    <div class="loading-spinner" style="position:absolute; top:50%; left:50%; transform:translate(-50%, -50%); color:white;">
        <i class="fas fa-spinner fa-spin fa-2x"></i>
        <p>Loading 3D model...</p>
    </div>
</div>

<div class="threejs-controls mt-2">
    <button class="btn btn-sm btn-outline-secondary" onclick="resetCamera_{$modelId}()">
        <i class="fas fa-sync"></i> Reset View
    </button>
    <button class="btn btn-sm btn-outline-secondary" onclick="toggleAutoRotate_{$modelId}()">
        <i class="fas fa-redo"></i> Auto-Rotate
    </button>
</div>

<script>
(function() {
    const container = document.getElementById('{$containerId}');
    const scene = new THREE.Scene();
    scene.background = new THREE.Color(0x{$bgColor});
    
    const camera = new THREE.PerspectiveCamera(60, container.clientWidth / container.clientHeight, 0.1, 1000);
    camera.position.set(2, 2, 3);
    
    const renderer = new THREE.WebGLRenderer({ antialias: true });
    renderer.setSize(container.clientWidth, container.clientHeight);
    renderer.shadowMap.enabled = true;
    container.appendChild(renderer.domElement);
    
    // Lighting
    const hemi = new THREE.HemisphereLight(0xffffff, 0x444444, 1.0);
    scene.add(hemi);
    
    const dirLight = new THREE.DirectionalLight(0xffffff, 0.8);
    dirLight.position.set(4, 10, 5);
    dirLight.castShadow = true;
    scene.add(dirLight);
    
    // Controls
    const controls = new THREE.OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.autoRotate = {$model->auto_rotate};
    controls.autoRotateSpeed = {$model->rotation_speed};
    
    // Load model
    const loader = new THREE.GLTFLoader();
    loader.load(
        '{$modelUrl}',
        function(gltf) {
            container.querySelector('.loading-spinner').style.display = 'none';
            
            const model = gltf.scene;
            
            // Center and scale model
            const box = new THREE.Box3().setFromObject(model);
            const center = box.getCenter(new THREE.Vector3());
            const size = box.getSize(new THREE.Vector3());
            const maxDim = Math.max(size.x, size.y, size.z);
            const scale = 2 / maxDim;
            
            model.position.sub(center);
            model.scale.multiplyScalar(scale);
            
            scene.add(model);
        },
        function(xhr) {
            const percent = (xhr.loaded / xhr.total * 100).toFixed(0);
            container.querySelector('.loading-spinner p').textContent = 'Loading... ' + percent + '%';
        },
        function(error) {
            console.error('3D Load Error:', error);
            container.querySelector('.loading-spinner').innerHTML = '<i class="fas fa-exclamation-triangle fa-2x"></i><p>Error loading model</p>';
        }
    );
    
    // Animation loop
    function animate() {
        requestAnimationFrame(animate);
        controls.update();
        renderer.render(scene, camera);
    }
    animate();
    
    // Handle resize
    window.addEventListener('resize', function() {
        camera.aspect = container.clientWidth / container.clientHeight;
        camera.updateProjectionMatrix();
        renderer.setSize(container.clientWidth, container.clientHeight);
    });
    
    // Global functions for controls
    window.resetCamera_{$modelId} = function() {
        camera.position.set(2, 2, 3);
        controls.reset();
    };
    
    window.toggleAutoRotate_{$modelId} = function() {
        controls.autoRotate = !controls.autoRotate;
    };
})();
</script>
HTML;
    }
    
    // ===== UTILITY METHODS =====
    
    /**
     * Analyze 3D model file for metadata
     */
    private function analyzeModel(string $filePath, string $format): array
    {
        $metadata = [
            'vertices' => null,
            'faces' => null,
            'textures' => 0,
            'animations' => 0,
        ];
        
        // For GLB/GLTF, we can parse JSON to get some metadata
        if (in_array($format, ['glb', 'gltf'])) {
            try {
                if ($format === 'glb') {
                    // GLB has 12-byte header, then JSON length, then JSON chunk
                    $handle = fopen($filePath, 'rb');
                    $header = fread($handle, 12);
                    $jsonLength = unpack('V', fread($handle, 4))[1];
                    fread($handle, 4); // chunk type
                    $json = fread($handle, $jsonLength);
                    fclose($handle);
                    $gltf = json_decode($json, true);
                } else {
                    $gltf = json_decode(file_get_contents($filePath), true);
                }
                
                if ($gltf) {
                    $metadata['textures'] = count($gltf['textures'] ?? []);
                    $metadata['animations'] = count($gltf['animations'] ?? []);
                    
                    // Rough vertex count from accessors
                    if (isset($gltf['accessors'])) {
                        foreach ($gltf['accessors'] as $accessor) {
                            if (($accessor['type'] ?? '') === 'VEC3') {
                                $metadata['vertices'] = ($metadata['vertices'] ?? 0) + ($accessor['count'] ?? 0);
                            }
                        }
                    }
                }
            } catch (\Exception $e) {
                $this->logger->warning('Could not analyze GLB/GLTF: ' . $e->getMessage());
            }
        }
        
        return $metadata;
    }
    
    /**
     * Log an action to the audit log
     */
    private function logAction(?int $modelId, ?int $objectId, int $userId, string $action, array $details = []): void
    {
        try {
            $userName = DB::table('user')->where('id', $userId)->value('username') ?? 'unknown';
            
            DB::table('object_3d_audit_log')->insert([
                'model_id' => $modelId,
                'object_id' => $objectId,
                'user_id' => $userId,
                'user_name' => $userName,
                'action' => $action,
                'details' => json_encode($details),
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            ]);
        } catch (\Exception $e) {
            $this->logger->warning('Could not log action: ' . $e->getMessage());
        }
    }
    
    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }
    
    /**
     * Check if object has any 3D models
     */
    public function hasModels(int $objectId): bool
    {
        return DB::table('object_3d_model')
            ->where('object_id', $objectId)
            ->where('is_public', 1)
            ->exists();
    }
    
    /**
     * Get model count for object
     */
    public function getModelCount(int $objectId): int
    {
        return DB::table('object_3d_model')
            ->where('object_id', $objectId)
            ->where('is_public', 1)
            ->count();
    }
    
    /**
     * Check if format is supported
     */
    public function isFormatSupported(string $format): bool
    {
        return isset(self::SUPPORTED_FORMATS[strtolower($format)]);
    }
    
    /**
     * Get all supported formats
     */
    public function getSupportedFormats(): array
    {
        return self::SUPPORTED_FORMATS;
    }
}
