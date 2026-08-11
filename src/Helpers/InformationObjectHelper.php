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
    /**
     * A nonce-carrying <style> element sizing one viewer container.
     *
     * The height varies per call, so it cannot be a static utility class. It
     * must not be a style attribute either: a CSP nonce covers <style> and
     * <script> ELEMENTS and never an attribute, so an inline height attribute is
     * dropped on any site running the enforcing header and the viewer collapses
     * to zero height with nothing in the console naming it.
     *
     * Scoping the rule by element id keeps it to the one container.
     */
    /**
     * The static rules these containers need, emitted once per request.
     *
     * Deliberately not an external stylesheet. A <link> has to be registered
     * with the response, and this codebase has already learned that the theme
     * never calls include_stylesheets() - registered assets are silently
     * dropped. A helper that returns HTML cannot rely on an asset pipeline it
     * does not control, so it carries its own rules in a nonced <style> element
     * and works wherever it is called from.
     *
     * Emitted once: several viewers on one page would otherwise repeat it.
     */
    public static function mediaBaseStyles(): string
    {
        static $emitted = false;

        if ($emitted) {
            return '';
        }

        $emitted = true;
        $nonce = function_exists('csp_nonce_attr') ? csp_nonce_attr() : '';

        return '<style'.($nonce ? ' '.$nonce : '').'>'
            .'.ahg-media-frame{width:100%;background:#1a1a1a}'
            .'.ahg-media-frame-400{height:400px}'
            .'.ahg-media-fill{width:100%;height:100%}'
            .'.ahg-media-embed{border:none}'
            .'.ahg-media-video{width:100%;max-height:500px}'
            .'.ahg-media-audio{width:100%}'
            .'.ahg-media-preview{max-height:400px}'
            .'</style>';
    }

    protected static function frameHeightStyle(string $elementId, string $height): string
    {
        // Only a length - never interpolate a caller's string into CSS.
        if (!preg_match('/^\d+(\.\d+)?(px|rem|em|vh|%)$/', trim($height))) {
            $height = '400px';
        }

        $nonce = function_exists('csp_nonce_attr') ? csp_nonce_attr() : '';
        $id = preg_replace('/[^A-Za-z0-9_-]/', '', $elementId);

        return self::mediaBaseStyles()
            .'<style'.($nonce ? ' '.$nonce : '').'>#'.$id.'{height:'.$height.'}</style>';
    }

    protected static function render3DViewer(int $objectId, array $options = []): string
    {
        $height = $options['height'] ?? '400px';
        
        $sizing = self::frameHeightStyle("3d-viewer-{$objectId}", $height);

        return <<<HTML
{$sizing}
<div id="3d-viewer-{$objectId}" class="ahg-media-frame">
    <model-viewer 
        src="/uploads/3d/{$objectId}/model.glb"
        alt="3D Model"
        auto-rotate
        camera-controls
        ar
        class="ahg-media-fill">
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

        $sizing = self::frameHeightStyle("image-viewer-{$objectId}", $height);

        return <<<HTML
{$sizing}
<div id="image-viewer-{$objectId}" class="ahg-media-frame"></div>
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
<video id="video-{$objectId}" controls class="ahg-media-video">
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
<audio id="audio-{$objectId}" controls class="ahg-media-audio">
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

        $sizing = self::frameHeightStyle("pdf-{$objectId}", $height);

        return <<<HTML
{$sizing}
<iframe id="pdf-{$objectId}" src="{$src}" class="ahg-media-frame ahg-media-embed"></iframe>
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
