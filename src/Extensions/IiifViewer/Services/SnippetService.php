<?php

declare(strict_types=1);

namespace AtomFramework\Extensions\IiifViewer\Services;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

/**
 * Snippet Service
 * 
 * Manages media snippets (clips):
 * - Save/load/delete snippets
 * - Export snippets as separate files
 * - Generate snippet preview thumbnails
 * 
 * @package AtomFramework\Extensions\IiifViewer
 * @author Johan Pieterse - The Archive and Heritage Group
 * @version 1.0.0
 */
class SnippetService
{
    private Logger $logger;
    private string $uploadsDir;
    private string $snippetsDir;
    private array $config;
    
    public function __construct(array $config = [])
    {
		$uploadDir = class_exists('sfConfig') 
			? \sfConfig::get('sf_upload_dir') 
			: '/usr/share/nginx/atom/uploads';

		$logDir = class_exists('sfConfig')
			? \sfConfig::get('sf_log_dir', '/var/log/atom')
			: '/var/log/atom';

		$this->config = array_merge([
			'uploads_dir' => $uploadDir,
			'snippets_dir' => $uploadDir . '/snippets',
			'log_path' => $logDir . '/snippets.log',
			'ffmpeg_path' => '/usr/bin/ffmpeg',
			'ffprobe_path' => '/usr/bin/ffprobe',
			'default_thumbnail_time' => 5,      // seconds into media
			'thumbnail_width' => 320,
			'thumbnail_height' => 180,
			'preview_duration' => 30,           // Default snippet preview duration
		], $config);

        $this->uploadsDir = $this->config['uploads_dir'];
        $this->snippetsDir = $this->config['snippets_dir'];
        
        // Ensure directories exist
        foreach ([$this->snippetsDir, $this->snippetsDir . '/exports', $this->snippetsDir . '/thumbnails'] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
        }
        
        $this->logger = new Logger('snippets');
        if (is_writable(dirname($this->config['log_path']))) {
            $this->logger->pushHandler(new RotatingFileHandler($this->config['log_path'], 30, Logger::INFO));
        }
    }
    
    // ========================================================================
    // CRUD Operations
    // ========================================================================
    
    /**
     * Save a new snippet
     */
    public function saveSnippet(array $data): array
    {
        $id = DB::table('media_snippets')->insertGetId([
            'digital_object_id' => $data['digital_object_id'],
            'object_id' => $data['object_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'start_time' => $data['start_time'],
            'end_time' => $data['end_time'],
            'duration' => $data['end_time'] - $data['start_time'],
            'created_by' => $data['user_id'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        
        $this->logger->info('Snippet saved', ['id' => $id, 'title' => $data['title']]);
        
        return $this->getSnippet($id);
    }
    
    /**
     * Get a single snippet
     */
    public function getSnippet(int $id): ?array
    {
        $snippet = DB::table('media_snippets')->where('id', $id)->first();
        
        if (!$snippet) {
            return null;
        }
        
        return (array)$snippet;
    }
    
    /**
     * Get all snippets for a digital object
     */
    public function getSnippetsForObject(int $digitalObjectId): array
    {
        return DB::table('media_snippets')
            ->where('digital_object_id', $digitalObjectId)
            ->orderBy('start_time')
            ->get()
            ->map(fn($s) => (array)$s)
            ->toArray();
    }
    
    /**
     * Update a snippet
     */
    public function updateSnippet(int $id, array $data): bool
    {
        $update = [];
        
        if (isset($data['title'])) {
            $update['title'] = $data['title'];
        }
        if (isset($data['description'])) {
            $update['description'] = $data['description'];
        }
        if (isset($data['start_time'])) {
            $update['start_time'] = $data['start_time'];
        }
        if (isset($data['end_time'])) {
            $update['end_time'] = $data['end_time'];
        }
        
        if (isset($update['start_time']) && isset($update['end_time'])) {
            $update['duration'] = $update['end_time'] - $update['start_time'];
        }
        
        $update['updated_at'] = date('Y-m-d H:i:s');
        
        return DB::table('media_snippets')->where('id', $id)->update($update) > 0;
    }
    
    /**
     * Delete a snippet
     */
    public function deleteSnippet(int $id): bool
    {
        $snippet = $this->getSnippet($id);
        
        if (!$snippet) {
            return false;
        }
        
        // Delete exported file if exists
        if (!empty($snippet['export_path'])) {
            @unlink($this->snippetsDir . '/exports/' . basename($snippet['export_path']));
        }
        
        // Delete thumbnail if exists
        if (!empty($snippet['thumbnail_path'])) {
            @unlink($this->snippetsDir . '/thumbnails/' . basename($snippet['thumbnail_path']));
        }
        
        return DB::table('media_snippets')->where('id', $id)->delete() > 0;
    }
    
    // ========================================================================
    // Export Operations
    // ========================================================================
    
    /**
     * Export snippet as separate media file
     */
    public function exportSnippet(int $snippetId): ?array
    {
        $snippet = $this->getSnippet($snippetId);
        
        if (!$snippet) {
            return null;
        }
        
        // Get source file
        $do = DB::table('digital_object')->where('id', $snippet['digital_object_id'])->first();
        
        if (!$do) {
            $this->logger->error('Digital object not found for snippet export', ['snippet_id' => $snippetId]);
            return null;
        }
        
        $sourcePath = $this->uploadsDir . '/' . trim($do->path ?? '', '/') . '/' . $do->name;
        
        if (!file_exists($sourcePath)) {
            $this->logger->error('Source file not found', ['path' => $sourcePath]);
            return null;
        }
        
        // Generate export filename
        $ext = pathinfo($do->name, PATHINFO_EXTENSION);
        $safeTitle = preg_replace('/[^a-z0-9]+/i', '_', $snippet['title']);
        $exportFilename = sprintf('snippet_%d_%s.%s', $snippetId, $safeTitle, $ext);
        $exportPath = $this->snippetsDir . '/exports/' . $exportFilename;
        
        // Use FFmpeg to extract snippet
        $ffmpeg = $this->config['ffmpeg_path'];
        
        if (!is_executable($ffmpeg)) {
            $this->logger->error('FFmpeg not available for export');
            return null;
        }
        
        $duration = $snippet['end_time'] - $snippet['start_time'];
        
        $cmd = sprintf(
            '%s -y -ss %f -i %s -t %f -c copy %s 2>&1',
            escapeshellcmd($ffmpeg),
            $snippet['start_time'],
            escapeshellarg($sourcePath),
            $duration,
            escapeshellarg($exportPath)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0 || !file_exists($exportPath)) {
            $this->logger->error('FFmpeg export failed', ['output' => implode("\n", $output)]);
            return null;
        }
        
        // Update snippet with export path
        DB::table('media_snippets')->where('id', $snippetId)->update([
            'export_path' => '/uploads/snippets/exports/' . $exportFilename,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        
        $this->logger->info('Snippet exported', ['snippet_id' => $snippetId, 'path' => $exportPath]);
        
        return [
            'id' => $snippetId,
            'filename' => $exportFilename,
            'url' => '/uploads/snippets/exports/' . $exportFilename,
            'size' => filesize($exportPath),
        ];
    }
    
    /**
     * Generate thumbnail for snippet
     */
    public function generateSnippetThumbnail(int $snippetId): ?string
    {
        $snippet = $this->getSnippet($snippetId);
        
        if (!$snippet) {
            return null;
        }
        
        $do = DB::table('digital_object')->where('id', $snippet['digital_object_id'])->first();
        
        if (!$do) {
            return null;
        }
        
        $sourcePath = $this->uploadsDir . '/' . trim($do->path ?? '', '/') . '/' . $do->name;
        
        if (!file_exists($sourcePath)) {
            return null;
        }
        
        // Generate thumbnail at start of snippet
        $thumbnailFilename = 'thumb_snippet_' . $snippetId . '.jpg';
        $thumbnailPath = $this->snippetsDir . '/thumbnails/' . $thumbnailFilename;
        
        $ffmpeg = $this->config['ffmpeg_path'];
        $width = $this->config['thumbnail_width'];
        $height = $this->config['thumbnail_height'];
        
        $cmd = sprintf(
            '%s -y -ss %f -i %s -vframes 1 -vf "scale=%d:%d:force_original_aspect_ratio=decrease,pad=%d:%d:(ow-iw)/2:(oh-ih)/2" %s 2>&1',
            escapeshellcmd($ffmpeg),
            $snippet['start_time'],
            escapeshellarg($sourcePath),
            $width, $height, $width, $height,
            escapeshellarg($thumbnailPath)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0 || !file_exists($thumbnailPath)) {
            return null;
        }
        
        $thumbnailUrl = '/uploads/snippets/thumbnails/' . $thumbnailFilename;
        
        DB::table('media_snippets')->where('id', $snippetId)->update([
            'thumbnail_path' => $thumbnailUrl,
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        
        return $thumbnailUrl;
    }
    
    // ========================================================================
    // Batch Operations
    // ========================================================================
    
    /**
     * Get all snippets for an information object (all its digital objects)
     */
    public function getSnippetsForInformationObject(int $objectId): array
    {
        return DB::table('media_snippets as s')
            ->leftJoin('digital_object as d', 's.digital_object_id', '=', 'd.id')
            ->where('s.object_id', $objectId)
            ->select('s.*', 'd.name as filename')
            ->orderBy('s.created_at', 'desc')
            ->get()
            ->map(fn($s) => (array)$s)
            ->toArray();
    }
    
    /**
     * Export all snippets for an object as a zip file
     */
    public function exportAllSnippets(int $objectId): ?string
    {
        $snippets = $this->getSnippetsForInformationObject($objectId);
        
        if (empty($snippets)) {
            return null;
        }
        
        $zipFilename = 'snippets_' . $objectId . '_' . date('Ymd_His') . '.zip';
        $zipPath = $this->snippetsDir . '/exports/' . $zipFilename;
        
        $zip = new \ZipArchive();
        
        if ($zip->open($zipPath, \ZipArchive::CREATE) !== true) {
            return null;
        }
        
        foreach ($snippets as $snippet) {
            // Export snippet if not already
            if (empty($snippet['export_path'])) {
                $this->exportSnippet($snippet['id']);
                $snippet = $this->getSnippet($snippet['id']);
            }
            
            if (!empty($snippet['export_path'])) {
                $filePath = $this->uploadsDir . '/..' . $snippet['export_path'];
                if (file_exists($filePath)) {
                    $zip->addFile($filePath, basename($snippet['export_path']));
                }
            }
        }
        
        $zip->close();
        
        if (!file_exists($zipPath)) {
            return null;
        }
        
        return '/uploads/snippets/exports/' . $zipFilename;
    }
}
