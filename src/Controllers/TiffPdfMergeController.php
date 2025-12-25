<?php

declare(strict_types=1);

namespace AtomFramework\Controllers;

use AtomFramework\Services\TiffPdfMergeService;
use AtomFramework\Repositories\TiffPdfMergeRepository;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Exception;

/**
 * Controller for TIFF to PDF merge operations
 */
class TiffPdfMergeController
{
    protected TiffPdfMergeService $service;
    protected TiffPdfMergeRepository $repository;

    public function __construct()
    {
        $this->service = new TiffPdfMergeService();
        $this->repository = new TiffPdfMergeRepository();
    }

    /**
     * Get current settings and statistics
     */
    public function index(): JsonResponse
    {
        try {
            $userId = sfContext::getInstance()->getUser()->getAttribute('user_id');

            return new JsonResponse([
                'success' => true,
                'settings' => $this->repository->getSettings(),
                'statistics' => $this->repository->getStatistics($userId),
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List jobs with optional filters
     */
    public function listJobs(Request $request): JsonResponse
    {
        try {
            $filters = [
                'status' => $request->get('status'),
                'user_id' => $request->get('user_id'),
                'information_object_id' => $request->get('information_object_id'),
                'date_from' => $request->get('date_from'),
                'date_to' => $request->get('date_to'),
            ];

            $limit = (int) ($request->get('limit', 50));
            $offset = (int) ($request->get('offset', 0));

            $jobs = $this->repository->getJobs(array_filter($filters), $limit, $offset);

            return new JsonResponse([
                'success' => true,
                'jobs' => $jobs->toArray(),
                'total' => $jobs->count(),
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Create a new merge job
     */
    public function createJob(Request $request): JsonResponse
    {
        try {
            $userId = sfContext::getInstance()->getUser()->getAttribute('user_id');

            if (!$userId) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Authentication required',
                ], 401);
            }

            $jobName = $request->get('job_name', 'Merged PDF ' . date('Y-m-d H:i:s'));
            $informationObjectId = $request->get('information_object_id');

            $options = [
                'pdf_standard' => $request->get('pdf_standard', 'pdfa-2b'),
                'compression_quality' => (int) $request->get('compression_quality', 85),
                'page_size' => $request->get('page_size', 'auto'),
                'orientation' => $request->get('orientation', 'auto'),
                'dpi' => (int) $request->get('dpi', 300),
                'preserve_originals' => (bool) $request->get('preserve_originals', true),
                'attach_to_record' => (bool) $request->get('attach_to_record', true),
            ];

            $jobId = $this->service->createJob(
                $userId,
                $jobName,
                $informationObjectId ? (int) $informationObjectId : null,
                $options
            );

            return new JsonResponse([
                'success' => true,
                'job_id' => $jobId,
                'message' => 'Job created successfully',
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get job details
     */
    public function getJob(int $jobId): JsonResponse
    {
        try {
            $job = $this->repository->getJob($jobId);

            if (!$job) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Job not found',
                ], 404);
            }

            $files = $this->repository->getJobFiles($jobId);

            return new JsonResponse([
                'success' => true,
                'job' => $job,
                'files' => $files->toArray(),
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Upload files to a job
     */
    public function uploadFiles(Request $request, int $jobId): JsonResponse
    {
        try {
            $results = [];
            $files = $request->files->get('files', []);

            if (empty($files)) {
                // Check for single file upload
                $singleFile = $request->files->get('file');

                if ($singleFile) {
                    $files = [$singleFile];
                }
            }

            if (empty($files)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'No files uploaded',
                ], 400);
            }

            foreach ($files as $file) {
                $uploadedFile = [
                    'name' => $file->getClientOriginalName(),
                    'type' => $file->getMimeType(),
                    'tmp_name' => $file->getPathname(),
                    'error' => $file->getError(),
                    'size' => $file->getSize(),
                ];

                $result = $this->service->uploadFile($jobId, $uploadedFile);
                $results[] = $result;
            }

            $successful = array_filter($results, fn ($r) => $r['success']);
            $failed = array_filter($results, fn ($r) => !$r['success']);

            return new JsonResponse([
                'success' => empty($failed),
                'uploaded' => count($successful),
                'failed' => count($failed),
                'results' => $results,
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reorder files in a job
     */
    public function reorderFiles(Request $request, int $jobId): JsonResponse
    {
        try {
            $fileOrder = $request->get('file_order', []);

            if (empty($fileOrder)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'No file order provided',
                ], 400);
            }

            $this->repository->updateFileOrder($jobId, $fileOrder);

            return new JsonResponse([
                'success' => true,
                'message' => 'File order updated',
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove a file from a job
     */
    public function removeFile(int $jobId, int $fileId): JsonResponse
    {
        try {
            $file = $this->repository->getJobFiles($jobId)
                ->firstWhere('id', $fileId);

            if (!$file) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'File not found',
                ], 404);
            }

            // Delete physical file
            if (file_exists($file->file_path)) {
                @unlink($file->file_path);
            }

            // Delete record
            $this->repository->deleteFile($fileId);

            return new JsonResponse([
                'success' => true,
                'message' => 'File removed',
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Process a job (merge files into PDF)
     */
    public function processJob(int $jobId): JsonResponse
    {
        try {
            $result = $this->service->processJob($jobId);

            if ($result['success']) {
                return new JsonResponse([
                    'success' => true,
                    'message' => 'PDF created successfully',
                    'output_filename' => $result['output_filename'],
                    'pages' => $result['pages'],
                    'digital_object_id' => $result['digital_object_id'] ?? null,
                ]);
            } else {
                return new JsonResponse([
                    'success' => false,
                    'error' => $result['error'],
                ], 500);
            }
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Download output PDF
     */
    public function downloadPdf(int $jobId): Response
    {
        try {
            $outputPath = $this->service->getOutputPath($jobId);

            if (!$outputPath) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Output file not found',
                ], 404);
            }

            $job = $this->repository->getJob($jobId);
            $filename = $job->output_filename ?? 'merged.pdf';

            return new BinaryFileResponse($outputPath, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a job
     */
    public function deleteJob(int $jobId): JsonResponse
    {
        try {
            // Cleanup files first
            $this->service->cleanupJob($jobId);

            // Delete job record
            $this->repository->deleteJob($jobId);

            return new JsonResponse([
                'success' => true,
                'message' => 'Job deleted',
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update settings
     */
    public function updateSettings(Request $request): JsonResponse
    {
        try {
            // Check admin permission
            if (!sfContext::getInstance()->getUser()->hasCredential('administrator')) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Administrator access required',
                ], 403);
            }

            $settings = $request->get('settings', []);

            foreach ($settings as $key => $value) {
                $this->repository->updateSetting($key, $value);
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Settings updated',
                'settings' => $this->repository->getSettings(),
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
