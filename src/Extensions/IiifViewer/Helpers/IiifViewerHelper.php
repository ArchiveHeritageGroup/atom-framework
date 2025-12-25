<?php

/**
 * IIIF Viewer Helper for AtoM Integration
 * 
 * Drop-in replacement for existing digital object viewing in AtoM
 * Replaces: ZoomPan, OpenSeadragon, video/audio players
 * 
 * Add to: /usr/share/nginx/archive/plugins/arAHGThemeB5Plugin/lib/helper/IiifViewerHelper.php
 * 
 * @author Johan Pieterse - The Archive and Heritage Group
 * @version 1.0.0
 */

/**
 * Main function to render IIIF viewer for an information object
 * This replaces all previous viewer rendering functions
 */
function render_iiif_viewer($resource, $options = [])
{
    // Get digital objects
    $digitalObjects = $resource->digitalObjectsRelatedByobjectId;
    
    if (empty($digitalObjects) || count($digitalObjects) === 0) {
        // Check for 3D models
        if (has_3d_models($resource)) {
            return render_3d_model_viewer($resource, $options);
        }
        return '';
    }
    
    $primaryDo = $digitalObjects[0];
    $mimeType = $primaryDo->mimeType ?? '';
    $objectId = $resource->id;
    $slug = $resource->slug ?? $objectId;
    
    // Configuration
    $baseUrl = sfConfig::get('app_iiif_base_url', 'https://archives.theahg.co.za');
    $cantaloupeUrl = sfConfig::get('app_iiif_cantaloupe_url', 'https://archives.theahg.co.za/iiif/2');
    $frameworkPath = sfConfig::get('app_iiif_framework_path', '/atom-framework/src/Extensions/IiifViewer');
    $defaultViewer = sfConfig::get('app_iiif_default_viewer', 'openseadragon');
    $enableAnnotations = sfConfig::get('app_iiif_enable_annotations', true);
    $viewerHeight = $options['height'] ?? sfConfig::get('app_iiif_viewer_height', '600px');
    
    // Merge options
    $opts = array_merge([
        'viewer' => $defaultViewer,
        'height' => $viewerHeight,
        'enable_annotations' => $enableAnnotations,
        'enable_download' => false,
        'enable_fullscreen' => true,
    ], $options);
    
    // Build manifest URL
    $manifestUrl = $baseUrl . '/iiif/manifest/' . $slug;
    
    // Determine content type flags
    $hasPdf = stripos($mimeType, 'pdf') !== false;
    $hasAudio = stripos($mimeType, 'audio') !== false;
    $hasVideo = stripos($mimeType, 'video') !== false;
    $has3D = has_3d_models($resource);
    $hasAV = $hasAudio || $hasVideo;
    
    // Generate unique viewer ID
    $viewerId = 'iiif-viewer-' . $objectId . '-' . substr(md5(uniqid()), 0, 8);
    
    // Build HTML
    $html = '';
    
    // Include CSS (once per page)
    $html .= get_iiif_viewer_css($frameworkPath);
    
    // Container
    $html .= '<div class="iiif-viewer-container" id="container-' . $viewerId . '">';
    
    // Viewer toggle buttons
    $html .= render_viewer_toggle($viewerId, $opts['viewer'], $has3D, $hasPdf, $hasAV);
    
    // Controls bar
    $html .= render_viewer_controls($viewerId, $manifestUrl, $objectId, $opts);
    
    // Main viewer area
    $html .= '<div class="viewer-area" id="viewer-area-' . $viewerId . '">';
    
    // OpenSeadragon viewer
    $html .= '<div id="osd-' . $viewerId . '" class="osd-viewer" style="width:100%;height:' . $viewerHeight . ';background:#1a1a1a;border-radius:8px;"></div>';
    
    // Mirador wrapper (hidden by default)
    $html .= '<div id="mirador-wrapper-' . $viewerId . '" class="mirador-wrapper" style="display:none;position:relative;">';
    $html .= '<button id="close-mirador-' . $viewerId . '" class="btn btn-sm btn-light" style="position:absolute;top:10px;right:10px;z-index:1000;">';
    $html .= '<i class="fas fa-times"></i> Close</button>';
    $html .= '<div id="mirador-' . $viewerId . '" style="width:100%;height:700px;"></div>';
    $html .= '</div>';
    
    // PDF viewer (if applicable)
    if ($hasPdf) {
        $pdfUrl = get_digital_object_url($primaryDo);
        $html .= render_pdf_viewer_html($viewerId, $pdfUrl, $viewerHeight);
    }
    
    // 3D viewer (if applicable)
    if ($has3D) {
        $model = get_primary_3d_model($resource);
        if ($model) {
            $html .= render_3d_viewer_html($viewerId, $model, $viewerHeight, $baseUrl);
        }
    }
    
    // Audio/Video viewer (if applicable)
    if ($hasAV) {
        $html .= render_av_viewer_html($viewerId, $primaryDo, $viewerHeight, $baseUrl);
    }
    
    $html .= '</div>'; // viewer-area
    
    // Thumbnail strip for multi-image
    if (count($digitalObjects) > 1) {
        $html .= render_thumbnail_strip($viewerId, $digitalObjects, $cantaloupeUrl);
    }
    
    $html .= '</div>'; // container
    
    // JavaScript initialization
    $html .= render_viewer_javascript($viewerId, $objectId, $manifestUrl, $opts, [
        'has3D' => $has3D,
        'hasPdf' => $hasPdf,
        'hasAV' => $hasAV,
        'baseUrl' => $baseUrl,
        'cantaloupeUrl' => $cantaloupeUrl,
        'frameworkPath' => $frameworkPath,
    ]);
    
    return $html;
}

/**
 * Check if resource has 3D models (from standard digital objects)
 */
function has_3d_models($resource)
{
    $extensions = ['glb', 'gltf', 'obj', 'stl', 'fbx', 'ply', 'usdz'];
    
    try {
        $digitalObjects = get_digital_objects($resource);
        
        foreach ($digitalObjects as $do) {
            $name = is_object($do) ? $do->name : ($do['name'] ?? '');
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            
            if (in_array($ext, $extensions)) {
                return true;
            }
        }
        return false;
    } catch (Exception $e) {
        return false;
    }
}

/**
 * Get primary 3D model for resource (from standard digital objects)
 */
function get_primary_3d_model($resource)
{
    $extensions = ['glb', 'gltf', 'obj', 'stl', 'fbx', 'ply', 'usdz'];
    
    try {
        $digitalObjects = get_digital_objects($resource);
        
        foreach ($digitalObjects as $do) {
            $name = is_object($do) ? $do->name : ($do['name'] ?? '');
            $path = is_object($do) ? $do->path : ($do['path'] ?? '');
            $id = is_object($do) ? $do->id : ($do['id'] ?? 0);
            $objectId = is_object($do) ? $do->object_id : ($do['object_id'] ?? $resource->id);
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            
            if (in_array($ext, $extensions)) {
                // Return as object with expected properties
                return (object)[
                    'id' => $id,
                    'object_id' => $objectId,
                    'filename' => $name,
                    'path' => $path,
                    'format' => $ext,
                    'title' => pathinfo($name, PATHINFO_FILENAME),
                    'auto_rotate' => true,
                    'ar_enabled' => true,
                    'camera_orbit' => '0deg 75deg 105%',
                    'background_color' => '#f5f5f5',
                    'poster_image' => null,
                ];
            }
        }
        return null;
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Get digital object URL
 */
function get_digital_object_url($digitalObject)
{
    $path = trim($digitalObject->path ?? '', '/');
    $name = $digitalObject->name ?? '';
    return '/uploads/' . $path . '/' . $name;
}

/**
 * Build IIIF identifier from path and name
 */
function build_iiif_identifier($path, $name)
{
    $path = trim($path ?? '', '/');
    return str_replace('/', '_SL_', $path . '/' . $name);
}

/**
 * Include viewer CSS (only once per page)
 */
function get_iiif_viewer_css($frameworkPath)
{
    static $cssIncluded = false;
    
    if ($cssIncluded) {
        return '';
    }
    
    $cssIncluded = true;
    
    return '<link rel="stylesheet" href="' . $frameworkPath . '/public/css/iiif-viewer.css">' . "\n";
}

/**
 * Render viewer toggle buttons
 */
function render_viewer_toggle($viewerId, $activeViewer, $has3D, $hasPdf, $hasAV)
{
    $osdClass = $activeViewer === 'openseadragon' ? 'btn-primary' : 'btn-outline-primary';
    $miradorClass = $activeViewer === 'mirador' ? 'btn-primary' : 'btn-outline-primary';
    
    $html = '<div class="viewer-toggle mb-2">';
    $html .= '<div class="btn-group btn-group-sm" role="group">';
    
    // OpenSeadragon
    $html .= '<button type="button" class="btn ' . $osdClass . '" id="btn-osd-' . $viewerId . '" title="OpenSeadragon - Fast image viewer">';
    $html .= '<i class="fas fa-search-plus me-1"></i><span class="d-none d-sm-inline">OpenSeadragon</span></button>';
    
    // Mirador
    $html .= '<button type="button" class="btn ' . $miradorClass . '" id="btn-mirador-' . $viewerId . '" title="Mirador 3 - IIIF 3.0 viewer">';
    $html .= '<i class="fas fa-layer-group me-1"></i><span class="d-none d-sm-inline">Mirador 3</span></button>';
    
    // PDF
    if ($hasPdf) {
        $html .= '<button type="button" class="btn btn-outline-primary" id="btn-pdf-' . $viewerId . '" title="PDF Viewer">';
        $html .= '<i class="fas fa-file-pdf me-1"></i><span class="d-none d-sm-inline">PDF</span></button>';
    }
    
    // 3D
    if ($has3D) {
        $html .= '<button type="button" class="btn btn-outline-primary" id="btn-3d-' . $viewerId . '" title="3D Model Viewer">';
        $html .= '<i class="fas fa-cube me-1"></i><span class="d-none d-sm-inline">3D</span>';
        $html .= '<span class="badge bg-success ms-1">AR</span></button>';
    }
    
    // Audio/Video
    if ($hasAV) {
        $html .= '<button type="button" class="btn btn-outline-primary" id="btn-av-' . $viewerId . '" title="Media Player">';
        $html .= '<i class="fas fa-play me-1"></i><span class="d-none d-sm-inline">Media</span></button>';
    }
    
    $html .= '</div></div>';
    
    return $html;
}

/**
 * Render viewer controls bar
 */
function render_viewer_controls($viewerId, $manifestUrl, $objectId, $opts)
{
    $html = '<div class="viewer-controls mb-2 d-flex justify-content-between align-items-center">';
    
    // IIIF badge
    $html .= '<div>';
    $html .= '<span class="badge bg-info"><i class="fas fa-certificate me-1"></i>IIIF 3.0</span>';
    $html .= '<small class="text-muted ms-2 d-none d-sm-inline">Presentation API 3.0</small>';
    $html .= '</div>';
    
    // Control buttons
    $html .= '<div class="btn-group btn-group-sm">';
    
    // New window
    $html .= '<button type="button" class="btn btn-outline-secondary" id="btn-newwin-' . $viewerId . '" title="Open in new window">';
    $html .= '<i class="fas fa-external-link-alt"></i></button>';
    
    // Fullscreen
    if ($opts['enable_fullscreen']) {
        $html .= '<button type="button" class="btn btn-outline-secondary" id="btn-fullscreen-' . $viewerId . '" title="Fullscreen">';
        $html .= '<i class="fas fa-expand"></i></button>';
    }
    
    // Download
    if ($opts['enable_download']) {
        $html .= '<button type="button" class="btn btn-outline-secondary" id="btn-download-' . $viewerId . '" title="Download">';
        $html .= '<i class="fas fa-download"></i></button>';
    }
    
    // Annotations
    if ($opts['enable_annotations']) {
        $html .= '<button type="button" class="btn btn-outline-secondary" id="btn-annotations-' . $viewerId . '" title="Toggle Annotations">';
        $html .= '<i class="fas fa-comment-dots"></i></button>';
    }
    
    // Copy manifest URL
    $html .= '<button type="button" class="btn btn-outline-secondary" id="btn-manifest-' . $viewerId . '" title="Copy IIIF Manifest URL" data-url="' . htmlspecialchars($manifestUrl) . '">';
    $html .= '<i class="fas fa-link"></i></button>';
    
    $html .= '</div></div>';
    
    return $html;
}

/**
 * Render PDF viewer HTML
 */
function render_pdf_viewer_html($viewerId, $pdfUrl, $height)
{
    $html = '<div id="pdf-wrapper-' . $viewerId . '" class="pdf-wrapper" style="display:none;">';
    $html .= '<div class="pdf-toolbar mb-2">';
    $html .= '<div class="btn-group btn-group-sm">';
    $html .= '<button type="button" class="btn btn-outline-secondary" id="pdf-prev-' . $viewerId . '"><i class="fas fa-chevron-left"></i></button>';
    $html .= '<span class="btn btn-outline-secondary disabled" id="pdf-page-' . $viewerId . '">1 / 1</span>';
    $html .= '<button type="button" class="btn btn-outline-secondary" id="pdf-next-' . $viewerId . '"><i class="fas fa-chevron-right"></i></button>';
    $html .= '</div>';
    $html .= '<div class="btn-group btn-group-sm ms-2">';
    $html .= '<button type="button" class="btn btn-outline-secondary" id="pdf-zoom-out-' . $viewerId . '"><i class="fas fa-search-minus"></i></button>';
    $html .= '<button type="button" class="btn btn-outline-secondary" id="pdf-zoom-in-' . $viewerId . '"><i class="fas fa-search-plus"></i></button>';
    $html .= '</div></div>';
    $html .= '<div id="pdf-container-' . $viewerId . '" style="width:100%;height:' . $height . ';overflow:auto;background:#525659;border-radius:8px;" data-pdf-url="' . htmlspecialchars($pdfUrl) . '">';
    $html .= '<canvas id="pdf-canvas-' . $viewerId . '"></canvas>';
    $html .= '</div></div>';
    
    return $html;
}

/**
 * Render 3D viewer HTML (uses standard digital object uploads)
 */
function render_3d_viewer_html($viewerId, $model, $height, $baseUrl)
{
    // Use standard digital object path
    $modelUrl = $baseUrl . '/uploads/' . trim($model->path ?? '', '/') . '/' . $model->filename;
    $arAttr = !empty($model->ar_enabled) ? 'ar ar-modes="webxr scene-viewer quick-look"' : '';
    $autoRotate = !empty($model->auto_rotate) ? 'auto-rotate' : '';
    $cameraOrbit = $model->camera_orbit ?? '0deg 75deg 105%';
    $bgColor = $model->background_color ?? '#f5f5f5';
    $poster = !empty($model->poster_image) ? 'poster="' . $baseUrl . $model->poster_image . '"' : '';
    
    $html = '<div id="model-wrapper-' . $viewerId . '" class="model-wrapper" style="display:none;">';
    $html .= '<model-viewer id="model-' . $viewerId . '" ';
    $html .= 'src="' . $modelUrl . '" ';
    $html .= $poster . ' ';
    $html .= $arAttr . ' ';
    $html .= $autoRotate . ' ';
    $html .= 'camera-controls touch-action="pan-y" ';
    $html .= 'camera-orbit="' . $cameraOrbit . '" ';
    $html .= 'style="width:100%;height:' . $height . ';background-color:' . $bgColor . ';border-radius:8px;">';
    $html .= '<button slot="ar-button" class="btn btn-primary" style="position:absolute;bottom:16px;right:16px;">';
    $html .= '<i class="fas fa-cube me-1"></i>View in AR</button>';
    $html .= '</model-viewer></div>';
    
    return $html;
}

/**
 * Render audio/video viewer HTML
 */
function render_av_viewer_html($viewerId, $digitalObject, $height, $baseUrl)
{
    $mediaUrl = get_digital_object_url($digitalObject);
    $mimeType = $digitalObject->mimeType ?? 'video/mp4';
    $isAudio = stripos($mimeType, 'audio') !== false;
    
    $html = '<div id="av-wrapper-' . $viewerId . '" class="av-wrapper" style="display:none;">';
    
    if ($isAudio) {
        $html .= '<audio id="audio-' . $viewerId . '" controls style="width:100%;">';
        $html .= '<source src="' . $mediaUrl . '" type="' . $mimeType . '">';
        $html .= 'Your browser does not support the audio element.</audio>';
    } else {
        $html .= '<video id="video-' . $viewerId . '" controls style="width:100%;height:' . $height . ';background:#000;border-radius:8px;">';
        $html .= '<source src="' . $mediaUrl . '" type="' . $mimeType . '">';
        $html .= 'Your browser does not support the video element.</video>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Render thumbnail strip
 */
function render_thumbnail_strip($viewerId, $digitalObjects, $cantaloupeUrl)
{
    $html = '<div class="thumbnail-strip mt-2" id="thumbs-' . $viewerId . '" style="display:flex;gap:8px;overflow-x:auto;padding:8px 0;">';
    
    foreach ($digitalObjects as $index => $do) {
        $iiifId = build_iiif_identifier($do->path, $do->name);
        $thumbUrl = $cantaloupeUrl . '/' . urlencode($iiifId) . '/full/100,/0/default.jpg';
        $activeClass = $index === 0 ? 'active' : '';
        
        $html .= '<div class="thumb-item ' . $activeClass . '" data-index="' . $index . '" style="flex-shrink:0;cursor:pointer;border:2px solid transparent;border-radius:4px;">';
        $html .= '<img src="' . $thumbUrl . '" alt="Page ' . ($index + 1) . '" style="height:80px;display:block;">';
        $html .= '</div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Render viewer JavaScript initialization
 */
function render_viewer_javascript($viewerId, $objectId, $manifestUrl, $opts, $config)
{
    $flagsJson = json_encode([
        'has3D' => $config['has3D'],
        'hasPdf' => $config['hasPdf'],
        'hasAV' => $config['hasAV'],
        'enableAnnotations' => $opts['enable_annotations'],
    ]);
    
    $osdConfig = json_encode([
        'showNavigator' => true,
        'navigatorPosition' => 'BOTTOM_RIGHT',
        'showRotationControl' => true,
        'showFlipControl' => true,
        'gestureSettingsMouse' => ['scrollToZoom' => true],
    ]);
    
    $miradorConfig = json_encode([
        'sideBarOpenByDefault' => false,
        'defaultSideBarPanel' => 'info',
    ]);
    
    $js = '<script src="https://cdn.jsdelivr.net/npm/openseadragon@3.1.0/build/openseadragon/openseadragon.min.js"></script>' . "\n";
    $js .= '<script type="module">' . "\n";
    $js .= 'import { IiifViewerManager } from "' . $config['frameworkPath'] . '/public/js/iiif-viewer-manager.js";' . "\n";
    $js .= 'document.addEventListener("DOMContentLoaded", function() {' . "\n";
    $js .= '    const viewer = new IiifViewerManager("' . $viewerId . '", {' . "\n";
    $js .= '        objectId: ' . $objectId . ',' . "\n";
    $js .= '        manifestUrl: "' . $manifestUrl . '",' . "\n";
    $js .= '        baseUrl: "' . $config['baseUrl'] . '",' . "\n";
    $js .= '        cantaloupeUrl: "' . $config['cantaloupeUrl'] . '",' . "\n";
    $js .= '        frameworkPath: "' . $config['frameworkPath'] . '",' . "\n";
    $js .= '        defaultViewer: "' . $opts['viewer'] . '",' . "\n";
    $js .= '        flags: ' . $flagsJson . ',' . "\n";
    $js .= '        osdConfig: ' . $osdConfig . ',' . "\n";
    $js .= '        miradorConfig: ' . $miradorConfig . "\n";
    $js .= '    });' . "\n";
    $js .= '    viewer.init();' . "\n";
    $js .= '});' . "\n";
    $js .= '</script>' . "\n";
    
    return $js;
}

/**
 * Render standalone 3D model viewer
 */
function render_3d_model_viewer($resource, $options = [])
{
    $model = get_primary_3d_model($resource);
    
    if (!$model) {
        return '';
    }
    
    $baseUrl = sfConfig::get('app_iiif_base_url', 'https://archives.theahg.co.za');
    $height = $options['height'] ?? '600px';
    $viewerId = 'model-viewer-' . $resource->id . '-' . substr(md5(uniqid()), 0, 8);
    
    $html = '<div class="iiif-viewer-container">';
    $html .= render_3d_viewer_html($viewerId, $model, $height, $baseUrl);
    $html .= '</div>';
    
    // Auto-show 3D viewer
    $html .= '<script>';
    $html .= 'document.getElementById("model-wrapper-' . $viewerId . '").style.display = "block";';
    $html .= '</script>';
    
    // Model-viewer script
    $html .= '<script type="module" src="https://ajax.googleapis.com/ajax/libs/model-viewer/3.3.0/model-viewer.min.js"></script>';
    
    return $html;
}

/**
 * Simple function to just render an image via IIIF
 * Useful for thumbnails or simple displays
 */
function render_iiif_image($identifier, $options = [])
{
    $cantaloupeUrl = sfConfig::get('app_iiif_cantaloupe_url', 'https://archives.theahg.co.za/iiif/2');
    
    $region = $options['region'] ?? 'full';
    $size = $options['size'] ?? 'max';
    $rotation = $options['rotation'] ?? '0';
    $quality = $options['quality'] ?? 'default';
    $format = $options['format'] ?? 'jpg';
    
    $url = $cantaloupeUrl . '/' . urlencode($identifier) . '/' . $region . '/' . $size . '/' . $rotation . '/' . $quality . '.' . $format;
    
    $alt = $options['alt'] ?? 'Image';
    $class = $options['class'] ?? '';
    $style = $options['style'] ?? '';
    
    return '<img src="' . htmlspecialchars($url) . '" alt="' . htmlspecialchars($alt) . '" class="' . $class . '" style="' . $style . '">';
}
