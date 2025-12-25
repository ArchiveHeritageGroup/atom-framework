<?php

namespace AtomFramework\Extensions\IiifViewer\Controllers;

use Illuminate\Database\Capsule\Manager as DB;

class MediaController
{
    private array $config;

    public function __construct(array $config = [])
    {
        $this->config = $config;
    }

    private function jsonResponse($data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        echo json_encode($data);
        exit;
    }

    public function lookup(string $slug): void
    {
        $result = DB::table('slug')
            ->join('information_object', 'slug.object_id', '=', 'information_object.id')
            ->join('digital_object', 'digital_object.object_id', '=', 'information_object.id')
            ->where('slug.slug', $slug)
            ->select('digital_object.id as digital_object_id')
            ->first();

        if (!$result) {
            $this->jsonResponse(['error' => 'Not found'], 404);
            return;
        }

        $doId = $result->digital_object_id;

        $hasMeta = DB::table('media_metadata')->where('digital_object_id', $doId)->exists();
        $hasTrans = DB::table('media_transcription')->where('digital_object_id', $doId)->exists();

        $this->jsonResponse([
            'digital_object_id' => (int)$doId,
            'has_metadata' => $hasMeta,
            'has_transcription' => $hasTrans
        ]);
    }

    public function getMetadata(int $id): void
    {
        $meta = DB::table('media_metadata')->where('digital_object_id', $id)->first();

        if (!$meta) {
            $this->jsonResponse(['error' => 'Not found'], 404);
            return;
        }

        // Map to expected format for JS
        $this->jsonResponse([
            'digital_object_id' => $meta->digital_object_id,
            'duration' => $meta->duration,
            'bitrate' => $meta->bitrate,
            'format' => $meta->format,
            'width' => $meta->video_width,
            'height' => $meta->video_height,
            'video_codec' => $meta->video_codec,
            'frame_rate' => $meta->video_frame_rate,
            'audio_codec' => $meta->audio_codec,
            'sample_rate' => $meta->audio_sample_rate,
            'channels' => $meta->audio_channels
        ]);
    }

    public function extractMetadata(int $id): void
    {
        $do = DB::table('digital_object')->where('id', $id)->first();

        if (!$do) {
            $this->jsonResponse(['success' => false, 'error' => 'Digital object not found']);
            return;
        }

        $uploadDir = dirname(__DIR__, 5);
        $filePath = $uploadDir . '/' . ltrim($do->path, '/') . $do->name;

        if (!file_exists($filePath)) {
            $this->jsonResponse(['success' => false, 'error' => 'File not found: ' . $filePath]);
            return;
        }

        $cmd = "ffprobe -v quiet -print_format json -show_format -show_streams " . escapeshellarg($filePath) . " 2>&1";
        $output = shell_exec($cmd);
        $data = json_decode($output, true);

        if (!$data) {
            $this->jsonResponse(['success' => false, 'error' => 'Failed to extract metadata']);
            return;
        }

        $format = $data['format'] ?? [];
        $videoStream = null;
        $audioStream = null;

        foreach (($data['streams'] ?? []) as $stream) {
            if ($stream['codec_type'] === 'video' && !$videoStream) {
                $videoStream = $stream;
            } elseif ($stream['codec_type'] === 'audio' && !$audioStream) {
                $audioStream = $stream;
            }
        }

        $frameRate = null;
        if (isset($videoStream['r_frame_rate'])) {
            $parts = explode('/', $videoStream['r_frame_rate']);
            if (count($parts) == 2 && $parts[1] != 0) {
                $frameRate = round($parts[0] / $parts[1], 3);
            }
        }

        // Determine media type
        $mediaType = $videoStream ? 'video' : 'audio';

        $metaData = [
            'digital_object_id' => $id,
            'object_id' => $do->object_id,
            'media_type' => $mediaType,
            'format' => $format['format_name'] ?? null,
            'file_size' => $format['size'] ?? null,
            'duration' => $format['duration'] ?? null,
            'bitrate' => $format['bit_rate'] ?? null,
            'video_codec' => $videoStream['codec_name'] ?? null,
            'video_width' => $videoStream['width'] ?? null,
            'video_height' => $videoStream['height'] ?? null,
            'video_frame_rate' => $frameRate,
            'video_pixel_format' => $videoStream['pix_fmt'] ?? null,
            'video_aspect_ratio' => $videoStream['display_aspect_ratio'] ?? null,
            'audio_codec' => $audioStream['codec_name'] ?? null,
            'audio_sample_rate' => $audioStream['sample_rate'] ?? null,
            'audio_channels' => $audioStream['channels'] ?? null,
            'audio_channel_layout' => $audioStream['channel_layout'] ?? null,
            'raw_metadata' => json_encode($data),
            'extracted_at' => date('Y-m-d H:i:s')
        ];

        $existing = DB::table('media_metadata')->where('digital_object_id', $id)->first();

        if ($existing) {
            DB::table('media_metadata')->where('digital_object_id', $id)->update($metaData);
        } else {
            DB::table('media_metadata')->insert($metaData);
        }

        $this->jsonResponse(['success' => true]);
    }

    public function getTranscription(int $id): void
    {
        $trans = DB::table('media_transcription')->where('digital_object_id', $id)->first();

        if (!$trans) {
            $this->jsonResponse(['error' => 'Not found'], 404);
            return;
        }

        $data = json_decode($trans->transcription_data, true);

        $this->jsonResponse([
            'language' => $trans->language,
            'full_text' => $trans->full_text,
            'segments' => $data['segments'] ?? []
        ]);
    }

    public function transcribe(int $id): void
    {
        $do = DB::table('digital_object')->where('id', $id)->first();

        if (!$do) {
            $this->jsonResponse(['success' => false, 'error' => 'Digital object not found']);
            return;
        }

        $uploadDir = dirname(__DIR__, 5);
        $filePath = $uploadDir . '/' . ltrim($do->path, '/') . $do->name;

        if (!file_exists($filePath)) {
            $this->jsonResponse(['success' => false, 'error' => 'File not found']);
            return;
        }

        // Get settings from correct columns
        $model = DB::table('media_processor_settings')->where('setting_key', 'whisper_model')->value('setting_value') ?? 'base';
        $language = DB::table('media_processor_settings')->where('setting_key', 'default_language')->value('setting_value') ?? 'en';

        $tempDir = sys_get_temp_dir() . '/whisper_' . $id . '_' . time();
        @mkdir($tempDir, 0755, true);

        $cmd = sprintf(
            'whisper %s --model %s --language %s --output_format json --output_dir %s 2>&1',
            escapeshellarg($filePath),
            escapeshellarg($model),
            escapeshellarg($language),
            escapeshellarg($tempDir)
        );

        $output = shell_exec($cmd);

        $baseName = pathinfo($do->name, PATHINFO_FILENAME);
        $jsonFile = $tempDir . '/' . $baseName . '.json';

        if (!file_exists($jsonFile)) {
            $this->jsonResponse(['success' => false, 'error' => 'Transcription failed: ' . $output]);
            return;
        }

        $transcription = json_decode(file_get_contents($jsonFile), true);

        $segments = [];
        $fullText = '';

        foreach (($transcription['segments'] ?? []) as $seg) {
            $segments[] = [
                'start' => $seg['start'],
                'end' => $seg['end'],
                'text' => trim($seg['text'])
            ];
            $fullText .= trim($seg['text']) . ' ';
        }

        $transData = [
            'digital_object_id' => $id,
            'object_id' => $do->object_id,
            'language' => $transcription['language'] ?? $language,
            'full_text' => trim($fullText),
            'transcription_data' => json_encode($transcription),
            'segment_count' => count($segments),
            'model_used' => $model,
            'updated_at' => date('Y-m-d H:i:s')
        ];

        $existing = DB::table('media_transcription')->where('digital_object_id', $id)->first();

        if ($existing) {
            DB::table('media_transcription')->where('digital_object_id', $id)->update($transData);
        } else {
            $transData['created_at'] = date('Y-m-d H:i:s');
            DB::table('media_transcription')->insert($transData);
        }

        array_map('unlink', glob($tempDir . '/*'));
        @rmdir($tempDir);

        $this->jsonResponse(['success' => true]);
    }

    public function getVtt(int $id): void
    {
        $trans = DB::table('media_transcription')->where('digital_object_id', $id)->first();

        if (!$trans) {
            http_response_code(404);
            echo "Not found";
            exit;
        }

        $data = json_decode($trans->transcription_data, true);
        $segments = $data['segments'] ?? [];

        $vtt = "WEBVTT\n\n";
        foreach ($segments as $seg) {
            $vtt .= $this->formatVttTime($seg['start']) . ' --> ' . $this->formatVttTime($seg['end']) . "\n";
            $vtt .= trim($seg['text']) . "\n\n";
        }

        header('Content-Type: text/vtt');
        header('Content-Disposition: attachment; filename="transcription.vtt"');
        echo $vtt;
        exit;
    }

    public function getSrt(int $id): void
    {
        $trans = DB::table('media_transcription')->where('digital_object_id', $id)->first();

        if (!$trans) {
            http_response_code(404);
            echo "Not found";
            exit;
        }

        $data = json_decode($trans->transcription_data, true);
        $segments = $data['segments'] ?? [];

        $srt = '';
        foreach ($segments as $i => $seg) {
            $srt .= ($i + 1) . "\n";
            $srt .= $this->formatSrtTime($seg['start']) . ' --> ' . $this->formatSrtTime($seg['end']) . "\n";
            $srt .= trim($seg['text']) . "\n\n";
        }

        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="transcription.srt"');
        echo $srt;
        exit;
    }

    public function getWaveform(int $id): void
    {
        $this->jsonResponse(['error' => 'Not implemented'], 501);
    }

    public function getIiifTranscription(int $id): void
    {
        $this->jsonResponse(['error' => 'Not implemented'], 501);
    }

    public function searchTranscriptions(): void
    {
        $query = $_GET['q'] ?? '';
        if (empty($query)) {
            $this->jsonResponse(['results' => []]);
            return;
        }

        $results = DB::table('media_transcription')
            ->where('full_text', 'LIKE', '%' . $query . '%')
            ->select('digital_object_id', 'language', 'full_text')
            ->limit(20)
            ->get();

        $this->jsonResponse(['results' => $results]);
    }

    public function searchWithTimestamps(int $id): void
    {
        $this->jsonResponse(['error' => 'Not implemented'], 501);
    }

    public function addToQueue(): void
    {
        $this->jsonResponse(['error' => 'Not implemented'], 501);
    }

    public function getQueueStatus(int $id): void
    {
        $this->jsonResponse(['error' => 'Not implemented'], 501);
    }

    public function batchExtract(): void
    {
        $this->jsonResponse(['error' => 'Not implemented'], 501);
    }

    public function batchTranscribe(): void
    {
        $this->jsonResponse(['error' => 'Not implemented'], 501);
    }

    public function getStatus(): void
    {
        $this->jsonResponse([
            'status' => 'ok',
            'whisper' => shell_exec('which whisper') ? 'available' : 'not found',
            'ffprobe' => shell_exec('which ffprobe') ? 'available' : 'not found'
        ]);
    }

    public function getSnippets(int $id): void
    {
        $snippets = DB::table('media_snippets')
            ->where('digital_object_id', $id)
            ->orderBy('start_time')
            ->get();
        $this->jsonResponse($snippets->toArray());
    }

    public function createSnippet(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (empty($input['digital_object_id']) || !isset($input['start_time']) || !isset($input['end_time'])) {
            $this->jsonResponse(['error' => 'Missing required fields'], 400);
            return;
        }

        $id = DB::table('media_snippets')->insertGetId([
            'digital_object_id' => $input['digital_object_id'],
            'object_id' => $input['object_id'] ?? 0,
            'title' => $input['title'] ?? 'Untitled Snippet',
            'description' => $input['description'] ?? null,
            'start_time' => $input['start_time'],
            'end_time' => $input['end_time'],
            'duration' => $input['end_time'] - $input['start_time'],
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ]);

        $this->jsonResponse(['success' => true, 'id' => $id]);
    }

    public function updateSnippet(int $id): void
    {
        $this->jsonResponse(['error' => 'Not implemented'], 501);
    }

    public function deleteSnippet(int $id): void
    {
        DB::table('media_snippets')->where('id', $id)->delete();
        $this->jsonResponse(['success' => true]);
    }

    public function exportSnippet(int $id): void
    {
        $snippet = DB::table('media_snippets')->where('id', $id)->first();
        
        if (!$snippet) {
            $this->jsonResponse(['error' => 'Snippet not found'], 404);
            return;
        }

        $do = DB::table('digital_object')->where('id', $snippet->digital_object_id)->first();
        
        if (!$do) {
            $this->jsonResponse(['error' => 'Digital object not found'], 404);
            return;
        }

        $uploadDir = dirname(__DIR__, 5);
        $inputFile = $uploadDir . '/' . ltrim($do->path, '/') . $do->name;
        
        $outputDir = $uploadDir . '/uploads/snippets/' . $snippet->digital_object_id;
        @mkdir($outputDir, 0755, true);
        
        $outputFile = $outputDir . '/snippet_' . $id . '.mp4';
        
        $cmd = sprintf(
            'ffmpeg -y -i %s -ss %s -to %s -c:v libx264 -c:a aac -preset fast %s 2>&1',
            escapeshellarg($inputFile),
            escapeshellarg($snippet->start_time),
            escapeshellarg($snippet->end_time),
            escapeshellarg($outputFile)
        );
        
        $output = shell_exec($cmd);
        
        if (file_exists($outputFile)) {
            DB::table('media_snippets')->where('id', $id)->update([
                'export_path' => '/uploads/snippets/' . $snippet->digital_object_id . '/snippet_' . $id . '.mp4',
                'updated_at' => date('Y-m-d H:i:s')
            ]);
            
            $this->jsonResponse([
                'success' => true,
                'url' => '/uploads/snippets/' . $snippet->digital_object_id . '/snippet_' . $id . '.mp4',
                'filename' => 'snippet_' . $id . '.mp4'
            ]);
        } else {
            $this->jsonResponse(['error' => 'Export failed'], 500);
        }
    }

    public function getDerivatives(int $id): void
    {
        $this->jsonResponse(['error' => 'Not implemented'], 501);
    }

    public function processUpload(int $id): void
    {
        $this->jsonResponse(['error' => 'Not implemented'], 501);
    }

    public function getSettings(): void
    {
        $settings = DB::table('media_processor_settings')->get();
        $this->jsonResponse(['settings' => $settings]);
    }

    public function saveSettings(): void
    {
        $this->jsonResponse(['error' => 'Not implemented'], 501);
    }

    private function formatVttTime($seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        return sprintf('%02d:%02d:%06.3f', $hours, $minutes, $secs);
    }

    private function formatSrtTime($seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = floor($seconds % 60);
        $ms = ($seconds - floor($seconds)) * 1000;
        return sprintf('%02d:%02d:%02d,%03d', $hours, $minutes, $secs, $ms);
    }
}
