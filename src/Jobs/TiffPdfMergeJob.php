<?php

namespace AtomFramework\Jobs;

use Illuminate\Database\Capsule\Manager as DB;

class TiffPdfMergeJob
{
    protected $mergeJobId;
    protected $jobTable = 'tiff_pdf_merge_job';
    protected $fileTable = 'tiff_pdf_merge_file';

    public function __construct(int $mergeJobId)
    {
        $this->mergeJobId = $mergeJobId;
    }

    public function handle(): bool
    {
        $this->log('Starting TIFF to PDF merge...');

        $mergeJob = DB::table($this->jobTable)->where('id', $this->mergeJobId)->first();

        if (!$mergeJob) {
            $this->log('Merge job not found: ' . $this->mergeJobId, 'error');
            return false;
        }

        DB::table($this->jobTable)->where('id', $this->mergeJobId)->update([
            'status' => 'processing',
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        try {
            $files = DB::table($this->fileTable)
                ->where('merge_job_id', $this->mergeJobId)
                ->orderBy('page_order')
                ->get();

            if ($files->isEmpty()) {
                throw new \Exception('No files to process');
            }

            $this->log(sprintf('Processing %d files...', $files->count()));

            $tempDir = '/tmp/tiff-pdf-merge';
            $jobDir = $tempDir . '/job_' . $this->mergeJobId;

            if (!is_dir($jobDir)) {
                mkdir($jobDir, 0755, true);
            }

            $outputFilename = $this->sanitizeFilename($mergeJob->job_name) . '.pdf';
            $outputPath = $jobDir . '/' . $outputFilename;

            $this->log('Converting images to PDF...');

            if (strpos($mergeJob->pdf_standard ?? 'pdfa-2b', 'pdfa') === 0) {
                $result = $this->convertToPdfA($files, $outputPath, $mergeJob);
            } else {
                $result = $this->convertToPdf($files, $outputPath, $mergeJob);
            }

            if (!$result['success']) {
                throw new \Exception($result['error']);
            }

            DB::table($this->fileTable)
                ->where('merge_job_id', $this->mergeJobId)
                ->update(['status' => 'processed']);

            DB::table($this->jobTable)->where('id', $this->mergeJobId)->update([
                'output_filename' => $outputFilename,
                'output_path' => $outputPath,
                'processed_files' => $files->count(),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $this->log('PDF created: ' . $outputFilename);

            $digitalObjectId = null;

            if ($mergeJob->attach_to_record && $mergeJob->information_object_id) {
                $this->log('Attaching PDF to record...');
                $digitalObjectId = $this->attachToRecord(
                    $mergeJob->information_object_id,
                    $outputPath,
                    $outputFilename
                );

                if ($digitalObjectId) {
                    DB::table($this->jobTable)->where('id', $this->mergeJobId)->update([
                        'output_digital_object_id' => $digitalObjectId,
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                    $this->log('Attached as digital object ID: ' . $digitalObjectId);
                }
            }

            DB::table($this->jobTable)->where('id', $this->mergeJobId)->update([
                'status' => 'completed',
                'completed_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            $this->log(sprintf('Merge complete! Created %d-page PDF%s',
                $files->count(), $digitalObjectId ? ' and attached to record' : ''));

            return true;

        } catch (\Exception $e) {
            DB::table($this->jobTable)->where('id', $this->mergeJobId)->update([
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $this->log('Merge failed: ' . $e->getMessage(), 'error');
            return false;
        }
    }

    protected function convertToPdf($files, $outputPath, $job): array
    {
        $convert = $this->getSetting('imagemagick_path', '/usr/bin/convert');
        $inputFiles = [];

        foreach ($files as $file) {
            if (file_exists($file->file_path)) {
                $inputFiles[] = escapeshellarg($file->file_path);
            }
        }

        if (empty($inputFiles)) {
            return ['success' => false, 'error' => 'No valid input files'];
        }

        $quality = (int) ($job->compression_quality ?? 85);
        $dpi = (int) ($job->dpi ?? 300);

        $cmd = sprintf('%s -quality %d -density %d %s %s 2>&1',
            escapeshellcmd($convert), $quality, $dpi,
            implode(' ', $inputFiles), escapeshellarg($outputPath));

        $this->log('Running ImageMagick...');
        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            return ['success' => false, 'error' => 'ImageMagick failed: ' . implode("\n", $output)];
        }

        return ['success' => true];
    }

    protected function convertToPdfA($files, $outputPath, $job): array
    {
        $convert = $this->getSetting('imagemagick_path', '/usr/bin/convert');
        $gs = $this->getSetting('ghostscript_path', '/usr/bin/gs');
        $tempDir = '/tmp/tiff-pdf-merge';

        if (!is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $tempPdf = $tempDir . '/temp_' . uniqid() . '.pdf';
        $inputFiles = [];

        foreach ($files as $file) {
            if (file_exists($file->file_path)) {
                $inputFiles[] = escapeshellarg($file->file_path);
            }
        }

        if (empty($inputFiles)) {
            return ['success' => false, 'error' => 'No valid input files'];
        }

        $quality = (int) ($job->compression_quality ?? 85);
        $dpi = (int) ($job->dpi ?? 300);

        // Step 1: Create PDF with ImageMagick
        $imCmd = sprintf('%s -quality %d -density %d %s %s 2>&1',
            escapeshellcmd($convert), $quality, $dpi,
            implode(' ', $inputFiles), escapeshellarg($tempPdf));

        $this->log('Creating PDF with ImageMagick...');
        exec($imCmd, $imOutput, $imReturn);

        if ($imReturn !== 0) {
            return ['success' => false, 'error' => 'ImageMagick failed: ' . implode("\n", $imOutput)];
        }

        // Determine PDF/A level
        $pdfStandard = $job->pdf_standard ?? 'pdfa-2b';
        $pdfaLevel = '2';
        if ($pdfStandard === 'pdfa-1b') {
            $pdfaLevel = '1';
        } elseif ($pdfStandard === 'pdfa-3b') {
            $pdfaLevel = '3';
        }

        // Step 2: Convert to PDF/A with Ghostscript
        $gsCmd = sprintf('%s -dPDFA=%s -dBATCH -dNOPAUSE -dNOOUTERSAVE ' .
            '-sProcessColorModel=DeviceRGB -sDEVICE=pdfwrite ' .
            '-dPDFACompatibilityPolicy=1 -sOutputFile=%s %s 2>&1',
            escapeshellcmd($gs), $pdfaLevel,
            escapeshellarg($outputPath), escapeshellarg($tempPdf));

        $this->log('Converting to PDF/A-' . $pdfaLevel . 'b...');
        exec($gsCmd, $gsOutput, $gsReturn);

        @unlink($tempPdf);

        if ($gsReturn !== 0) {
            $this->log('PDF/A failed, falling back to standard PDF...');
            return $this->convertToPdf($files, $outputPath, $job);
        }

        return ['success' => true];
    }

    protected function attachToRecord($informationObjectId, $filePath, $filename): ?int
    {
        // Get information object
        $io = DB::table('information_object')->where('id', $informationObjectId)->first();

        if (!$io) {
            $this->log('Warning: Information object not found');
            return null;
        }

        $slug = DB::table('slug')
            ->where('object_id', $informationObjectId)
            ->first();

        $slugValue = $slug ? $slug->slug : sprintf('%010d', $informationObjectId);

        $uploadDir = '/usr/share/nginx/archive/uploads/r/' . $slugValue;

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $destPath = $uploadDir . '/' . $filename;

        if (!copy($filePath, $destPath)) {
            $this->log('Warning: Failed to copy PDF to uploads');
            return null;
        }

        // Create digital object record
        $digitalObjectId = DB::table('digital_object')->insertGetId([
            'information_object_id' => $informationObjectId,
            'usage_id' => 142, // QubitTerm::MASTER_ID
            'mime_type' => 'application/pdf',
            'byte_size' => filesize($destPath),
            'checksum' => md5_file($destPath),
            'checksum_type' => 'md5',
            'name' => $filename,
            'path' => 'r/' . $slugValue . '/' . $filename,
            'sequence' => 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);

        // Generate derivatives
        $this->generateDerivatives($digitalObjectId, $destPath, $slugValue);

        return $digitalObjectId;
    }

    protected function generateDerivatives($digitalObjectId, $masterPath, $slugValue): void
    {
        $convert = $this->getSetting('imagemagick_path', '/usr/bin/convert');
        $uploadDir = dirname($masterPath);
        $baseName = pathinfo(basename($masterPath), PATHINFO_FILENAME);

        // Thumbnail
        $thumbFilename = $baseName . '_thumb.jpg';
        $thumbPath = $uploadDir . '/' . $thumbFilename;

        exec(sprintf('%s -density 72 %s[0] -thumbnail 100x100 -flatten -quality 75 %s 2>&1',
            escapeshellcmd($convert), escapeshellarg($masterPath), escapeshellarg($thumbPath)),
            $thumbOutput, $thumbReturn);

        if ($thumbReturn === 0 && file_exists($thumbPath)) {
            DB::table('digital_object')->insert([
                'parent_id' => $digitalObjectId,
                'usage_id' => 137, // QubitTerm::THUMBNAIL_ID
                'mime_type' => 'image/jpeg',
                'byte_size' => filesize($thumbPath),
                'name' => $thumbFilename,
                'path' => 'r/' . $slugValue . '/' . $thumbFilename,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // Reference
        $refFilename = $baseName . '_ref.jpg';
        $refPath = $uploadDir . '/' . $refFilename;

        exec(sprintf('%s -density 150 %s[0] -resize 480x480 -flatten -quality 80 %s 2>&1',
            escapeshellcmd($convert), escapeshellarg($masterPath), escapeshellarg($refPath)),
            $refOutput, $refReturn);

        if ($refReturn === 0 && file_exists($refPath)) {
            DB::table('digital_object')->insert([
                'parent_id' => $digitalObjectId,
                'usage_id' => 141, // QubitTerm::REFERENCE_ID
                'mime_type' => 'image/jpeg',
                'byte_size' => filesize($refPath),
                'name' => $refFilename,
                'path' => 'r/' . $slugValue . '/' . $refFilename,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);
        }
    }

    protected function getSetting(string $key, $default = null)
    {
        try {
            $setting = DB::table('tiff_pdf_settings')
                ->where('setting_key', $key)
                ->first();

            if ($setting && !empty($setting->setting_value)) {
                return $setting->setting_value;
            }
        } catch (\Exception $e) {
        }

        return $default;
    }

    protected function sanitizeFilename($filename): string
    {
        $filename = basename($filename);
        $filename = preg_replace('/[^a-zA-Z0-9_.-]/', '_', $filename);
        $filename = preg_replace('/_+/', '_', $filename);
        $filename = trim($filename, '_');
        return !empty($filename) ? $filename : 'merged_document';
    }

    protected function log(string $message, string $level = 'info'): void
    {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] [TiffPdfMerge:{$this->mergeJobId}] {$message}";
        error_log($logMessage);

        // Also update job notes
        DB::table($this->jobTable)->where('id', $this->mergeJobId)->update([
            'notes' => DB::raw("CONCAT(IFNULL(notes, ''), '\n', " . DB::connection()->getPdo()->quote($message) . ")"),
        ]);
    }
}
