<?php

declare(strict_types=1);

namespace AtomFramework\Extensions\IiifViewer\Services;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

/**
 * IIIF Annotation Service
 * 
 * Manages annotations using W3C Web Annotation Data Model
 * Supports Annotorious and Mirador annotation creation/storage
 * 
 * @package AtomFramework\Extensions\IiifViewer
 * @author Johan Pieterse - The Archive and Heritage Group
 * @version 1.0.0
 */
class AnnotationService
{
    private Logger $logger;
    private string $baseUrl;
    
    public const MOTIVATION_COMMENTING = 'commenting';
    public const MOTIVATION_TAGGING = 'tagging';
    public const MOTIVATION_DESCRIBING = 'describing';
    public const MOTIVATION_LINKING = 'linking';
    public const MOTIVATION_TRANSCRIBING = 'transcribing';
    public const MOTIVATION_IDENTIFYING = 'identifying';
    
    public function __construct(string $baseUrl = 'https://archives.theahg.co.za')
    {
        $this->baseUrl = $baseUrl;
        
        $this->logger = new Logger('iiif-annotation');
        $logPath = '/var/log/atom/iiif-annotation.log';
        
        if (is_writable(dirname($logPath))) {
            $this->logger->pushHandler(new RotatingFileHandler($logPath, 30, Logger::INFO));
        }
    }
    
    // ========================================================================
    // Annotation CRUD Operations
    // ========================================================================
    
    /**
     * Get all annotations for an object
     */
    public function getAnnotationsForObject(int $objectId): array
    {
        return DB::table('iiif_annotation as a')
            ->leftJoin('iiif_annotation_body as b', 'a.id', '=', 'b.annotation_id')
            ->where('a.object_id', $objectId)
            ->orderBy('a.created_at')
            ->select(
                'a.*',
                'b.body_type',
                'b.body_value',
                'b.body_format',
                'b.body_language'
            )
            ->get()
            ->toArray();
    }
    
    /**
     * Get annotations for a specific canvas
     */
    public function getAnnotationsForCanvas(string $canvasId): array
    {
        return DB::table('iiif_annotation as a')
            ->leftJoin('iiif_annotation_body as b', 'a.id', '=', 'b.annotation_id')
            ->where('a.target_canvas', $canvasId)
            ->orderBy('a.created_at')
            ->select(
                'a.*',
                'b.body_type',
                'b.body_value',
                'b.body_format',
                'b.body_language'
            )
            ->get()
            ->toArray();
    }
    
    /**
     * Get a single annotation by ID
     */
    public function getAnnotation(int $annotationId): ?object
    {
        $annotation = DB::table('iiif_annotation')
            ->where('id', $annotationId)
            ->first();
        
        if ($annotation) {
            $annotation->bodies = DB::table('iiif_annotation_body')
                ->where('annotation_id', $annotationId)
                ->get()
                ->toArray();
        }
        
        return $annotation;
    }
    
    /**
     * Create a new annotation
     */
    public function createAnnotation(array $data): int
    {
        $annotationId = DB::table('iiif_annotation')->insertGetId([
            'object_id' => $data['object_id'],
            'canvas_id' => $data['canvas_id'] ?? null,
            'target_canvas' => $data['target_canvas'],
            'target_selector' => json_encode($data['target_selector'] ?? null),
            'motivation' => $data['motivation'] ?? self::MOTIVATION_COMMENTING,
            'created_by' => $data['created_by'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        
        // Add annotation body
        if (!empty($data['body'])) {
            $this->addAnnotationBody($annotationId, $data['body']);
        }
        
        $this->logger->info('Annotation created', ['id' => $annotationId, 'object_id' => $data['object_id']]);
        
        return $annotationId;
    }
    
    /**
     * Add a body to an annotation
     */
    public function addAnnotationBody(int $annotationId, array $body): int
    {
        return DB::table('iiif_annotation_body')->insertGetId([
            'annotation_id' => $annotationId,
            'body_type' => $body['type'] ?? 'TextualBody',
            'body_value' => $body['value'] ?? '',
            'body_format' => $body['format'] ?? 'text/plain',
            'body_language' => $body['language'] ?? 'en',
            'body_purpose' => $body['purpose'] ?? null,
        ]);
    }
    
    /**
     * Update an annotation
     */
    public function updateAnnotation(int $annotationId, array $data): bool
    {
        $update = [
            'updated_at' => date('Y-m-d H:i:s'),
        ];
        
        if (isset($data['target_selector'])) {
            $update['target_selector'] = json_encode($data['target_selector']);
        }
        
        if (isset($data['motivation'])) {
            $update['motivation'] = $data['motivation'];
        }
        
        DB::table('iiif_annotation')
            ->where('id', $annotationId)
            ->update($update);
        
        // Update body if provided
        if (!empty($data['body'])) {
            DB::table('iiif_annotation_body')
                ->where('annotation_id', $annotationId)
                ->delete();
            
            $this->addAnnotationBody($annotationId, $data['body']);
        }
        
        $this->logger->info('Annotation updated', ['id' => $annotationId]);
        
        return true;
    }
    
    /**
     * Delete an annotation
     */
    public function deleteAnnotation(int $annotationId): bool
    {
        // Delete bodies first
        DB::table('iiif_annotation_body')
            ->where('annotation_id', $annotationId)
            ->delete();
        
        DB::table('iiif_annotation')
            ->where('id', $annotationId)
            ->delete();
        
        $this->logger->info('Annotation deleted', ['id' => $annotationId]);
        
        return true;
    }
    
    // ========================================================================
    // Annotation Page Generation (IIIF 3.0)
    // ========================================================================
    
    /**
     * Generate IIIF Annotation Page for a canvas
     */
    public function generateAnnotationPage(string $canvasId, int $objectId): array
    {
        $annotations = $this->getAnnotationsForCanvas($canvasId);
        
        $annotationPageId = $this->baseUrl . '/iiif/annotations/' . urlencode($canvasId);
        
        $page = [
            '@context' => 'http://iiif.io/api/presentation/3/context.json',
            'id' => $annotationPageId,
            'type' => 'AnnotationPage',
            'items' => []
        ];
        
        foreach ($annotations as $annotation) {
            $page['items'][] = $this->formatAnnotationAsIiif($annotation);
        }
        
        return $page;
    }
    
    /**
     * Generate all annotation pages for an object
     */
    public function generateAnnotationPagesForObject(int $objectId, array $canvases): array
    {
        $pages = [];
        
        foreach ($canvases as $canvas) {
            $canvasId = $canvas['id'];
            $annotations = $this->getAnnotationsForCanvas($canvasId);
            
            if (!empty($annotations)) {
                $pages[] = [
                    'id' => $this->baseUrl . '/iiif/annotations/' . urlencode($canvasId),
                    'type' => 'AnnotationPage'
                ];
            }
        }
        
        return $pages;
    }
    
    /**
     * Format a database annotation as IIIF annotation
     */
    private function formatAnnotationAsIiif(object $annotation): array
    {
        $iiifAnnotation = [
            'id' => $this->baseUrl . '/iiif/annotation/' . $annotation->id,
            'type' => 'Annotation',
            'motivation' => $annotation->motivation,
            'created' => $annotation->created_at,
            'modified' => $annotation->updated_at,
        ];
        
        // Body
        if (!empty($annotation->body_value)) {
            $iiifAnnotation['body'] = [
                'type' => $annotation->body_type ?? 'TextualBody',
                'value' => $annotation->body_value,
                'format' => $annotation->body_format ?? 'text/plain',
            ];
            
            if ($annotation->body_language) {
                $iiifAnnotation['body']['language'] = $annotation->body_language;
            }
        }
        
        // Target with selector
        $target = $annotation->target_canvas;
        $selector = json_decode($annotation->target_selector, true);
        
        if ($selector) {
            $target = [
                'type' => 'SpecificResource',
                'source' => $annotation->target_canvas,
                'selector' => $selector
            ];
        }
        
        $iiifAnnotation['target'] = $target;
        
        // Creator
        if ($annotation->created_by) {
            $iiifAnnotation['creator'] = [
                'type' => 'Person',
                'id' => $this->baseUrl . '/user/' . $annotation->created_by
            ];
        }
        
        return $iiifAnnotation;
    }
    
    // ========================================================================
    // Annotorious Format Conversion
    // ========================================================================
    
    /**
     * Convert Annotorious annotation to database format
     */
    public function parseAnnotoriousAnnotation(array $annoData, int $objectId): array
    {
        $data = [
            'object_id' => $objectId,
            'target_canvas' => $annoData['target']['source'] ?? $annoData['target'],
            'motivation' => $annoData['motivation'] ?? self::MOTIVATION_COMMENTING,
        ];
        
        // Parse selector
        if (isset($annoData['target']['selector'])) {
            $selector = $annoData['target']['selector'];
            
            // Handle different selector types
            if (is_array($selector)) {
                $data['target_selector'] = $selector;
            } elseif (is_string($selector)) {
                // SVG selector
                if (strpos($selector, '<svg') !== false) {
                    $data['target_selector'] = [
                        'type' => 'SvgSelector',
                        'value' => $selector
                    ];
                }
                // Fragment selector (xywh)
                elseif (preg_match('/xywh=(\d+),(\d+),(\d+),(\d+)/', $selector, $matches)) {
                    $data['target_selector'] = [
                        'type' => 'FragmentSelector',
                        'conformsTo' => 'http://www.w3.org/TR/media-frags/',
                        'value' => $selector
                    ];
                }
            }
        }
        
        // Parse body
        if (isset($annoData['body'])) {
            $body = $annoData['body'];
            
            if (is_array($body) && isset($body[0])) {
                $body = $body[0]; // Take first body
            }
            
            $data['body'] = [
                'type' => $body['type'] ?? 'TextualBody',
                'value' => $body['value'] ?? '',
                'format' => $body['format'] ?? 'text/plain',
                'language' => $body['language'] ?? 'en',
                'purpose' => $body['purpose'] ?? null,
            ];
        }
        
        return $data;
    }
    
    /**
     * Convert database annotation to Annotorious format
     */
    public function toAnnotoriousFormat(object $annotation): array
    {
        $annoData = [
            '@context' => 'http://www.w3.org/ns/anno.jsonld',
            'id' => '#' . $annotation->id,
            'type' => 'Annotation',
            'motivation' => $annotation->motivation,
        ];
        
        // Body
        if (!empty($annotation->body_value)) {
            $annoData['body'] = [[
                'type' => $annotation->body_type ?? 'TextualBody',
                'value' => $annotation->body_value,
                'purpose' => $annotation->motivation,
            ]];
        }
        
        // Target with selector
        $selector = json_decode($annotation->target_selector, true);
        
        if ($selector) {
            $annoData['target'] = [
                'source' => $annotation->target_canvas,
                'selector' => $selector
            ];
        } else {
            $annoData['target'] = [
                'source' => $annotation->target_canvas
            ];
        }
        
        return $annoData;
    }
    
    // ========================================================================
    // Tag Management
    // ========================================================================
    
    /**
     * Get all tags for an object
     */
    public function getTagsForObject(int $objectId): array
    {
        return DB::table('iiif_annotation as a')
            ->join('iiif_annotation_body as b', 'a.id', '=', 'b.annotation_id')
            ->where('a.object_id', $objectId)
            ->where('a.motivation', self::MOTIVATION_TAGGING)
            ->pluck('b.body_value')
            ->unique()
            ->values()
            ->toArray();
    }
    
    /**
     * Add a tag annotation
     */
    public function addTag(int $objectId, string $canvasId, string $tag, ?array $selector = null): int
    {
        return $this->createAnnotation([
            'object_id' => $objectId,
            'target_canvas' => $canvasId,
            'target_selector' => $selector,
            'motivation' => self::MOTIVATION_TAGGING,
            'body' => [
                'type' => 'TextualBody',
                'value' => $tag,
                'purpose' => 'tagging'
            ]
        ]);
    }
    
    // ========================================================================
    // Search
    // ========================================================================
    
    /**
     * Search annotations by text
     */
    public function searchAnnotations(string $query, ?int $objectId = null): array
    {
        $q = DB::table('iiif_annotation as a')
            ->join('iiif_annotation_body as b', 'a.id', '=', 'b.annotation_id')
            ->where('b.body_value', 'LIKE', '%' . $query . '%');
        
        if ($objectId) {
            $q->where('a.object_id', $objectId);
        }
        
        return $q->select('a.*', 'b.body_value')
            ->orderBy('a.created_at', 'desc')
            ->limit(100)
            ->get()
            ->toArray();
    }
    
    /**
     * Get annotation statistics for an object
     */
    public function getAnnotationStats(int $objectId): array
    {
        $total = DB::table('iiif_annotation')
            ->where('object_id', $objectId)
            ->count();
        
        $byMotivation = DB::table('iiif_annotation')
            ->where('object_id', $objectId)
            ->groupBy('motivation')
            ->select('motivation', DB::raw('COUNT(*) as count'))
            ->pluck('count', 'motivation')
            ->toArray();
        
        return [
            'total' => $total,
            'by_motivation' => $byMotivation
        ];
    }
}
