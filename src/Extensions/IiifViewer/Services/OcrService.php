<?php

declare(strict_types=1);

namespace AtomFramework\Extensions\IiifViewer\Services;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

/**
 * OCR Service
 * 
 * Manages OCR text extraction and IIIF annotation overlays
 * Supports ALTO XML, hOCR, and plain text formats
 * 
 * @package AtomFramework\Extensions\IiifViewer
 * @author Johan Pieterse - The Archive and Heritage Group
 * @version 1.0.0
 */
class OcrService
{
    private Logger $logger;
    private string $baseUrl;
    private string $uploadsDir;
    
    public function __construct(string $baseUrl = 'https://archives.theahg.co.za')
    {
        $this->baseUrl = $baseUrl;
        $this->uploadsDir = class_exists('sfConfig') 
		? \sfConfig::get('sf_upload_dir') 
		: '/usr/share/nginx/atom/uploads';
	
        $this->logger = new Logger('iiif-ocr');
        $logPath = '/var/log/atom/iiif-ocr.log';
        
        if (is_writable(dirname($logPath))) {
            $this->logger->pushHandler(new RotatingFileHandler($logPath, 30, Logger::INFO));
        }
    }
    
    // ========================================================================
    // OCR Text CRUD
    // ========================================================================
    
    /**
     * Get OCR text for a digital object
     */
    public function getOcrForDigitalObject(int $digitalObjectId): ?object
    {
        return DB::table('iiif_ocr_text')
            ->where('digital_object_id', $digitalObjectId)
            ->first();
    }
    
    /**
     * Get OCR blocks (word/line/paragraph regions) for a page
     */
    public function getOcrBlocks(int $ocrId, ?int $pageNumber = null): array
    {
        $query = DB::table('iiif_ocr_block')
            ->where('ocr_id', $ocrId);
        
        if ($pageNumber !== null) {
            $query->where('page_number', $pageNumber);
        }
        
        return $query->orderBy('page_number')
            ->orderBy('block_order')
            ->get()
            ->toArray();
    }
    
    /**
     * Store OCR text and blocks
     */
    public function storeOcr(int $digitalObjectId, int $objectId, string $fullText, string $format = 'plain'): int
    {
        // Delete existing
        $existing = DB::table('iiif_ocr_text')
            ->where('digital_object_id', $digitalObjectId)
            ->first();
        
        if ($existing) {
            DB::table('iiif_ocr_block')->where('ocr_id', $existing->id)->delete();
            DB::table('iiif_ocr_text')->where('id', $existing->id)->delete();
        }
        
        // Insert new
        $ocrId = DB::table('iiif_ocr_text')->insertGetId([
            'digital_object_id' => $digitalObjectId,
            'object_id' => $objectId,
            'full_text' => $fullText,
            'format' => $format,
            'language' => 'en',
            'confidence' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
        
        $this->logger->info('OCR stored', ['ocr_id' => $ocrId, 'object_id' => $objectId]);
        
        return $ocrId;
    }
    
    /**
     * Store OCR block (word/line with coordinates)
     */
    public function storeOcrBlock(int $ocrId, array $block): int
    {
        return DB::table('iiif_ocr_block')->insertGetId([
            'ocr_id' => $ocrId,
            'page_number' => $block['page_number'] ?? 1,
            'block_type' => $block['block_type'] ?? 'word', // word, line, paragraph, region
            'text' => $block['text'],
            'x' => $block['x'],
            'y' => $block['y'],
            'width' => $block['width'],
            'height' => $block['height'],
            'confidence' => $block['confidence'] ?? null,
            'block_order' => $block['block_order'] ?? 0,
        ]);
    }
    
    // ========================================================================
    // OCR Import (ALTO, hOCR)
    // ========================================================================
    
    /**
     * Import ALTO XML OCR
     */
    public function importAlto(int $digitalObjectId, int $objectId, string $altoXml): int
    {
        $dom = new \DOMDocument();
        @$dom->loadXML($altoXml);
        
        $fullText = '';
        $blocks = [];
        $blockOrder = 0;
        
        // Parse ALTO structure
        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('alto', 'http://www.loc.gov/standards/alto/ns-v3#');
        
        // Try different ALTO namespaces
        $namespaces = [
            'http://www.loc.gov/standards/alto/ns-v3#',
            'http://www.loc.gov/standards/alto/ns-v2#',
            'http://schema.ccs-gmbh.com/ALTO'
        ];
        
        $strings = null;
        foreach ($namespaces as $ns) {
            $xpath->registerNamespace('alto', $ns);
            $strings = $xpath->query('//alto:String');
            if ($strings->length > 0) break;
        }
        
        // Fallback to no namespace
        if (!$strings || $strings->length === 0) {
            $strings = $xpath->query('//String');
        }
        
        if ($strings) {
            foreach ($strings as $string) {
                $text = $string->getAttribute('CONTENT');
                $x = (int)$string->getAttribute('HPOS');
                $y = (int)$string->getAttribute('VPOS');
                $w = (int)$string->getAttribute('WIDTH');
                $h = (int)$string->getAttribute('HEIGHT');
                $conf = $string->getAttribute('WC') ?: null;
                
                $fullText .= $text . ' ';
                
                $blocks[] = [
                    'page_number' => 1,
                    'block_type' => 'word',
                    'text' => $text,
                    'x' => $x,
                    'y' => $y,
                    'width' => $w,
                    'height' => $h,
                    'confidence' => $conf ? (float)$conf * 100 : null,
                    'block_order' => $blockOrder++,
                ];
            }
        }
        
        // Store OCR
        $ocrId = $this->storeOcr($digitalObjectId, $objectId, trim($fullText), 'alto');
        
        // Store blocks
        foreach ($blocks as $block) {
            $this->storeOcrBlock($ocrId, $block);
        }
        
        $this->logger->info('ALTO imported', ['ocr_id' => $ocrId, 'blocks' => count($blocks)]);
        
        return $ocrId;
    }
    
    /**
     * Import hOCR format
     */
    public function importHocr(int $digitalObjectId, int $objectId, string $hocrHtml): int
    {
        $dom = new \DOMDocument();
        @$dom->loadHTML($hocrHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $fullText = '';
        $blocks = [];
        $blockOrder = 0;
        
        $xpath = new \DOMXPath($dom);
        
        // Find all words (ocrx_word class)
        $words = $xpath->query("//*[contains(@class, 'ocrx_word')]");
        
        foreach ($words as $word) {
            $text = $word->textContent;
            $title = $word->getAttribute('title');
            
            // Parse bbox from title attribute
            if (preg_match('/bbox\s+(\d+)\s+(\d+)\s+(\d+)\s+(\d+)/', $title, $matches)) {
                $x1 = (int)$matches[1];
                $y1 = (int)$matches[2];
                $x2 = (int)$matches[3];
                $y2 = (int)$matches[4];
                
                $fullText .= $text . ' ';
                
                // Parse confidence if present
                $conf = null;
                if (preg_match('/x_wconf\s+(\d+)/', $title, $confMatch)) {
                    $conf = (int)$confMatch[1];
                }
                
                $blocks[] = [
                    'page_number' => 1,
                    'block_type' => 'word',
                    'text' => $text,
                    'x' => $x1,
                    'y' => $y1,
                    'width' => $x2 - $x1,
                    'height' => $y2 - $y1,
                    'confidence' => $conf,
                    'block_order' => $blockOrder++,
                ];
            }
        }
        
        // Store OCR
        $ocrId = $this->storeOcr($digitalObjectId, $objectId, trim($fullText), 'hocr');
        
        // Store blocks
        foreach ($blocks as $block) {
            $this->storeOcrBlock($ocrId, $block);
        }
        
        $this->logger->info('hOCR imported', ['ocr_id' => $ocrId, 'blocks' => count($blocks)]);
        
        return $ocrId;
    }
    
    /**
     * Run Tesseract OCR on an image
     */
    public function runTesseract(string $imagePath, int $digitalObjectId, int $objectId, string $language = 'eng'): ?int
    {
        if (!file_exists($imagePath)) {
            $this->logger->error('Image not found for OCR', ['path' => $imagePath]);
            return null;
        }
        
        // Check if tesseract is available
        $tesseractPath = trim(shell_exec('which tesseract 2>/dev/null'));
        if (empty($tesseractPath)) {
            $this->logger->error('Tesseract not installed');
            return null;
        }
        
        // Create temp file for output
        $tempBase = tempnam(sys_get_temp_dir(), 'ocr_');
        $hocrPath = $tempBase . '.hocr';
        
        // Run tesseract with hOCR output
        $cmd = sprintf(
            '%s %s %s -l %s hocr 2>&1',
            escapeshellcmd($tesseractPath),
            escapeshellarg($imagePath),
            escapeshellarg($tempBase),
            escapeshellarg($language)
        );
        
        exec($cmd, $output, $returnCode);
        
        if ($returnCode !== 0 || !file_exists($hocrPath)) {
            $this->logger->error('Tesseract failed', ['output' => implode("\n", $output)]);
            @unlink($tempBase);
            return null;
        }
        
        // Import hOCR
        $hocrContent = file_get_contents($hocrPath);
        $ocrId = $this->importHocr($digitalObjectId, $objectId, $hocrContent);
        
        // Cleanup
        @unlink($tempBase);
        @unlink($hocrPath);
        
        return $ocrId;
    }
    
    // ========================================================================
    // IIIF Annotation Generation
    // ========================================================================
    
    /**
     * Generate IIIF annotation page from OCR
     */
    public function generateOcrAnnotationPage(int $digitalObjectId, string $canvasId, array $canvasDimensions): array
    {
        $ocr = $this->getOcrForDigitalObject($digitalObjectId);
        
        if (!$ocr) {
            return [
                '@context' => 'http://iiif.io/api/presentation/3/context.json',
                'id' => $this->baseUrl . '/iiif/ocr/' . $digitalObjectId,
                'type' => 'AnnotationPage',
                'items' => []
            ];
        }
        
        $blocks = $this->getOcrBlocks($ocr->id);
        
        $annotations = [];
        
        foreach ($blocks as $block) {
            // Create fragment selector (xywh)
            $x = $block->x;
            $y = $block->y;
            $w = $block->width;
            $h = $block->height;
            
            $annotations[] = [
                'id' => $this->baseUrl . '/iiif/ocr/annotation/' . $block->id,
                'type' => 'Annotation',
                'motivation' => 'supplementing',
                'body' => [
                    'type' => 'TextualBody',
                    'value' => $block->text,
                    'format' => 'text/plain',
                    'language' => $ocr->language ?? 'en'
                ],
                'target' => [
                    'source' => $canvasId,
                    'selector' => [
                        'type' => 'FragmentSelector',
                        'conformsTo' => 'http://www.w3.org/TR/media-frags/',
                        'value' => "xywh={$x},{$y},{$w},{$h}"
                    ]
                ]
            ];
        }
        
        return [
            '@context' => 'http://iiif.io/api/presentation/3/context.json',
            'id' => $this->baseUrl . '/iiif/ocr/' . $digitalObjectId,
            'type' => 'AnnotationPage',
            'items' => $annotations
        ];
    }
    
    /**
     * Generate plain text content annotation
     */
    public function generateTextAnnotation(int $digitalObjectId, string $canvasId): ?array
    {
        $ocr = $this->getOcrForDigitalObject($digitalObjectId);
        
        if (!$ocr || empty($ocr->full_text)) {
            return null;
        }
        
        return [
            'id' => $this->baseUrl . '/iiif/text/' . $digitalObjectId,
            'type' => 'Annotation',
            'motivation' => 'supplementing',
            'body' => [
                'type' => 'TextualBody',
                'value' => $ocr->full_text,
                'format' => 'text/plain',
                'language' => $ocr->language ?? 'en'
            ],
            'target' => $canvasId
        ];
    }
    
    // ========================================================================
    // Search
    // ========================================================================
    
    /**
     * Search OCR text
     */
    public function searchOcr(string $query, ?int $objectId = null): array
    {
        $q = DB::table('iiif_ocr_text as o')
            ->leftJoin('information_object_i18n as ioi', 'o.object_id', '=', 'ioi.id')
            ->leftJoin('slug', 'o.object_id', '=', 'slug.object_id')
            ->where('o.full_text', 'LIKE', '%' . $query . '%');
        
        if ($objectId) {
            $q->where('o.object_id', $objectId);
        }
        
        return $q->select(
                'o.*',
                'ioi.title as object_title',
                'slug.slug'
            )
            ->orderBy('o.created_at', 'desc')
            ->limit(100)
            ->get()
            ->toArray();
    }
    
    /**
     * Search OCR blocks (word-level search with coordinates)
     */
    public function searchOcrBlocks(string $query, int $ocrId): array
    {
        return DB::table('iiif_ocr_block')
            ->where('ocr_id', $ocrId)
            ->where('text', 'LIKE', '%' . $query . '%')
            ->orderBy('page_number')
            ->orderBy('block_order')
            ->get()
            ->toArray();
    }
    
    /**
     * Generate IIIF Content Search response
     */
    public function generateSearchResponse(string $query, int $objectId, array $canvasMap): array
    {
        $ocr = DB::table('iiif_ocr_text')
            ->where('object_id', $objectId)
            ->first();
        
        $response = [
            '@context' => 'http://iiif.io/api/search/1/context.json',
            '@id' => $this->baseUrl . '/iiif/search/' . $objectId . '?q=' . urlencode($query),
            '@type' => 'sc:AnnotationList',
            'resources' => [],
            'hits' => []
        ];
        
        if (!$ocr) {
            return $response;
        }
        
        $blocks = $this->searchOcrBlocks($query, $ocr->id);
        
        foreach ($blocks as $block) {
            $canvasId = $canvasMap[$block->page_number] ?? null;
            
            if (!$canvasId) continue;
            
            $annoId = $this->baseUrl . '/iiif/search/annotation/' . $block->id;
            
            $response['resources'][] = [
                '@id' => $annoId,
                '@type' => 'oa:Annotation',
                'motivation' => 'sc:painting',
                'resource' => [
                    '@type' => 'cnt:ContentAsText',
                    'chars' => $block->text
                ],
                'on' => $canvasId . '#xywh=' . $block->x . ',' . $block->y . ',' . $block->width . ',' . $block->height
            ];
            
            $response['hits'][] = [
                '@type' => 'search:Hit',
                'annotations' => [$annoId],
                'match' => $block->text
            ];
        }
        
        return $response;
    }
}
