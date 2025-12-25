<?php

declare(strict_types=1);

namespace AtomFramework\Extensions\Spectrum\Services;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

/**
 * Label Generation Service
 * 
 * Generates object labels, storage labels, exhibition labels, QR codes, and barcodes
 * 
 * @package AtomFramework\Extensions\Spectrum
 * @author Johan Pieterse - The Archive and Heritage Group
 * @version 1.0.0
 */
class LabelService
{
    private Logger $logger;
    private string $outputPath;
    private array $templates;
    
    public function __construct()
    {
        $this->logger = new Logger('spectrum-labels');
        $logPath = '/var/log/atom/spectrum-labels.log';
        
        if (is_writable(dirname($logPath))) {
            $this->logger->pushHandler(new RotatingFileHandler($logPath, 30, Logger::INFO));
        }
        
        $this->outputPath = '/var/www/html/uploads/labels/';
        $this->loadTemplates();
    }
    
    /**
     * Load available label templates
     */
    private function loadTemplates(): void
    {
        $this->templates = [
            'object_standard' => [
                'name' => 'Standard Object Label',
                'width' => 100, // mm
                'height' => 70,
                'fields' => ['title', 'identifier', 'date', 'creator', 'repository']
            ],
            'object_detailed' => [
                'name' => 'Detailed Object Label',
                'width' => 150,
                'height' => 100,
                'fields' => ['title', 'identifier', 'date', 'creator', 'repository', 'description', 'materials', 'dimensions']
            ],
            'storage' => [
                'name' => 'Storage Label',
                'width' => 50,
                'height' => 25,
                'fields' => ['identifier', 'location', 'barcode']
            ],
            'exhibition' => [
                'name' => 'Exhibition Label',
                'width' => 200,
                'height' => 150,
                'fields' => ['title', 'creator', 'date', 'materials', 'dimensions', 'credit_line', 'accession_number']
            ],
            'loan' => [
                'name' => 'Loan Label',
                'width' => 100,
                'height' => 50,
                'fields' => ['identifier', 'loan_number', 'borrower', 'return_date']
            ],
            'qr' => [
                'name' => 'QR Code Label',
                'width' => 50,
                'height' => 60,
                'fields' => ['identifier', 'qr_code']
            ],
            'barcode' => [
                'name' => 'Barcode Label',
                'width' => 60,
                'height' => 25,
                'fields' => ['identifier', 'barcode']
            ]
        ];
    }
    
    /**
     * Generate a label for an object
     * 
     * @param int $objectId Information object ID
     * @param string $labelType Type of label (object, storage, exhibition, loan, qr, barcode)
     * @param string $template Template name
     * @param array $options Additional options
     * @return array Result with file path and label data
     */
    public function generateLabel(int $objectId, string $labelType = 'object', string $template = 'standard', array $options = []): array
    {
        $this->logger->info("Generating {$labelType} label for object {$objectId}");
        
        // Get object data
        $objectData = $this->getObjectData($objectId);
        
        if (!$objectData) {
            throw new \RuntimeException("Object not found: {$objectId}");
        }
        
        // Get template
        $templateKey = $labelType === 'object' ? "object_{$template}" : $labelType;
        $templateConfig = $this->templates[$templateKey] ?? $this->templates['object_standard'];
        
        // Prepare label data
        $labelData = $this->prepareLabelData($objectData, $templateConfig['fields'], $options);
        
        // Generate the label
        $result = $this->renderLabel($labelData, $templateConfig, $labelType, $options);
        
        // Save to database
        $this->saveLabel($objectId, $labelType, $template, $labelData, $result['file_path'] ?? null);
        
        $this->logger->info("Label generated successfully", ['file' => $result['file_path'] ?? 'html_only']);
        
        return $result;
    }
    
    /**
     * Get object data from database
     */
    private function getObjectData(int $objectId): ?array
    {
        $sql = "
            SELECT 
                io.id,
                io.identifier,
                io.source_culture,
                io.level_of_description_id,
                s.slug,
                i18n.title,
                i18n.scope_and_content as description,
                i18n.extent_and_medium as dimensions,
                i18n.physical_characteristics as materials,
                ed.date as dates,
                ed.actor_id as creator_id,
                actor_i18n.authorized_form_of_name as creator_name,
                repo_i18n.authorized_form_of_name as repository_name,
                prop.physical_location as location
            FROM information_object io
            LEFT JOIN information_object_i18n i18n ON io.id = i18n.id AND i18n.culture = io.source_culture
            LEFT JOIN slug s ON io.id = s.object_id
            LEFT JOIN event ed ON io.id = ed.object_id AND ed.type_id = (SELECT id FROM term WHERE taxonomy_id = 42 AND id IN (SELECT id FROM term_i18n WHERE name = 'Creation'))
            LEFT JOIN actor_i18n ON ed.actor_id = actor_i18n.id AND actor_i18n.culture = io.source_culture
            LEFT JOIN repository ON io.repository_id = repository.id
            LEFT JOIN actor_i18n repo_i18n ON repository.id = repo_i18n.id AND repo_i18n.culture = io.source_culture
            LEFT JOIN property prop ON io.id = prop.object_id AND prop.name = 'physicalLocation'
            WHERE io.id = :object_id
        ";
        
        $result = DB::select($sql, ['object_id' => $objectId]);
        
        return $result[0] ?? null;
    }
    
    /**
     * Prepare label data based on template fields
     */
    private function prepareLabelData(object $object, array $fields, array $options): array
    {
        $data = [];
        
        foreach ($fields as $field) {
            switch ($field) {
                case 'title':
                    $data['title'] = $object->title ?? 'Untitled';
                    break;
                case 'identifier':
                    $data['identifier'] = $object->identifier ?? '';
                    break;
                case 'date':
                    $data['date'] = $object->dates ?? '';
                    break;
                case 'creator':
                    $data['creator'] = $object->creator_name ?? '';
                    break;
                case 'repository':
                    $data['repository'] = $object->repository_name ?? '';
                    break;
                case 'description':
                    $desc = $object->description ?? '';
                    $data['description'] = strlen($desc) > 200 ? substr($desc, 0, 197) . '...' : $desc;
                    break;
                case 'materials':
                    $data['materials'] = $object->materials ?? '';
                    break;
                case 'dimensions':
                    $data['dimensions'] = $object->dimensions ?? '';
                    break;
                case 'location':
                    $data['location'] = $object->location ?? $options['location'] ?? '';
                    break;
                case 'credit_line':
                    $data['credit_line'] = $options['credit_line'] ?? $object->repository_name ?? '';
                    break;
                case 'accession_number':
                    $data['accession_number'] = $object->identifier ?? '';
                    break;
                case 'loan_number':
                    $data['loan_number'] = $options['loan_number'] ?? '';
                    break;
                case 'borrower':
                    $data['borrower'] = $options['borrower'] ?? '';
                    break;
                case 'return_date':
                    $data['return_date'] = $options['return_date'] ?? '';
                    break;
                case 'barcode':
                    $data['barcode'] = $this->generateBarcode($object->identifier ?? $object->id);
                    break;
                case 'qr_code':
                    $data['qr_code'] = $this->generateQrCode($object->slug ?? $object->id);
                    break;
            }
        }
        
        return $data;
    }
    
    /**
     * Render the label as HTML/PDF
     */
    private function renderLabel(array $data, array $template, string $labelType, array $options): array
    {
        $format = $options['format'] ?? 'html';
        
        // Generate HTML
        $html = $this->generateLabelHtml($data, $template, $labelType);
        
        if ($format === 'pdf') {
            // Generate PDF (requires TCPDF or similar)
            $filePath = $this->generatePdf($html, $data['identifier'] ?? 'label', $template);
            return ['html' => $html, 'file_path' => $filePath, 'format' => 'pdf'];
        }
        
        return ['html' => $html, 'format' => 'html', 'data' => $data];
    }
    
    /**
     * Generate label HTML
     */
    private function generateLabelHtml(array $data, array $template, string $labelType): string
    {
        $width = $template['width'];
        $height = $template['height'];
        
        $html = "<!DOCTYPE html><html><head><meta charset='utf-8'>";
        $html .= "<style>
            .label { 
                width: {$width}mm; 
                height: {$height}mm; 
                border: 1px solid #333; 
                padding: 5mm;
                font-family: Arial, sans-serif;
                box-sizing: border-box;
                page-break-after: always;
            }
            .label-title { font-size: 14pt; font-weight: bold; margin-bottom: 3mm; }
            .label-field { font-size: 10pt; margin-bottom: 2mm; }
            .label-field-label { font-weight: bold; }
            .label-identifier { font-size: 12pt; font-weight: bold; color: #333; }
            .barcode { font-family: 'Libre Barcode 39', monospace; font-size: 36pt; }
            .qr-code { text-align: center; }
            .qr-code img { max-width: 40mm; }
        </style></head><body>";
        
        $html .= "<div class='label label-{$labelType}'>";
        
        // Title
        if (isset($data['title'])) {
            $html .= "<div class='label-title'>" . htmlspecialchars($data['title']) . "</div>";
        }
        
        // Identifier
        if (isset($data['identifier'])) {
            $html .= "<div class='label-identifier'>" . htmlspecialchars($data['identifier']) . "</div>";
        }
        
        // Other fields
        $skipFields = ['title', 'identifier', 'barcode', 'qr_code'];
        foreach ($data as $field => $value) {
            if (in_array($field, $skipFields) || empty($value)) continue;
            
            $label = ucwords(str_replace('_', ' ', $field));
            $html .= "<div class='label-field'><span class='label-field-label'>{$label}:</span> " . htmlspecialchars($value) . "</div>";
        }
        
        // Barcode
        if (isset($data['barcode'])) {
            $html .= "<div class='barcode'>*" . htmlspecialchars($data['barcode']) . "*</div>";
        }
        
        // QR Code
        if (isset($data['qr_code'])) {
            $html .= "<div class='qr-code'><img src='{$data['qr_code']}' alt='QR Code'></div>";
        }
        
        $html .= "</div></body></html>";
        
        return $html;
    }
    
    /**
     * Generate barcode data (Code 39)
     */
    private function generateBarcode(string $identifier): string
    {
        // Clean identifier for Code 39 (uppercase alphanumeric + some special chars)
        return strtoupper(preg_replace('/[^A-Z0-9\-\.\ \$\/\+\%]/', '', $identifier));
    }
    
    /**
     * Generate QR code URL (using external API or local library)
     */
    private function generateQrCode(string $slug): string
    {
        $baseUrl = $this->getSetting('site_base_url', 'https://archives.theahg.co.za');
        $url = $baseUrl . '/index.php/' . $slug;
        
        // Using Google Charts API (or could use local library like phpqrcode)
        return "https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl=" . urlencode($url);
    }
    
    /**
     * Generate PDF from HTML
     */
    private function generatePdf(string $html, string $identifier, array $template): string
    {
        // Create output directory if needed
        if (!is_dir($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }
        
        $filename = 'label_' . preg_replace('/[^a-zA-Z0-9]/', '_', $identifier) . '_' . time() . '.pdf';
        $filePath = $this->outputPath . $filename;
        
        // If TCPDF is available
        if (class_exists('TCPDF')) {
            $pdf = new \TCPDF('P', 'mm', [$template['width'], $template['height']]);
            $pdf->SetMargins(0, 0, 0);
            $pdf->AddPage();
            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->Output($filePath, 'F');
        } else {
            // Fallback: save HTML
            $filePath = str_replace('.pdf', '.html', $filePath);
            file_put_contents($filePath, $html);
        }
        
        return $filePath;
    }
    
    /**
     * Save label record to database
     */
    private function saveLabel(int $objectId, string $labelType, string $template, array $labelData, ?string $filePath): void
    {
        DB::table('spectrum_label')->insert([
            'object_id' => $objectId,
            'label_type' => $labelType,
            'template' => $template,
            'label_data' => json_encode($labelData),
            'file_path' => $filePath,
            'generated_by' => $this->getCurrentUserId(),
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    
    /**
     * Get available templates
     */
    public function getTemplates(): array
    {
        return $this->templates;
    }
    
    /**
     * Batch generate labels
     */
    public function batchGenerateLabels(array $objectIds, string $labelType = 'object', string $template = 'standard', array $options = []): array
    {
        $results = [];
        
        foreach ($objectIds as $objectId) {
            try {
                $results[$objectId] = $this->generateLabel($objectId, $labelType, $template, $options);
            } catch (\Exception $e) {
                $this->logger->error("Failed to generate label for object {$objectId}", ['error' => $e->getMessage()]);
                $results[$objectId] = ['error' => $e->getMessage()];
            }
        }
        
        return $results;
    }
    
    private function getSetting(string $key, string $default = ''): string
    {
        try {
            $result = DB::table('setting_i18n')
                ->where('name', $key)
                ->value('value');
            return $result ?? $default;
        } catch (\Exception $e) {
            return $default;
        }
    }
    
    private function getCurrentUserId(): ?int
    {
        // Get from session/context if available
        return null;
    }
}
