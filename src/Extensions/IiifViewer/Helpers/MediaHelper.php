<?php

/**
 * Media Metadata & Transcription Helper
 * 
 * Helper functions to display media metadata and transcriptions in AtoM templates
 * 
 * Install to: /usr/share/nginx/archive/plugins/ahgThemeB5Plugin/lib/helper/MediaHelper.php
 * 
 * Usage:
 *   use_helper('Media');
 *   echo render_media_player($digitalObject);
 *   echo render_media_metadata($digitalObject);
 *   echo render_transcription_panel($digitalObject);
 * 
 * @author Johan Pieterse - The Archive and Heritage Group
 */

/**
 * Render full-featured media player with all controls
 */
function render_media_player($digitalObject, array $options = []): string
{
    if (!$digitalObject || !is_media_file($digitalObject)) {
        return '';
    }
    
    $baseUrl = sfConfig::get('app_iiif_base_url', '');
    $id = is_object($digitalObject) ? $digitalObject->id : $digitalObject['id'];
    $objectId = is_object($digitalObject) ? $digitalObject->object_id : ($digitalObject['object_id'] ?? 0);
    $name = is_object($digitalObject) ? $digitalObject->name : ($digitalObject['name'] ?? '');
    $path = is_object($digitalObject) ? $digitalObject->path : ($digitalObject['path'] ?? '');
    
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $isVideo = in_array($ext, ['mov', 'mp4', 'avi', 'mkv', 'webm', 'wmv', 'flv', 'm4v', 'mpeg', 'mpg']);
    
    $mediaUrl = $baseUrl . '/uploads/' . trim($path, '/') . '/' . $name;
    
    // Get derivatives
    $derivatives = get_media_derivatives($id);
    $waveformUrl = $derivatives['waveform']['path'] ?? null;
    $posterUrl = !empty($derivatives['posters']) 
        ? $derivatives['posters'][0]['url'] ?? $derivatives['posters'][0]['path'] ?? null 
        : null;
    
    // Get transcription info
    $transcription = get_transcription($id);
    $transcriptUrl = $transcription ? $baseUrl . '/media/transcription/' . $id : null;
    
    $containerId = 'media-player-' . $id;
    $height = $options['height'] ?? ($isVideo ? '400px' : '200px');
    $theme = $options['theme'] ?? 'dark';
    $allowSnippets = $options['allow_snippets'] ?? true;
    
    $html = '<div id="' . $containerId . '" style="height:' . $height . ';"></div>';
    
    // Include player script (locally hosted - no CDN needed)
    $html .= '<script src="' . $baseUrl . '/atom-framework/src/Extensions/IiifViewer/public/js/atom-media-player.js"></script>';
    
    $html .= '<script>';
    $html .= 'document.addEventListener("DOMContentLoaded", function() {';
    $html .= '  new AtomMediaPlayer("#' . $containerId . '", {';
    $html .= '    mediaUrl: "' . htmlspecialchars($mediaUrl) . '",';
    $html .= '    mediaType: "' . ($isVideo ? 'video' : 'audio') . '",';
    $html .= '    digitalObjectId: ' . $id . ',';
    $html .= '    objectId: ' . $objectId . ',';
    
    if ($waveformUrl) {
        $html .= '    waveformUrl: "' . $baseUrl . htmlspecialchars($waveformUrl) . '",';
    }
    if ($transcriptUrl) {
        $html .= '    transcriptUrl: "' . $transcriptUrl . '",';
    }
    if ($posterUrl && $isVideo) {
        $html .= '    poster: "' . $baseUrl . htmlspecialchars($posterUrl) . '",';
    }
    
    $html .= '    snippetsUrl: "' . $baseUrl . '/media/snippets",';
    $html .= '    theme: "' . $theme . '",';
    $html .= '    allowSnippets: ' . ($allowSnippets ? 'true' : 'false') . ',';
    $html .= '    skipSeconds: 10';
    $html .= '  });';
    $html .= '});';
    $html .= '</script>';
    
    return $html;
}

/**
 * Render simple HTML5 player (for basic needs/fallback)
 */
function render_simple_media_player($digitalObject, array $options = []): string
{
    if (!$digitalObject || !is_media_file($digitalObject)) {
        return '';
    }
    
    $baseUrl = sfConfig::get('app_iiif_base_url', '');
    $name = is_object($digitalObject) ? $digitalObject->name : ($digitalObject['name'] ?? '');
    $path = is_object($digitalObject) ? $digitalObject->path : ($digitalObject['path'] ?? '');
    
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $isVideo = in_array($ext, ['mov', 'mp4', 'avi', 'mkv', 'webm', 'wmv', 'flv', 'm4v', 'mpeg', 'mpg']);
    
    $mediaUrl = $baseUrl . '/uploads/' . trim($path, '/') . '/' . $name;
    
    $id = is_object($digitalObject) ? $digitalObject->id : $digitalObject['id'];
    $derivatives = get_media_derivatives($id);
    $posterUrl = !empty($derivatives['posters']) 
        ? $baseUrl . ($derivatives['posters'][0]['url'] ?? $derivatives['posters'][0]['path'] ?? '')
        : null;
    
    if ($isVideo) {
        $poster = $posterUrl ? 'poster="' . htmlspecialchars($posterUrl) . '"' : '';
        return '<video controls ' . $poster . ' style="width:100%;max-height:500px;" preload="metadata">
            <source src="' . htmlspecialchars($mediaUrl) . '" type="video/' . $ext . '">
            Your browser does not support the video tag.
        </video>';
    } else {
        return '<audio controls style="width:100%;" preload="metadata">
            <source src="' . htmlspecialchars($mediaUrl) . '" type="audio/' . $ext . '">
            Your browser does not support the audio tag.
        </audio>';
    }
}

/**
 * Render media metadata panel
 */
function render_media_metadata($digitalObject, array $options = []): string
{
    if (!$digitalObject || !is_media_file($digitalObject)) {
        return '';
    }
    
    $metadata = get_media_metadata($digitalObject->id);
    
    if (!$metadata) {
        // Show extraction button
        return render_extraction_button($digitalObject->id);
    }
    
    $consolidated = json_decode($metadata->consolidated_metadata, true) ?? [];
    
    $html = '<div class="media-metadata-panel card mb-3">';
    $html .= '<div class="card-header d-flex justify-content-between align-items-center">';
    $html .= '<span><i class="fas fa-info-circle me-2"></i>Media Information</span>';
    $html .= '<span class="badge bg-' . ($metadata->media_type === 'audio' ? 'info' : 'primary') . '">';
    $html .= ucfirst($metadata->media_type) . ' - ' . strtoupper($metadata->format);
    $html .= '</span></div>';
    
    $html .= '<div class="card-body">';
    
    // Technical specs
    $html .= '<div class="row">';
    
    // Duration & file info
    $html .= '<div class="col-md-6">';
    $html .= '<h6 class="text-muted mb-2">Technical Details</h6>';
    $html .= '<table class="table table-sm table-borderless">';
    
    if ($metadata->duration) {
        $html .= '<tr><td class="text-muted">Duration:</td><td>' . format_duration($metadata->duration) . '</td></tr>';
    }
    
    $html .= '<tr><td class="text-muted">File Size:</td><td>' . format_file_size($metadata->file_size) . '</td></tr>';
    
    if ($metadata->bitrate) {
        $html .= '<tr><td class="text-muted">Bitrate:</td><td>' . format_bitrate($metadata->bitrate) . '</td></tr>';
    }
    
    // Audio info
    if ($metadata->audio_codec) {
        $html .= '<tr><td class="text-muted">Audio Codec:</td><td>' . $metadata->audio_codec . '</td></tr>';
        $html .= '<tr><td class="text-muted">Sample Rate:</td><td>' . number_format($metadata->audio_sample_rate) . ' Hz</td></tr>';
        $html .= '<tr><td class="text-muted">Channels:</td><td>' . format_channels($metadata->audio_channels) . '</td></tr>';
        
        if ($metadata->audio_bits_per_sample) {
            $html .= '<tr><td class="text-muted">Bit Depth:</td><td>' . $metadata->audio_bits_per_sample . '-bit</td></tr>';
        }
    }
    
    // Video info
    if ($metadata->video_codec) {
        $html .= '<tr><td class="text-muted">Video Codec:</td><td>' . $metadata->video_codec . '</td></tr>';
        $html .= '<tr><td class="text-muted">Resolution:</td><td>' . $metadata->video_width . ' × ' . $metadata->video_height . '</td></tr>';
        $html .= '<tr><td class="text-muted">Frame Rate:</td><td>' . round($metadata->video_frame_rate, 2) . ' fps</td></tr>';
    }
    
    $html .= '</table></div>';
    
    // Tags/Embedded metadata
    $html .= '<div class="col-md-6">';
    $html .= '<h6 class="text-muted mb-2">Embedded Metadata</h6>';
    $html .= '<table class="table table-sm table-borderless">';
    
    if ($metadata->title) {
        $html .= '<tr><td class="text-muted">Title:</td><td>' . htmlspecialchars($metadata->title) . '</td></tr>';
    }
    if ($metadata->artist) {
        $html .= '<tr><td class="text-muted">Artist:</td><td>' . htmlspecialchars($metadata->artist) . '</td></tr>';
    }
    if ($metadata->album) {
        $html .= '<tr><td class="text-muted">Album:</td><td>' . htmlspecialchars($metadata->album) . '</td></tr>';
    }
    if ($metadata->genre) {
        $html .= '<tr><td class="text-muted">Genre:</td><td>' . htmlspecialchars($metadata->genre) . '</td></tr>';
    }
    if ($metadata->year) {
        $html .= '<tr><td class="text-muted">Year:</td><td>' . htmlspecialchars($metadata->year) . '</td></tr>';
    }
    if ($metadata->copyright) {
        $html .= '<tr><td class="text-muted">Copyright:</td><td>' . htmlspecialchars($metadata->copyright) . '</td></tr>';
    }
    if ($metadata->make) {
        $html .= '<tr><td class="text-muted">Device:</td><td>' . htmlspecialchars($metadata->make . ' ' . $metadata->model) . '</td></tr>';
    }
    
    $html .= '</table></div>';
    $html .= '</div>'; // row
    
    // Waveform
    if ($metadata->waveform_path && $metadata->media_type === 'audio') {
        $baseUrl = sfConfig::get('app_iiif_base_url', '');
        $html .= '<div class="waveform-container mt-3">';
        $html .= '<h6 class="text-muted mb-2">Waveform</h6>';
        $html .= '<img src="' . $baseUrl . htmlspecialchars($metadata->waveform_path) . '" ';
        $html .= 'alt="Audio waveform" class="img-fluid rounded" style="background:#1a1a1a;width:100%;">';
        $html .= '</div>';
    }
    
    $html .= '</div>'; // card-body
    $html .= '</div>'; // card
    
    return $html;
}

/**
 * Render transcription panel with interactive features
 */
function render_transcription_panel($digitalObject, array $options = []): string
{
    if (!$digitalObject || !is_media_file($digitalObject)) {
        return '';
    }
    
    $transcription = get_transcription($digitalObject->id);
    
    if (!$transcription) {
        return render_transcribe_button($digitalObject->id);
    }
    
    $data = json_decode($transcription->transcription_data, true);
    $segments = $data['segments'] ?? [];
    
    $html = '<div class="transcription-panel card mb-3" id="transcription-panel-' . $digitalObject->id . '">';
    
    // Header
    $html .= '<div class="card-header d-flex justify-content-between align-items-center">';
    $html .= '<span><i class="fas fa-file-alt me-2"></i>Transcription</span>';
    $html .= '<div class="btn-group btn-group-sm">';
    
    // Download buttons
    if ($transcription->vtt_path) {
        $html .= '<a href="' . htmlspecialchars($transcription->vtt_path) . '" class="btn btn-outline-secondary" title="Download VTT">';
        $html .= '<i class="fas fa-closed-captioning"></i> VTT</a>';
    }
    if ($transcription->srt_path) {
        $html .= '<a href="' . htmlspecialchars($transcription->srt_path) . '" class="btn btn-outline-secondary" title="Download SRT">';
        $html .= '<i class="fas fa-file-video"></i> SRT</a>';
    }
    if ($transcription->txt_path) {
        $html .= '<a href="' . htmlspecialchars($transcription->txt_path) . '" class="btn btn-outline-secondary" title="Download Text">';
        $html .= '<i class="fas fa-file-alt"></i> TXT</a>';
    }
    
    $html .= '</div></div>';
    
    // Info bar
    $html .= '<div class="card-body py-2 bg-light border-bottom">';
    $html .= '<small class="text-muted">';
    $html .= '<i class="fas fa-language me-1"></i>' . get_language_name($transcription->language);
    $html .= ' &bull; <i class="fas fa-clock me-1"></i>' . format_duration($transcription->duration);
    $html .= ' &bull; <i class="fas fa-paragraph me-1"></i>' . $transcription->segment_count . ' segments';
    
    if ($transcription->confidence) {
        $confidenceClass = $transcription->confidence > 70 ? 'success' : ($transcription->confidence > 50 ? 'warning' : 'danger');
        $html .= ' &bull; <span class="badge bg-' . $confidenceClass . '">' . round($transcription->confidence) . '% confidence</span>';
    }
    
    $html .= '</small></div>';
    
    // Search box
    $html .= '<div class="card-body py-2 border-bottom">';
    $html .= '<div class="input-group input-group-sm">';
    $html .= '<input type="text" class="form-control" id="transcript-search-' . $digitalObject->id . '" ';
    $html .= 'placeholder="Search in transcript...">';
    $html .= '<button class="btn btn-outline-secondary" type="button" id="transcript-search-btn-' . $digitalObject->id . '">';
    $html .= '<i class="fas fa-search"></i></button></div></div>';
    
    // Transcript content
    $html .= '<div class="card-body transcript-content" style="max-height:400px;overflow-y:auto;">';
    
    if (empty($options['segments_only'])) {
        // Full text view
        $html .= '<div class="transcript-full-text" style="white-space:pre-wrap;line-height:1.8;">';
        $html .= htmlspecialchars($transcription->full_text);
        $html .= '</div>';
    }
    
    // Segments view (for click-to-seek)
    $html .= '<div class="transcript-segments" style="display:none;">';
    
    foreach ($segments as $index => $segment) {
        $html .= '<div class="transcript-segment" data-start="' . $segment['start'] . '" ';
        $html .= 'data-end="' . $segment['end'] . '" data-index="' . $index . '" ';
        $html .= 'style="cursor:pointer;padding:4px 8px;border-radius:4px;margin:2px 0;" ';
        $html .= 'onmouseover="this.style.background=\'#f0f0f0\'" onmouseout="this.style.background=\'transparent\'">';
        $html .= '<small class="text-muted me-2">[' . format_duration($segment['start']) . ']</small>';
        $html .= htmlspecialchars(trim($segment['text']));
        $html .= '</div>';
    }
    
    $html .= '</div>'; // segments
    $html .= '</div>'; // card-body
    
    // Footer with view toggle
    $html .= '<div class="card-footer py-2">';
    $html .= '<div class="btn-group btn-group-sm">';
    $html .= '<button class="btn btn-outline-secondary active" data-view="text" id="btn-text-' . $digitalObject->id . '">';
    $html .= '<i class="fas fa-align-left"></i> Full Text</button>';
    $html .= '<button class="btn btn-outline-secondary" data-view="segments" id="btn-segments-' . $digitalObject->id . '">';
    $html .= '<i class="fas fa-list"></i> Timed Segments</button>';
    $html .= '</div></div>';
    
    $html .= '</div>'; // card
    
    // JavaScript for interactivity
    $html .= render_transcription_javascript($digitalObject->id);
    
    return $html;
}

/**
 * Render extraction button for when metadata hasn't been extracted
 */
function render_extraction_button(int $digitalObjectId): string
{
    $html = '<div class="media-extraction-prompt card mb-3">';
    $html .= '<div class="card-body text-center py-4">';
    $html .= '<i class="fas fa-music fa-2x text-muted mb-3"></i>';
    $html .= '<p class="text-muted mb-3">Media metadata has not been extracted yet.</p>';
    $html .= '<button class="btn btn-primary" onclick="extractMediaMetadata(' . $digitalObjectId . ')">';
    $html .= '<i class="fas fa-magic me-1"></i>Extract Metadata</button>';
    $html .= '</div></div>';
    
    return $html;
}

/**
 * Render transcription button for when transcription hasn't been done
 */
function render_transcribe_button(int $digitalObjectId): string
{
    $html = '<div class="transcription-prompt card mb-3">';
    $html .= '<div class="card-body text-center py-4">';
    $html .= '<i class="fas fa-microphone fa-2x text-muted mb-3"></i>';
    $html .= '<p class="text-muted mb-3">This audio/video has not been transcribed yet.</p>';
    $html .= '<div class="d-flex justify-content-center gap-2 flex-wrap">';
    $html .= '<button class="btn btn-primary" onclick="transcribeMedia(' . $digitalObjectId . ', \'en\')">';
    $html .= '<i class="fas fa-language me-1"></i>Transcribe (English)</button>';
    $html .= '<button class="btn btn-outline-primary" onclick="transcribeMedia(' . $digitalObjectId . ', \'af\')">';
    $html .= 'Afrikaans</button>';
    $html .= '<button class="btn btn-outline-secondary" onclick="showTranscribeOptions(' . $digitalObjectId . ')">';
    $html .= '<i class="fas fa-cog"></i> More Options</button>';
    $html .= '</div></div></div>';
    
    return $html;
}

/**
 * Render JavaScript for transcription interactivity
 */
function render_transcription_javascript(int $digitalObjectId): string
{
    return <<<JS
<script>
(function() {
    const doId = {$digitalObjectId};
    const panel = document.getElementById('transcription-panel-' + doId);
    if (!panel) return;
    
    // View toggle
    const btnText = panel.querySelector('#btn-text-' + doId);
    const btnSegments = panel.querySelector('#btn-segments-' + doId);
    const fullText = panel.querySelector('.transcript-full-text');
    const segments = panel.querySelector('.transcript-segments');
    
    if (btnText && btnSegments) {
        btnText.onclick = function() {
            fullText.style.display = 'block';
            segments.style.display = 'none';
            btnText.classList.add('active');
            btnSegments.classList.remove('active');
        };
        
        btnSegments.onclick = function() {
            fullText.style.display = 'none';
            segments.style.display = 'block';
            btnText.classList.remove('active');
            btnSegments.classList.add('active');
        };
    }
    
    // Click to seek
    panel.querySelectorAll('.transcript-segment').forEach(function(seg) {
        seg.onclick = function() {
            const start = parseFloat(seg.dataset.start);
            // Try to find audio/video player and seek
            const player = document.querySelector('audio, video');
            if (player) {
                player.currentTime = start;
                player.play();
            }
            
            // Highlight current segment
            panel.querySelectorAll('.transcript-segment').forEach(s => s.style.background = 'transparent');
            seg.style.background = '#fff3cd';
        };
    });
    
    // Search
    const searchInput = panel.querySelector('#transcript-search-' + doId);
    const searchBtn = panel.querySelector('#transcript-search-btn-' + doId);
    
    function doSearch() {
        const query = searchInput.value.toLowerCase().trim();
        if (!query) return;
        
        panel.querySelectorAll('.transcript-segment').forEach(function(seg) {
            const text = seg.textContent.toLowerCase();
            if (text.includes(query)) {
                seg.style.background = '#d4edda';
                seg.style.display = 'block';
            } else {
                seg.style.display = 'none';
            }
        });
        
        // Show segments view
        if (btnSegments) btnSegments.click();
    }
    
    if (searchBtn) searchBtn.onclick = doSearch;
    if (searchInput) searchInput.onkeypress = function(e) {
        if (e.key === 'Enter') doSearch();
    };
})();
</script>
JS;
}

// ========================================================================
// Helper Functions
// ========================================================================

function is_media_file($digitalObject): bool
{
    if (!$digitalObject) return false;
    
    $name = is_object($digitalObject) ? $digitalObject->name : ($digitalObject['name'] ?? '');
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    
    $audioFormats = ['wav', 'mp3', 'flac', 'ogg', 'oga', 'm4a', 'aac', 'wma', 'aiff', 'aif'];
    $videoFormats = ['mov', 'mp4', 'avi', 'mkv', 'webm', 'wmv', 'flv', 'm4v', 'mpeg', 'mpg', '3gp'];
    
    return in_array($ext, $audioFormats) || in_array($ext, $videoFormats);
}

function get_media_metadata(int $digitalObjectId): ?object
{
    try {
        $result = \Illuminate\Database\Capsule\Manager::table('media_metadata')
            ->where('digital_object_id', $digitalObjectId)
            ->first();
        return $result ?: null;
    } catch (Exception $e) {
        return null;
    }
}

function get_transcription(int $digitalObjectId): ?object
{
    try {
        $result = \Illuminate\Database\Capsule\Manager::table('media_transcription')
            ->where('digital_object_id', $digitalObjectId)
            ->first();
        return $result ?: null;
    } catch (Exception $e) {
        return null;
    }
}

function format_duration(float $seconds): string
{
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = floor($seconds % 60);
    
    if ($hours > 0) {
        return sprintf('%d:%02d:%02d', $hours, $minutes, $secs);
    }
    return sprintf('%d:%02d', $minutes, $secs);
}

function format_file_size(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

function format_bitrate(int $bitrate): string
{
    if ($bitrate >= 1000000) {
        return round($bitrate / 1000000, 2) . ' Mbps';
    }
    return round($bitrate / 1000) . ' kbps';
}

function format_channels(int $channels): string
{
    $names = [1 => 'Mono', 2 => 'Stereo', 6 => '5.1 Surround', 8 => '7.1 Surround'];
    return $names[$channels] ?? $channels . ' channels';
}

function get_language_name(string $code): string
{
    $languages = [
        'en' => 'English', 'af' => 'Afrikaans', 'nl' => 'Dutch', 'de' => 'German',
        'fr' => 'French', 'es' => 'Spanish', 'pt' => 'Portuguese', 'it' => 'Italian',
        'pl' => 'Polish', 'ru' => 'Russian', 'zh' => 'Chinese', 'ja' => 'Japanese',
        'ko' => 'Korean', 'ar' => 'Arabic', 'hi' => 'Hindi', 'zu' => 'Zulu',
        'xh' => 'Xhosa', 'st' => 'Sesotho',
    ];
    return $languages[$code] ?? $code;
}

/**
 * Get media derivatives (thumbnails, previews, waveforms)
 */
function get_media_derivatives(int $digitalObjectId): array
{
    try {
        $rows = \Illuminate\Database\Capsule\Manager::table('media_derivatives')
            ->where('digital_object_id', $digitalObjectId)
            ->orderBy('derivative_type')
            ->orderBy('derivative_index')
            ->select('derivative_type', 'derivative_index', 'path', 'metadata')
            ->get();

        $derivatives = [];
        foreach ($rows as $row) {
            $type = $row->derivative_type;

            $item = ['path' => $row->path];
            if ($row->metadata) {
                $item = array_merge($item, json_decode($row->metadata, true) ?: []);
            }

            if (!isset($derivatives[$type])) {
                $derivatives[$type] = [];
            }
            $derivatives[$type][] = $item;
        }

        // Flatten single items
        foreach ($derivatives as $type => $items) {
            if (count($items) === 1 && $type !== 'posters') {
                $derivatives[$type] = $items[0];
            }
        }

        return $derivatives;
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Get snippets for a digital object
 */
function get_snippets(int $digitalObjectId): array
{
    try {
        return \Illuminate\Database\Capsule\Manager::table('media_snippets')
            ->where('digital_object_id', $digitalObjectId)
            ->orderBy('start_time')
            ->get()
            ->all();
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Render snippets list
 */
function render_snippets_list($digitalObject, array $options = []): string
{
    if (!$digitalObject) return '';
    
    $id = is_object($digitalObject) ? $digitalObject->id : $digitalObject['id'];
    $snippets = get_snippets($id);
    
    if (empty($snippets)) {
        return '<div class="text-muted">No saved snippets</div>';
    }
    
    $baseUrl = sfConfig::get('app_iiif_base_url', '');
    
    $html = '<div class="snippets-list">';
    
    foreach ($snippets as $snippet) {
        $html .= '<div class="snippet-item card mb-2">';
        $html .= '<div class="card-body py-2">';
        $html .= '<div class="d-flex justify-content-between align-items-center">';
        $html .= '<div>';
        $html .= '<strong>' . htmlspecialchars($snippet->title) . '</strong>';
        $html .= '<div class="small text-muted">';
        $html .= format_duration($snippet->start_time) . ' → ' . format_duration($snippet->end_time);
        $html .= ' (' . format_duration($snippet->duration) . ')';
        $html .= '</div></div>';
        $html .= '<div class="btn-group btn-group-sm">';
        $html .= '<button class="btn btn-outline-primary" onclick="playSnippet(' . $snippet->id . ', ' . $snippet->start_time . ', ' . $snippet->end_time . ')">';
        $html .= '<i class="fas fa-play"></i></button>';
        
        if (!empty($snippet->export_path)) {
            $html .= '<a class="btn btn-outline-secondary" href="' . $baseUrl . htmlspecialchars($snippet->export_path) . '" download>';
            $html .= '<i class="fas fa-download"></i></a>';
        }
        
        $html .= '</div></div></div></div>';
    }
    
    $html .= '</div>';
    
    return $html;
}

/**
 * Render settings admin interface
 */
function render_media_settings_admin(): string
{
    $baseUrl = sfConfig::get('app_iiif_base_url', '');
    
    $html = '<div class="media-settings-admin card">';
    $html .= '<div class="card-header"><h5 class="mb-0">Media Processing Settings</h5></div>';
    $html .= '<div class="card-body" id="media-settings-form">';
    $html .= '<div class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading settings...</div>';
    $html .= '</div></div>';
    
    $html .= '<script>';
    $html .= 'document.addEventListener("DOMContentLoaded", function() {';
    $html .= '  loadMediaSettings();';
    $html .= '});';
    $html .= '
function loadMediaSettings() {
    fetch("' . $baseUrl . '/media/settings")
        .then(r => r.json())
        .then(data => {
            renderSettingsForm(data.grouped);
        })
        .catch(err => {
            document.getElementById("media-settings-form").innerHTML = 
                "<div class=\"alert alert-danger\">Failed to load settings</div>";
        });
}

function renderSettingsForm(grouped) {
    let html = "";
    
    const groupLabels = {
        thumbnail: "Thumbnail Generation",
        preview: "Preview Clips",
        waveform: "Audio Waveform",
        poster: "Video Posters",
        audio: "Audio Processing",
        transcription: "Transcription"
    };
    
    for (const [group, settings] of Object.entries(grouped)) {
        html += `<div class="settings-group mb-4">`;
        html += `<h6 class="text-muted mb-3">${groupLabels[group] || group}</h6>`;
        
        for (const setting of settings) {
            html += `<div class="row mb-2">`;
            html += `<label class="col-md-4 col-form-label">${setting.description || setting.key}</label>`;
            html += `<div class="col-md-8">`;
            
            if (setting.type === "boolean") {
                const checked = setting.value ? "checked" : "";
                html += `<div class="form-check form-switch">`;
                html += `<input class="form-check-input setting-input" type="checkbox" data-key="${setting.key}" data-type="boolean" ${checked}>`;
                html += `</div>`;
            } else if (setting.type === "integer" || setting.type === "float") {
                html += `<input type="number" class="form-control form-control-sm setting-input" data-key="${setting.key}" data-type="${setting.type}" value="${setting.value}">`;
            } else if (setting.type === "json") {
                html += `<input type="text" class="form-control form-control-sm setting-input" data-key="${setting.key}" data-type="json" value=\'${JSON.stringify(setting.value)}\'>`;
            } else {
                html += `<input type="text" class="form-control form-control-sm setting-input" data-key="${setting.key}" data-type="string" value="${setting.value}">`;
            }
            
            html += `</div></div>`;
        }
        
        html += `</div>`;
    }
    
    html += `<button class="btn btn-primary" onclick="saveMediaSettings()">Save Settings</button>`;
    
    document.getElementById("media-settings-form").innerHTML = html;
}

function saveMediaSettings() {
    const settings = {};
    
    document.querySelectorAll(".setting-input").forEach(input => {
        const key = input.dataset.key;
        const type = input.dataset.type;
        let value;
        
        if (type === "boolean") {
            value = input.checked;
        } else if (type === "integer") {
            value = parseInt(input.value);
        } else if (type === "float") {
            value = parseFloat(input.value);
        } else if (type === "json") {
            try {
                value = JSON.parse(input.value);
            } catch (e) {
                value = input.value;
            }
        } else {
            value = input.value;
        }
        
        settings[key] = { value, type };
    });
    
    fetch("' . $baseUrl . '/media/settings", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(settings)
    })
    .then(r => r.json())
    .then(data => {
        alert("Settings saved successfully!");
    })
    .catch(err => {
        alert("Failed to save settings");
    });
}
';
    $html .= '</script>';
    
    return $html;
}
