<?php

declare(strict_types=1);

namespace AtomFramework\Helpers;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Information Object Helper - Renders viewers for digital objects
 */
class InformationObjectHelper
{
    protected static array $modelCache = [];

    /**
     * Check if ahg3DModelPlugin is available and has models for this object
     */
    public static function get3DModelsFromPlugin(int $objectId): ?array
    {
        if (isset(self::$modelCache[$objectId])) {
            return self::$modelCache[$objectId];
        }

        try {
            $servicePath = \sfConfig::get('sf_root_dir') . '/atom-framework/src/Services/Model3DService.php';
            if (!file_exists($servicePath)) {
                self::$modelCache[$objectId] = null;
                return null;
            }

            // Bootstrap Laravel if not already done
            $bootstrapPath = \sfConfig::get('sf_root_dir') . '/atom-framework/bootstrap.php';
            if (file_exists($bootstrapPath)) {
                require_once $bootstrapPath;
            }

            // Check if table exists
            if (!DB::schema()->hasTable('object_3d_model')) {
                self::$modelCache[$objectId] = null;
                return null;
            }

            // Query for 3D models
            $models = DB::table('object_3d_model as m')
                ->leftJoin('object_3d_model_i18n as i18n', function ($join) {
                    $join->on('m.id', '=', 'i18n.model_id')
                        ->where('i18n.culture', '=', \sfContext::getInstance()->user->getCulture());
                })
                ->where('m.object_id', '=', $objectId)
                ->select('m.*', 'i18n.title', 'i18n.description')
                ->get()
                ->toArray();

            self::$modelCache[$objectId] = !empty($models) ? $models : null;
            return self::$modelCache[$objectId];

        } catch (\Exception $e) {
            self::$modelCache[$objectId] = null;
            return null;
        }
    }

    /**
     * Clear the model cache
     */
    public static function clearCache(): void
    {
        self::$modelCache = [];
    }

    /**
     * Check if object has 3D models
     */
    public static function has3DModels(int $objectId): bool
    {
        return self::get3DModelsFromPlugin($objectId) !== null;
    }

    /**
     * Render appropriate viewer based on digital object type
     */
    public static function renderViewer(int $objectId, string $mimeType, array $options = []): string
    {
        // Check for 3D models first
        if (self::has3DModels($objectId)) {
            return self::render3DViewer($objectId, $options);
        }

        // Check MIME type for viewer selection
        if (str_starts_with($mimeType, 'image/')) {
            return self::renderImageViewer($objectId, $options);
        }

        if (str_starts_with($mimeType, 'video/')) {
            return self::renderVideoViewer($objectId, $mimeType, $options);
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return self::renderAudioViewer($objectId, $mimeType, $options);
        }

        if ($mimeType === 'application/pdf') {
            return self::renderPdfViewer($objectId, $options);
        }

        return self::renderGenericViewer($objectId, $mimeType, $options);
    }

    /**
     * Render 3D model viewer
     */
    protected static function render3DViewer(int $objectId, array $options = []): string
    {
        $height = $options['height'] ?? '400px';
        
        return <<<HTML
<div id="3d-viewer-{$objectId}" style="width:100%;height:{$height};background:#1a1a1a;">
    <model-viewer 
        src="/uploads/3d/{$objectId}/model.glb"
        alt="3D Model"
        auto-rotate
        camera-controls
        ar
        style="width:100%;height:100%;">
    </model-viewer>
</div>
HTML;
    }

    /**
     * Render image viewer (IIIF or standard)
     */
    protected static function renderImageViewer(int $objectId, array $options = []): string
    {
        $viewer = DigitalObjectViewerHelper::getPreferredIiifViewer();
        $height = $options['height'] ?? '400px';

        return <<<HTML
<div id="image-viewer-{$objectId}" style="width:100%;height:{$height};background:#1a1a1a;"></div>
HTML;
    }

    /**
     * Render video viewer
     */
    protected static function renderVideoViewer(int $objectId, string $mimeType, array $options = []): string
    {
        $needsStreaming = MediaHelper::needsStreaming($mimeType);
        $src = $needsStreaming 
            ? MediaHelper::buildStreamingUrl($objectId)
            : "/uploads/r/{$objectId}/original";
        $outputType = $needsStreaming ? 'video/mp4' : $mimeType;

        return <<<HTML
<video id="video-{$objectId}" controls style="width:100%;max-height:500px;">
    <source src="{$src}" type="{$outputType}">
    Your browser does not support the video tag.
</video>
HTML;
    }

    /**
     * Render audio viewer
     */
    protected static function renderAudioViewer(int $objectId, string $mimeType, array $options = []): string
    {
        $needsStreaming = MediaHelper::needsStreaming($mimeType);
        $src = $needsStreaming 
            ? MediaHelper::buildStreamingUrl($objectId)
            : "/uploads/r/{$objectId}/original";
        $outputType = $needsStreaming ? 'audio/mpeg' : $mimeType;

        return <<<HTML
<audio id="audio-{$objectId}" controls style="width:100%;">
    <source src="{$src}" type="{$outputType}">
    Your browser does not support the audio tag.
</audio>
HTML;
    }

    /**
     * Render PDF viewer
     */
    protected static function renderPdfViewer(int $objectId, array $options = []): string
    {
        $height = $options['height'] ?? '600px';
        $src = "/uploads/r/{$objectId}/original";

        return <<<HTML
<iframe id="pdf-{$objectId}" src="{$src}" style="width:100%;height:{$height};border:none;"></iframe>
HTML;
    }

    /**
     * Render generic download link
     */
    protected static function renderGenericViewer(int $objectId, string $mimeType, array $options = []): string
    {
        $src = "/uploads/r/{$objectId}/original";

        return <<<HTML
<div class="text-center p-4">
    <i class="fas fa-file fa-3x text-muted mb-3"></i>
    <p class="text-muted">{$mimeType}</p>
    <a href="{$src}" class="btn btn-primary" download>
        <i class="fas fa-download me-1"></i>Download
    </a>
</div>
HTML;
    }
}
