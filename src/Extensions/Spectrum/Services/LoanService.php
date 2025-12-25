<?php

declare(strict_types=1);

namespace AtomFramework\Extensions\Spectrum\Services;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

/**
 * Loan Agreement Service
 * 
 * Manages loans (incoming/outgoing) and generates loan agreements
 * 
 * @package AtomFramework\Extensions\Spectrum
 * @author Johan Pieterse - The Archive and Heritage Group
 * @version 1.0.0
 */
class LoanService
{
    private Logger $logger;
    private string $outputPath;
    
    public function __construct()
    {
        $this->logger = new Logger('spectrum-loans');
        $logPath = '/var/log/atom/spectrum-loans.log';
        
        if (is_writable(dirname($logPath))) {
            $this->logger->pushHandler(new RotatingFileHandler($logPath, 30, Logger::INFO));
        }
        
        $this->outputPath = '/var/www/html/uploads/loans/';
    }
    
    /**
     * Create a new loan
     */
    public function createLoan(array $data): int
    {
        $this->logger->info("Creating new loan", ['type' => $data['loan_type'] ?? 'unknown']);
        
        // Generate loan number
        $loanNumber = $this->generateLoanNumber($data['loan_type'] ?? 'outgoing');
        
        $loanId = DB::table('spectrum_loan')->insertGetId([
            'loan_number' => $loanNumber,
            'loan_type' => $data['loan_type'] ?? 'outgoing',
            'borrower_id' => $data['borrower_id'] ?? null,
            'lender_id' => $data['lender_id'] ?? null,
            'contact_name' => $data['contact_name'] ?? null,
            'contact_email' => $data['contact_email'] ?? null,
            'contact_phone' => $data['contact_phone'] ?? null,
            'contact_address' => $data['contact_address'] ?? null,
            'purpose' => $data['purpose'] ?? null,
            'conditions' => $data['conditions'] ?? null,
            'request_date' => $data['request_date'] ?? now(),
            'loan_start_date' => $data['loan_start_date'] ?? null,
            'loan_end_date' => $data['loan_end_date'] ?? null,
            'status' => 'requested',
            'insurance_value' => $data['insurance_value'] ?? null,
            'insurance_policy' => $data['insurance_policy'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // Add loan items
        if (!empty($data['object_ids'])) {
            foreach ($data['object_ids'] as $objectId) {
                $this->addLoanItem($loanId, $objectId, $data['item_values'][$objectId] ?? null);
            }
        }
        
        $this->logger->info("Loan created", ['loan_id' => $loanId, 'loan_number' => $loanNumber]);
        
        return $loanId;
    }
    
    /**
     * Add item to loan
     */
    public function addLoanItem(int $loanId, int $objectId, ?float $itemValue = null): int
    {
        return DB::table('spectrum_loan_item')->insertGetId([
            'loan_id' => $loanId,
            'object_id' => $objectId,
            'item_value' => $itemValue,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    
    /**
     * Update loan status
     */
    public function updateLoanStatus(int $loanId, string $status, ?int $approvedBy = null): bool
    {
        $updates = [
            'status' => $status,
            'updated_at' => now()
        ];
        
        if ($status === 'approved' && $approvedBy) {
            $updates['approved_by'] = $approvedBy;
            $updates['approval_date'] = now();
        }
        
        if ($status === 'returned') {
            $updates['actual_return_date'] = now();
        }
        
        return DB::table('spectrum_loan')
            ->where('id', $loanId)
            ->update($updates) > 0;
    }
    
    /**
     * Get loan details
     */
    public function getLoan(int $loanId): ?object
    {
        $loan = DB::table('spectrum_loan')
            ->where('id', $loanId)
            ->first();
            
        if ($loan) {
            $loan->items = $this->getLoanItems($loanId);
        }
        
        return $loan;
    }
    
    /**
     * Get loan by loan number
     */
    public function getLoanByNumber(string $loanNumber): ?object
    {
        $loan = DB::table('spectrum_loan')
            ->where('loan_number', $loanNumber)
            ->first();
            
        if ($loan) {
            $loan->items = $this->getLoanItems($loan->id);
        }
        
        return $loan;
    }
    
    /**
     * Get loan items with object details
     */
    public function getLoanItems(int $loanId): array
    {
        return DB::table('spectrum_loan_item as li')
            ->join('information_object as io', 'li.object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as i18n', function($join) {
                $join->on('io.id', '=', 'i18n.id')
                     ->on('io.source_culture', '=', 'i18n.culture');
            })
            ->where('li.loan_id', $loanId)
            ->select('li.*', 'io.identifier', 'i18n.title')
            ->get()
            ->toArray();
    }
    
    /**
     * Generate loan agreement document
     */
    public function generateLoanAgreement(int $loanId, string $format = 'html'): array
    {
        $this->logger->info("Generating loan agreement for loan {$loanId}");
        
        $loan = $this->getLoan($loanId);
        
        if (!$loan) {
            throw new \RuntimeException("Loan not found: {$loanId}");
        }
        
        // Get institution details
        $institution = $this->getInstitutionDetails();
        
        // Generate HTML
        $html = $this->generateAgreementHtml($loan, $institution);
        
        if ($format === 'pdf') {
            $filePath = $this->generatePdf($html, $loan->loan_number);
            return ['html' => $html, 'file_path' => $filePath, 'format' => 'pdf'];
        }
        
        return ['html' => $html, 'format' => 'html'];
    }
    
    /**
     * Generate loan agreement HTML
     */
    private function generateAgreementHtml(object $loan, array $institution): string
    {
        $isOutgoing = $loan->loan_type === 'outgoing';
        $title = $isOutgoing ? 'OUTGOING LOAN AGREEMENT' : 'INCOMING LOAN AGREEMENT';
        
        $html = "<!DOCTYPE html><html><head><meta charset='utf-8'>";
        $html .= "<style>
            body { font-family: Arial, sans-serif; font-size: 11pt; line-height: 1.5; margin: 20mm; }
            .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #333; padding-bottom: 10px; }
            .header h1 { font-size: 18pt; margin: 0; }
            .header h2 { font-size: 14pt; margin: 5px 0 0 0; color: #666; }
            .logo { max-height: 60px; margin-bottom: 10px; }
            .section { margin-bottom: 15px; }
            .section-title { font-weight: bold; font-size: 12pt; border-bottom: 1px solid #ccc; padding-bottom: 3px; margin-bottom: 8px; }
            .field { margin-bottom: 5px; }
            .field-label { font-weight: bold; display: inline-block; width: 150px; }
            table { width: 100%; border-collapse: collapse; margin: 10px 0; }
            th, td { border: 1px solid #333; padding: 8px; text-align: left; }
            th { background-color: #f5f5f5; }
            .conditions { background-color: #f9f9f9; padding: 10px; border: 1px solid #ddd; font-size: 10pt; }
            .signatures { margin-top: 40px; }
            .signature-block { display: inline-block; width: 45%; vertical-align: top; }
            .signature-line { border-bottom: 1px solid #333; height: 40px; margin-top: 30px; }
            .signature-name { margin-top: 5px; }
            .footer { margin-top: 30px; font-size: 9pt; color: #666; text-align: center; border-top: 1px solid #ccc; padding-top: 10px; }
        </style></head><body>";
        
        // Header
        $html .= "<div class='header'>";
        if (!empty($institution['logo'])) {
            $html .= "<img src='{$institution['logo']}' class='logo' alt='Logo'>";
        }
        $html .= "<h1>{$institution['name']}</h1>";
        $html .= "<h2>{$title}</h2>";
        $html .= "</div>";
        
        // Loan Details
        $html .= "<div class='section'>";
        $html .= "<div class='section-title'>LOAN DETAILS</div>";
        $html .= "<div class='field'><span class='field-label'>Loan Number:</span> {$loan->loan_number}</div>";
        $html .= "<div class='field'><span class='field-label'>Loan Type:</span> " . ucfirst($loan->loan_type) . "</div>";
        $html .= "<div class='field'><span class='field-label'>Purpose:</span> " . htmlspecialchars($loan->purpose ?? 'Not specified') . "</div>";
        $html .= "<div class='field'><span class='field-label'>Start Date:</span> " . ($loan->loan_start_date ?? 'TBD') . "</div>";
        $html .= "<div class='field'><span class='field-label'>End Date:</span> " . ($loan->loan_end_date ?? 'TBD') . "</div>";
        $html .= "</div>";
        
        // Borrower/Lender Details
        $partyTitle = $isOutgoing ? 'BORROWER DETAILS' : 'LENDER DETAILS';
        $html .= "<div class='section'>";
        $html .= "<div class='section-title'>{$partyTitle}</div>";
        $html .= "<div class='field'><span class='field-label'>Contact Name:</span> " . htmlspecialchars($loan->contact_name ?? '') . "</div>";
        $html .= "<div class='field'><span class='field-label'>Email:</span> " . htmlspecialchars($loan->contact_email ?? '') . "</div>";
        $html .= "<div class='field'><span class='field-label'>Phone:</span> " . htmlspecialchars($loan->contact_phone ?? '') . "</div>";
        $html .= "<div class='field'><span class='field-label'>Address:</span> " . nl2br(htmlspecialchars($loan->contact_address ?? '')) . "</div>";
        $html .= "</div>";
        
        // Items on Loan
        $html .= "<div class='section'>";
        $html .= "<div class='section-title'>ITEMS ON LOAN</div>";
        $html .= "<table>";
        $html .= "<tr><th>No.</th><th>Identifier</th><th>Title</th><th>Value</th><th>Condition</th></tr>";
        
        $totalValue = 0;
        foreach ($loan->items as $i => $item) {
            $item = (object) $item;
            $value = $item->item_value ?? 0;
            $totalValue += $value;
            $html .= "<tr>";
            $html .= "<td>" . ($i + 1) . "</td>";
            $html .= "<td>" . htmlspecialchars($item->identifier ?? '') . "</td>";
            $html .= "<td>" . htmlspecialchars($item->title ?? 'Untitled') . "</td>";
            $html .= "<td>R " . number_format($value, 2) . "</td>";
            $html .= "<td>" . htmlspecialchars($item->condition_on_departure ?? 'To be recorded') . "</td>";
            $html .= "</tr>";
        }
        
        $html .= "<tr><th colspan='3'>TOTAL</th><th colspan='2'>R " . number_format($totalValue, 2) . "</th></tr>";
        $html .= "</table>";
        $html .= "</div>";
        
        // Insurance
        $html .= "<div class='section'>";
        $html .= "<div class='section-title'>INSURANCE</div>";
        $html .= "<div class='field'><span class='field-label'>Insurance Value:</span> R " . number_format($loan->insurance_value ?? $totalValue, 2) . "</div>";
        $html .= "<div class='field'><span class='field-label'>Policy Number:</span> " . htmlspecialchars($loan->insurance_policy ?? 'To be provided') . "</div>";
        $html .= "</div>";
        
        // Conditions
        $html .= "<div class='section'>";
        $html .= "<div class='section-title'>LOAN CONDITIONS</div>";
        $html .= "<div class='conditions'>";
        if (!empty($loan->conditions)) {
            $html .= nl2br(htmlspecialchars($loan->conditions));
        } else {
            $html .= $this->getStandardConditions($loan->loan_type);
        }
        $html .= "</div>";
        $html .= "</div>";
        
        // Signatures
        $html .= "<div class='signatures'>";
        $html .= "<div class='signature-block'>";
        $html .= "<strong>FOR THE " . ($isOutgoing ? 'LENDER' : 'BORROWER') . " ({$institution['name']})</strong>";
        $html .= "<div class='signature-line'></div>";
        $html .= "<div class='signature-name'>Signature</div>";
        $html .= "<div class='signature-line'></div>";
        $html .= "<div class='signature-name'>Name &amp; Title</div>";
        $html .= "<div class='signature-line'></div>";
        $html .= "<div class='signature-name'>Date</div>";
        $html .= "</div>";
        
        $html .= "<div class='signature-block'>";
        $html .= "<strong>FOR THE " . ($isOutgoing ? 'BORROWER' : 'LENDER') . "</strong>";
        $html .= "<div class='signature-line'></div>";
        $html .= "<div class='signature-name'>Signature</div>";
        $html .= "<div class='signature-line'></div>";
        $html .= "<div class='signature-name'>Name &amp; Title</div>";
        $html .= "<div class='signature-line'></div>";
        $html .= "<div class='signature-name'>Date</div>";
        $html .= "</div>";
        $html .= "</div>";
        
        // Footer
        $html .= "<div class='footer'>";
        $html .= "Generated on " . date('Y-m-d H:i') . " | Loan Number: {$loan->loan_number}";
        $html .= "</div>";
        
        $html .= "</body></html>";
        
        return $html;
    }
    
    /**
     * Get standard loan conditions
     */
    private function getStandardConditions(string $loanType): string
    {
        $conditions = [
            "1. The borrower agrees to exercise all reasonable care in handling and displaying the loaned items.",
            "2. The items must be kept in a secure, climate-controlled environment suitable for their preservation.",
            "3. No conservation, cleaning, or repair work may be performed on the items without prior written consent from the lender.",
            "4. The borrower shall insure all items against damage, loss, or theft for their full stated value.",
            "5. The items may only be used for the stated purpose and may not be photographed or reproduced without permission.",
            "6. The items must be returned by the agreed date in the same condition as received.",
            "7. Any damage, loss, or theft must be reported immediately to the lender.",
            "8. The lender reserves the right to recall items before the loan end date with reasonable notice.",
            "9. The borrower shall credit the lender in any publications or displays featuring the loaned items.",
            "10. This agreement is governed by the laws of the Republic of South Africa."
        ];
        
        return implode("\n", $conditions);
    }
    
    /**
     * Get institution details from settings
     */
    private function getInstitutionDetails(): array
    {
        return [
            'name' => $this->getSetting('site_title', 'The Archives and Heritage Group'),
            'address' => $this->getSetting('site_address', ''),
            'phone' => $this->getSetting('site_phone', ''),
            'email' => $this->getSetting('site_email', ''),
            'logo' => $this->getSetting('site_logo', '')
        ];
    }
    
    /**
     * Generate loan number
     */
    private function generateLoanNumber(string $type): string
    {
        $prefix = $type === 'incoming' ? 'LI' : 'LO';
        $year = date('Y');
        
        // Get count for this year
        $count = DB::table('spectrum_loan')
            ->where('loan_number', 'like', "{$prefix}-{$year}-%")
            ->count();
            
        return sprintf('%s-%s-%04d', $prefix, $year, $count + 1);
    }
    
    /**
     * Generate PDF from HTML
     */
    private function generatePdf(string $html, string $loanNumber): string
    {
        if (!is_dir($this->outputPath)) {
            mkdir($this->outputPath, 0755, true);
        }
        
        $filename = 'loan_agreement_' . $loanNumber . '_' . date('Ymd') . '.pdf';
        $filePath = $this->outputPath . $filename;
        
        if (class_exists('TCPDF')) {
            $pdf = new \TCPDF('P', 'mm', 'A4');
            $pdf->SetMargins(15, 15, 15);
            $pdf->AddPage();
            $pdf->writeHTML($html, true, false, true, false, '');
            $pdf->Output($filePath, 'F');
        } else {
            // Fallback to HTML
            $filePath = str_replace('.pdf', '.html', $filePath);
            file_put_contents($filePath, $html);
        }
        
        return $filePath;
    }
    
    /**
     * Get overdue loans
     */
    public function getOverdueLoans(): array
    {
        return DB::table('spectrum_loan')
            ->where('status', 'active')
            ->where('loan_end_date', '<', now())
            ->get()
            ->toArray();
    }
    
    /**
     * Get loans for an object
     */
    public function getLoansForObject(int $objectId): array
    {
        return DB::table('spectrum_loan as l')
            ->join('spectrum_loan_item as li', 'l.id', '=', 'li.loan_id')
            ->where('li.object_id', $objectId)
            ->select('l.*')
            ->distinct()
            ->orderBy('l.created_at', 'desc')
            ->get()
            ->toArray();
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
}
