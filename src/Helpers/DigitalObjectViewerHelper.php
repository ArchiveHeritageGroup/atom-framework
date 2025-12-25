<?php

declare(strict_types=1);

namespace AtomFramework\Helpers;

/**
 * Digital Object Viewer Helper - IIIF and media viewer rendering
 */
class DigitalObjectViewerHelper
{
    /**
     * Get user's preferred IIIF viewer
     */
    public static function getPreferredIiifViewer(): string
    {
        $user = \sfContext::getInstance()->getUser();
        return $user->getAttribute('preferred_iiif_viewer', 'openseadragon');
    }

    /**
     * Set user's preferred IIIF viewer
     */
    public static function setPreferredIiifViewer(string $viewer): void
    {
        $user = \sfContext::getInstance()->getUser();
        $user->setAttribute('preferred_iiif_viewer', $viewer);
    }

    /**
     * Render viewer toggle buttons for IIIF content
     */
    public static function renderViewerToggle(int $objId, string $currentViewer = 'openseadragon'): string
    {
        $osdActive = ($currentViewer === 'openseadragon') ? 'btn-primary' : 'btn-outline-primary';
        $miradorActive = ($currentViewer === 'mirador') ? 'btn-primary' : 'btn-outline-primary';

        return <<<HTML
<div class="viewer-toggle mb-2" data-object-id="{$objId}">
    <div class="btn-group btn-group-sm" role="group">
        <button type="button" class="btn {$osdActive} viewer-btn" data-viewer="openseadragon" title="Fast, lightweight viewer">
            <i class="fas fa-search-plus me-1"></i>OpenSeadragon
        </button>
        <button type="button" class="btn {$miradorActive} viewer-btn" data-viewer="mirador" title="Feature-rich scholarly viewer">
            <i class="fas fa-layer-group me-1"></i>Mirador
        </button>
    </div>
    <div class="btn-group btn-group-sm ms-2">
        <button type="button" class="btn btn-outline-secondary" onclick="openViewerNewWindow({$objId})" title="Open in new window">
            <i class="fas fa-external-link-alt"></i>
        </button>
    </div>
</div>
HTML;
    }

    /**
     * Render Mirador viewer for IIIF content
     */
    public static function renderMiradorViewer(string $iiifIdentifier, int $objId, string $root, $request): string
    {
        $viewerId = "mirador-viewer-{$objId}";
        $siteBaseUrl = \QubitSetting::getByName('siteBaseUrl') ?: $request->getUriPrefix();
        $baseUrl = rtrim($siteBaseUrl, '/') . $root;
        $manifestUrl = "{$baseUrl}/iiif-manifest.php?id={$objId}";

        return <<<HTML
<div id="{$viewerId}" style="width:100%;height:400px;background:#1a1a1a;"></div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof Mirador !== 'undefined') {
        Mirador.viewer({
            id: '{$viewerId}',
            windows: [{
                manifestId: '{$manifestUrl}'
            }],
            window: {
                allowClose: false,
                allowMaximize: true,
                allowFullscreen: true
            }
        });
    }
});
</script>
HTML;
    }

    /**
     * Render OpenSeadragon viewer for IIIF content
     */
    public static function renderOpenSeadragonViewer(string $iiifIdentifier, int $objId, string $cantaloupeUrl): string
    {
        $viewerId = "osd-viewer-{$objId}";
        $infoUrl = "{$cantaloupeUrl}/{$iiifIdentifier}/info.json";

        return <<<HTML
<div id="{$viewerId}" style="width:100%;height:400px;background:#1a1a1a;"></div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof OpenSeadragon !== 'undefined') {
        OpenSeadragon({
            id: '{$viewerId}',
            prefixUrl: '/vendor/openseadragon/images/',
            tileSources: '{$infoUrl}',
            showNavigator: true,
            navigatorPosition: 'BOTTOM_RIGHT',
            showRotationControl: true,
            showFullPageControl: true
        });
    }
});
</script>
HTML;
    }
}
