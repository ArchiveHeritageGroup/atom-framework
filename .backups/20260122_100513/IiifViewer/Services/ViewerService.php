<?php

declare(strict_types=1);

namespace AtomFramework\Extensions\IiifViewer\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * IIIF Viewer Service
 * 
 * Renders appropriate viewers for different content types:
 * - OpenSeadragon for IIIF images
 * - Mirador for rich IIIF viewing with annotations
 * - PDF.js for PDF documents
 * - Google Model Viewer for 3D models
 * - Native HTML5 for audio/video
 * 
 * Includes Annotorious integration for all image viewers
 * 
 * @package AtomFramework\Extensions\IiifViewer
 * @author Johan Pieterse - The Archive and Heritage Group
 * @version 1.0.0
 */
class ViewerService
{
    private string $baseUrl;
    private string $frameworkPath;
    private array $config;
    
    // Viewer types
    public const VIEWER_OPENSEADRAGON = 'openseadragon';
    public const VIEWER_MIRADOR = 'mirador';
    public const VIEWER_PDFJS = 'pdfjs';
    public const VIEWER_MODEL = 'model-viewer';
    public const VIEWER_AUDIO = 'audio';
    public const VIEWER_VIDEO = 'video';
    public const VIEWER_UNIVERSAL = 'universal';
    
    public function __construct(array $config = [])
    {
        $this->config = array_merge($this->getDefaultConfig(), $config);
        $this->baseUrl = $this->config['base_url'];
        $this->frameworkPath = $this->config['framework_path'];
    }
    
    private function getDefaultConfig(): array
    {
        return [
            'base_url' => 'https://archives.theahg.co.za',
            'framework_path' => '/atom-framework/src/Extensions/IiifViewer',
            'cantaloupe_url' => 'https://archives.theahg.co.za/iiif/2',
            'default_viewer' => self::VIEWER_OPENSEADRAGON,
            'enable_annotations' => true,
            'enable_download' => false,
            'enable_fullscreen' => true,
            'viewer_height' => '600px',
            'osd_config' => [
                'showNavigator' => true,
                'navigatorPosition' => 'BOTTOM_RIGHT',
                'showRotationControl' => true,
                'showFlipControl' => true,
                'gestureSettingsMouse' => ['scrollToZoom' => true],
            ],
            'mirador_config' => [
                'sideBarOpenByDefault' => false,
                'defaultSideBarPanel' => 'info',
            ],
            'model_viewer_config' => [
                'ar' => true,
                'autoRotate' => true,
                'cameraControls' => true,
            ],
        ];
    }
    
    // ========================================================================
    // Main Render Method
    // ========================================================================
    
    /**
     * Render the appropriate viewer for a digital object
     */
    public function renderViewer(int $objectId, array $options = []): string
    {
        $digitalObjects = $this->getDigitalObjects($objectId);
        $models3d = $this->get3DModels($objectId);
        $objectInfo = $this->getObjectInfo($objectId);
        
        if (empty($digitalObjects) && empty($models3d)) {
            return $this->renderNoContent();
        }
        
        $viewerId = 'iiif-viewer-' . $objectId . '-' . uniqid();
        $preferredViewer = $options['viewer'] ?? $this->config['default_viewer'];
        
        // Determine primary content type
        $primaryDo = $digitalObjects[0] ?? null;
        $has3D = !empty($models3d);
        $hasPdf = $primaryDo && stripos($primaryDo->mime_type ?? '', 'pdf') !== false;
        $hasAV = $primaryDo && (stripos($primaryDo->mime_type ?? '', 'audio') !== false || stripos($primaryDo->mime_type ?? '', 'video') !== false);
        
        // Build manifest URL
        $manifestUrl = $this->baseUrl . '/iiif/manifest/' . ($objectInfo->slug ?? $objectId);
        
        // Render based on content type
        $html = '<div class="iiif-viewer-container" id="container-' . $viewerId . '">';
        
        // Viewer selection buttons
        $html .= $this->renderViewerToggle($viewerId, $preferredViewer, $has3D, $hasPdf, $hasAV);
        
        // IIIF badge and controls
        $html .= $this->renderViewerControls($viewerId, $manifestUrl, $objectId);
        
        // Main viewer area
        $html .= '<div class="viewer-area" id="viewer-area-' . $viewerId . '">';
        
        // OpenSeadragon viewer
        $html .= $this->renderOpenSeadragon($viewerId, $objectId, $options);
        
        // Mirador viewer (hidden by default)
        $html .= $this->renderMirador($viewerId, $manifestUrl, $options);
        
        // PDF viewer (if applicable)
        $isPiiRedacted = false;
        if ($hasPdf) {
            // Check for PII redaction
            $pdfInfo = $this->getPdfUrlWithRedaction($objectId, $primaryDo);
            $pdfUrl = $pdfInfo['url'];
            $isPiiRedacted = $pdfInfo['is_redacted'];
            $options['is_redacted'] = $isPiiRedacted;
            $html .= $this->renderPdfViewer($viewerId, $pdfUrl, $options);
        }
        
        // 3D viewer (if applicable)
        if ($has3D) {
            $html .= $this->render3DViewer($viewerId, $models3d[0], $options);
        }
        
        // Audio/Video viewer (if applicable)
        if ($hasAV) {
            $html .= $this->renderAVViewer($viewerId, $primaryDo, $options);
        }
        
        $html .= '</div>'; // viewer-area
        
        // Annotorious layer (for annotations)
        if ($this->config['enable_annotations']) {
            $html .= $this->renderAnnotoriousOverlay($viewerId, $objectId);
        }
        
        // Thumbnail strip for multi-image
        if (count($digitalObjects) > 1) {
            $html .= $this->renderThumbnailStrip($viewerId, $digitalObjects);
        }
        
        $html .= '</div>'; // container
        
        // Check if user can do manual redaction (authenticated editor/admin with privacy plugin)
        $canRedact = $this->canUserRedact();

        // JavaScript initialization
        $html .= $this->renderJavaScript($viewerId, $objectId, $manifestUrl, $preferredViewer, [
            'has3D' => $has3D,
            'hasPdf' => $hasPdf,
            'hasAV' => $hasAV,
            'enableAnnotations' => $this->config['enable_annotations'],
            'enableRedaction' => $canRedact && $hasPdf,
            'isPiiRedacted' => $isPiiRedacted,
            'pdfUrl' => $hasPdf ? ($isPiiRedacted ? $this->baseUrl . '/privacyAdmin/downloadPdf?id=' . $objectId : $this->getDigitalObjectUrl($primaryDo)) : null,
        ]);
        
        return $html;
    }
    
    // ========================================================================
    // Individual Viewer Renderers
    // ========================================================================
    
    /**
     * Render OpenSeadragon viewer
     */
    private function renderOpenSeadragon(string $viewerId, int $objectId, array $options): string
    {
        $height = $options['height'] ?? $this->config['viewer_height'];
        
        return <<<HTML
<div id="osd-{$viewerId}" class="osd-viewer" style="width:100%;height:{$height};background:#1a1a1a;border-radius:8px;display:block;"></div>
HTML;
    }
    
    /**
     * Render Mirador viewer
     */
    private function renderMirador(string $viewerId, string $manifestUrl, array $options): string
    {
        $height = $options['height'] ?? '700px';
        
        return <<<HTML
<div id="mirador-wrapper-{$viewerId}" class="mirador-wrapper" style="display:none;position:relative;">
    <button id="close-mirador-{$viewerId}" class="btn btn-sm btn-light" 
            style="position:absolute;top:10px;right:10px;z-index:1000;">
        <i class="fas fa-times"></i> Close Mirador
    </button>
    <div id="mirador-{$viewerId}" style="width:100%;height:{$height};"></div>
</div>
HTML;
    }
    
    /**
     * Render PDF.js viewer
     */
    private function renderPdfViewer(string $viewerId, string $pdfUrl, array $options): string
    {
        $height = $options['height'] ?? $this->config['viewer_height'];
        $isRedacted = $options['is_redacted'] ?? false;

        $redactedBadge = $isRedacted
            ? '<span class="badge bg-warning text-dark ms-2"><i class="fas fa-shield-alt me-1"></i>PII Redacted</span>'
            : '';

        return <<<HTML
<div id="pdf-wrapper-{$viewerId}" class="pdf-wrapper" style="display:none;">
    <div class="pdf-toolbar mb-2 d-flex align-items-center">
        <div class="btn-group btn-group-sm">
            <button type="button" class="btn btn-outline-secondary" id="pdf-prev-{$viewerId}">
                <i class="fas fa-chevron-left"></i>
            </button>
            <span class="btn btn-outline-secondary disabled" id="pdf-page-{$viewerId}">1 / 1</span>
            <button type="button" class="btn btn-outline-secondary" id="pdf-next-{$viewerId}">
                <i class="fas fa-chevron-right"></i>
            </button>
        </div>
        <div class="btn-group btn-group-sm ms-2">
            <button type="button" class="btn btn-outline-secondary" id="pdf-zoom-out-{$viewerId}">
                <i class="fas fa-search-minus"></i>
            </button>
            <button type="button" class="btn btn-outline-secondary" id="pdf-zoom-in-{$viewerId}">
                <i class="fas fa-search-plus"></i>
            </button>
        </div>
        {$redactedBadge}
    </div>
    <div id="pdf-container-{$viewerId}" style="width:100%;height:{$height};overflow:auto;background:#525659;border-radius:8px;">
        <canvas id="pdf-canvas-{$viewerId}"></canvas>
    </div>
</div>
HTML;
    }
    
    /**
     * Render 3D Model Viewer (using standard digital object uploads)
     */
    private function render3DViewer(string $viewerId, object $model, array $options): string
    {
        $height = $options['height'] ?? $this->config['viewer_height'];
        
        // Use standard digital object path
        $modelUrl = $this->baseUrl . '/uploads/' . trim($model->path ?? '', '/') . '/' . $model->filename;
        
        $arAttr = !empty($model->ar_enabled) ? 'ar ar-modes="webxr scene-viewer quick-look"' : '';
        $autoRotate = !empty($model->auto_rotate) ? 'auto-rotate' : '';
        $cameraOrbit = $model->camera_orbit ?? '0deg 75deg 105%';
        $bgColor = $model->background_color ?? '#f5f5f5';
        
        $poster = !empty($model->poster_image) ? 'poster="' . $this->baseUrl . $model->poster_image . '"' : '';
        
        return <<<HTML
<div id="model-wrapper-{$viewerId}" class="model-wrapper" style="display:none;">
    <model-viewer id="model-{$viewerId}"
                  src="{$modelUrl}"
                  {$poster}
                  {$arAttr}
                  {$autoRotate}
                  camera-controls
                  touch-action="pan-y"
                  camera-orbit="{$cameraOrbit}"
                  style="width:100%;height:{$height};background-color:{$bgColor};border-radius:8px;">
        
        <button slot="ar-button" class="btn btn-primary" style="position:absolute;bottom:16px;right:16px;">
            <i class="fas fa-cube me-1"></i>View in AR
        </button>
        
        <div class="progress-bar hide" slot="progress-bar">
            <div class="update-bar"></div>
        </div>
    </model-viewer>
</div>
HTML;
    }
    
    /**
     * Render Audio/Video viewer
     */
    private function renderAVViewer(string $viewerId, object $digitalObject, array $options): string
    {
        $mediaUrl = $this->getDigitalObjectUrl($digitalObject);
        $mimeType = $digitalObject->mime_type ?? 'video/mp4';
        $isAudio = stripos($mimeType, 'audio') !== false;
        
        if ($isAudio) {
            return <<<HTML
<div id="av-wrapper-{$viewerId}" class="av-wrapper" style="display:none;padding:20px;">
    <audio id="audio-{$viewerId}" controls style="width:100%;">
        <source src="{$mediaUrl}" type="{$mimeType}">
        Your browser does not support the audio element.
    </audio>
</div>
HTML;
        }
        
        $height = $options['height'] ?? $this->config['viewer_height'];
        
        return <<<HTML
<div id="av-wrapper-{$viewerId}" class="av-wrapper" style="display:none;">
    <video id="video-{$viewerId}" controls style="width:100%;height:{$height};background:#000;border-radius:8px;">
        <source src="{$mediaUrl}" type="{$mimeType}">
        Your browser does not support the video element.
    </video>
</div>
HTML;
    }
    
    // ========================================================================
    // UI Components
    // ========================================================================
    
    /**
     * Render viewer toggle buttons
     */
    private function renderViewerToggle(string $viewerId, string $activeViewer, bool $has3D, bool $hasPdf, bool $hasAV): string
    {
        $osdActive = $activeViewer === self::VIEWER_OPENSEADRAGON ? 'btn-primary' : 'btn-outline-primary';
        $miradorActive = $activeViewer === self::VIEWER_MIRADOR ? 'btn-primary' : 'btn-outline-primary';
        
        $html = '<div class="viewer-toggle mb-2">';
        $html .= '<div class="btn-group btn-group-sm" role="group">';
        
        // OpenSeadragon
        $html .= <<<HTML
<button type="button" class="btn {$osdActive}" id="btn-osd-{$viewerId}" title="OpenSeadragon - Fast image viewer">
    <i class="fas fa-search-plus me-1"></i>OpenSeadragon
</button>
HTML;
        
        // Mirador
        $html .= <<<HTML
<button type="button" class="btn {$miradorActive}" id="btn-mirador-{$viewerId}" title="Mirador - IIIF 3.0 viewer with annotations">
    <i class="fas fa-layer-group me-1"></i>Mirador 3
</button>
HTML;
        
        // PDF viewer
        if ($hasPdf) {
            $html .= <<<HTML
<button type="button" class="btn btn-outline-primary" id="btn-pdf-{$viewerId}" title="PDF Viewer">
    <i class="fas fa-file-pdf me-1"></i>PDF
</button>
HTML;
        }
        
        // 3D viewer
        if ($has3D) {
            $html .= <<<HTML
<button type="button" class="btn btn-outline-primary" id="btn-3d-{$viewerId}" title="3D Model Viewer with AR">
    <i class="fas fa-cube me-1"></i>3D
    <span class="badge bg-success ms-1">AR</span>
</button>
HTML;
        }
        
        // AV viewer
        if ($hasAV) {
            $html .= <<<HTML
<button type="button" class="btn btn-outline-primary" id="btn-av-{$viewerId}" title="Audio/Video Player">
    <i class="fas fa-play me-1"></i>Media
</button>
HTML;
        }
        
        $html .= '</div>'; // btn-group
        $html .= '</div>'; // viewer-toggle
        
        return $html;
    }
    
    /**
     * Render viewer controls (fullscreen, download, etc.)
     */
    private function renderViewerControls(string $viewerId, string $manifestUrl, int $objectId): string
    {
        $html = '<div class="viewer-controls mb-2 d-flex justify-content-between align-items-center">';
        
        // IIIF badge
        $html .= '<div>';
        $html .= '<span class="badge bg-info"><i class="fas fa-certificate me-1"></i>IIIF 3.0</span>';
        $html .= '<small class="text-muted ms-2">Presentation API 3.0</small>';
        $html .= '</div>';
        
        // Control buttons
        $html .= '<div class="btn-group btn-group-sm">';
        
        // New window
        $html .= <<<HTML
<button type="button" class="btn btn-outline-secondary" id="btn-newwin-{$viewerId}" title="Open in new window">
    <i class="fas fa-external-link-alt"></i>
</button>
HTML;
        
        // Fullscreen
        if ($this->config['enable_fullscreen']) {
            $html .= <<<HTML
<button type="button" class="btn btn-outline-secondary" id="btn-fullscreen-{$viewerId}" title="Fullscreen">
    <i class="fas fa-expand"></i>
</button>
HTML;
        }
        
        // Download
        if ($this->config['enable_download']) {
            $html .= <<<HTML
<button type="button" class="btn btn-outline-secondary" id="btn-download-{$viewerId}" title="Download">
    <i class="fas fa-download"></i>
</button>
HTML;
        }
        
        // Annotations toggle
        if ($this->config['enable_annotations']) {
            $html .= <<<HTML
<button type="button" class="btn btn-outline-secondary" id="btn-annotations-{$viewerId}" title="Toggle Annotations">
    <i class="fas fa-comment-dots"></i>
</button>
HTML;
        }
        
        // Copy manifest URL
        $html .= <<<HTML
<button type="button" class="btn btn-outline-secondary" id="btn-manifest-{$viewerId}" title="Copy IIIF Manifest URL" data-url="{$manifestUrl}">
    <i class="fas fa-link"></i>
</button>
HTML;
        
        $html .= '</div>'; // btn-group
        $html .= '</div>'; // viewer-controls
        
        return $html;
    }
    
    /**
     * Render Annotorious overlay
     */
    private function renderAnnotoriousOverlay(string $viewerId, int $objectId): string
    {
        return <<<HTML
<div id="annotorious-{$viewerId}" class="annotorious-container" data-object-id="{$objectId}"></div>
HTML;
    }
    
    /**
     * Render thumbnail strip for multi-image
     */
    private function renderThumbnailStrip(string $viewerId, array $digitalObjects): string
    {
        $html = '<div class="thumbnail-strip mt-2" id="thumbs-' . $viewerId . '" style="display:flex;gap:8px;overflow-x:auto;padding:8px 0;">';
        
        foreach ($digitalObjects as $index => $do) {
            $iiifId = $this->buildIiifIdentifier($do->path, $do->name);
            $thumbUrl = $this->config['cantaloupe_url'] . '/' . urlencode($iiifId) . '/full/100,/0/default.jpg';
            $active = $index === 0 ? 'active' : '';
            
            $html .= <<<HTML
<div class="thumb-item {$active}" data-index="{$index}" style="flex-shrink:0;cursor:pointer;border:2px solid transparent;border-radius:4px;">
    <img src="{$thumbUrl}" alt="Page " . ($index + 1) . "" style="height:80px;display:block;">
</div>
HTML;
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * Render no content message
     */
    private function renderNoContent(): string
    {
        return <<<HTML
<div class="alert alert-info">
    <i class="fas fa-info-circle me-2"></i>
    No digital objects available for this item.
</div>
HTML;
    }
    
    // ========================================================================
    // JavaScript
    // ========================================================================
    
    /**
     * Render JavaScript initialization
     */
    private function renderJavaScript(string $viewerId, int $objectId, string $manifestUrl, string $defaultViewer, array $flags): string
    {
        $osdConfig = json_encode($this->config['osd_config']);
        $miradorConfig = json_encode($this->config['mirador_config']);
        $flagsJson = json_encode($flags);
        $baseUrl = $this->baseUrl;
        $cantaloupeUrl = $this->config['cantaloupe_url'];
        $frameworkPath = $this->frameworkPath;
        
        return <<<JS
<script type="module">
import { IiifViewerManager } from '{$frameworkPath}/public/js/iiif-viewer-manager.js';

document.addEventListener('DOMContentLoaded', function() {
    const viewer = new IiifViewerManager('{$viewerId}', {
        objectId: {$objectId},
        manifestUrl: '{$manifestUrl}',
        baseUrl: '{$baseUrl}',
        cantaloupeUrl: '{$cantaloupeUrl}',
        frameworkPath: '{$frameworkPath}',
        defaultViewer: '{$defaultViewer}',
        flags: {$flagsJson},
        osdConfig: {$osdConfig},
        miradorConfig: {$miradorConfig}
    });
    
    viewer.init();
});
</script>
JS;
    }
    
    // ========================================================================
    // Helper Methods
    // ========================================================================
    
    private function getDigitalObjects(int $objectId): array
    {
        return DB::table('digital_object')
            ->where('object_id', $objectId)
            ->orderBy('id')
            ->get()
            ->toArray();
    }
    
    private function get3DModels(int $objectId): array
    {
        // 3D model extensions
        $extensions = ['glb', 'gltf', 'obj', 'stl', 'fbx', 'ply', 'usdz'];
        
        $models = [];
        
        $digitalObjects = DB::table('digital_object')
            ->where('object_id', $objectId)
            ->get();
        
        foreach ($digitalObjects as $do) {
            $ext = strtolower(pathinfo($do->name, PATHINFO_EXTENSION));
            
            if (in_array($ext, $extensions)) {
                $models[] = (object)[
                    'id' => $do->id,
                    'object_id' => $do->object_id,
                    'filename' => $do->name,
                    'path' => $do->path,
                    'format' => $ext,
                    'title' => pathinfo($do->name, PATHINFO_FILENAME),
                    'auto_rotate' => true,
                    'ar_enabled' => true,
                    'camera_orbit' => '0deg 75deg 105%',
                    'background_color' => '#f5f5f5',
                    'poster_image' => null,
                    'is_digital_object' => true,
                ];
            }
        }
        
        return $models;
    }
    
    private function getObjectInfo(int $objectId): ?object
    {
        return DB::table('information_object as io')
            ->leftJoin('slug', 'io.id', '=', 'slug.object_id')
            ->where('io.id', $objectId)
            ->select('io.id', 'slug.slug')
            ->first();
    }
    
    private function getDigitalObjectUrl(object $do): string
    {
        return $this->baseUrl . '/uploads/' . trim($do->path ?? '', '/') . '/' . $do->name;
    }

    /**
     * Get PDF URL with PII redaction check
     *
     * Returns the redacted PDF URL if PII redaction is active,
     * otherwise returns the original PDF URL.
     *
     * @param int $objectId
     * @param object $do Digital object
     * @return array ['url' => string, 'is_redacted' => bool]
     */
    private function getPdfUrlWithRedaction(int $objectId, object $do): array
    {
        // Check if PII redaction is needed
        $hasRedaction = $this->hasPiiRedaction($objectId);

        if (!$hasRedaction) {
            return [
                'url' => $this->getDigitalObjectUrl($do),
                'is_redacted' => false
            ];
        }

        // Return URL to redaction download endpoint
        return [
            'url' => $this->baseUrl . '/privacyAdmin/downloadPdf?id=' . $objectId,
            'is_redacted' => true
        ];
    }

    /**
     * Check if an object has PII entities marked for redaction
     *
     * @param int $objectId
     * @return bool
     */
    private function hasPiiRedaction(int $objectId): bool
    {
        try {
            $count = DB::table('ahg_ner_entity')
                ->where('object_id', $objectId)
                ->where('status', 'redacted')
                ->count();

            return $count > 0;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    private function buildIiifIdentifier(?string $path, ?string $name): string
    {
        $path = trim($path ?? '', '/');
        return str_replace('/', '_SL_', $path . '/' . $name);
    }
}
