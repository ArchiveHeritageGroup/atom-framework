<?php

declare(strict_types=1);

namespace AtomFramework\Extensions\Spectrum;

use AtomFramework\Extensions\Spectrum\Services\LabelService;
use AtomFramework\Extensions\Spectrum\Services\LoanService;
use AtomFramework\Extensions\Spectrum\Services\ProvenanceService;
use AtomFramework\Extensions\Spectrum\Database\Migrations\MigrationRunner;
use AtomFramework\Core\Database\DatabaseManager;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

/**
 * Spectrum Extension Adapter
 * 
 * Main entry point for Spectrum extension functionality
 * Provides: Labels, Loans, Provenance management
 * 
 * @package AtomFramework\Extensions\Spectrum
 * @author Johan Pieterse - The Archive and Heritage Group
 * @version 1.0.0
 */
class SpectrumAdapter
{
    private Logger $logger;
    private ?LabelService $labelService = null;
    private ?LoanService $loanService = null;
    private ?ProvenanceService $provenanceService = null;
    
    public function __construct()
    {
        $this->logger = new Logger('spectrum');
        $logPath = '/var/log/atom/spectrum.log';
        
        if (is_writable(dirname($logPath))) {
            $this->logger->pushHandler(new RotatingFileHandler($logPath, 30, Logger::INFO));
        }
    }
    
    /**
     * Get Label Service
     */
    public function labels(): LabelService
    {
        if ($this->labelService === null) {
            $this->labelService = new LabelService();
        }
        return $this->labelService;
    }
    
    /**
     * Get Loan Service
     */
    public function loans(): LoanService
    {
        if ($this->loanService === null) {
            $this->loanService = new LoanService();
        }
        return $this->loanService;
    }
    
    /**
     * Get Provenance Service
     */
    public function provenance(): ProvenanceService
    {
        if ($this->provenanceService === null) {
            $this->provenanceService = new ProvenanceService();
        }
        return $this->provenanceService;
    }
    
    /**
     * Run database migrations
     */
    public function migrate(DatabaseManager $db): array
    {
        $runner = new MigrationRunner($db, $this->logger);
        return $runner->runAll();
    }
    
    /**
     * Rollback database migrations
     */
    public function rollback(DatabaseManager $db): array
    {
        $runner = new MigrationRunner($db, $this->logger);
        return $runner->rollbackAll();
    }
    
    /**
     * Get migration status
     */
    public function getMigrationStatus(DatabaseManager $db): array
    {
        $runner = new MigrationRunner($db, $this->logger);
        return $runner->getStatus();
    }
    
    /**
     * Generate label for object
     */
    public function generateLabel(int $objectId, string $type = 'object', string $template = 'standard', array $options = []): array
    {
        return $this->labels()->generateLabel($objectId, $type, $template, $options);
    }
    
    /**
     * Create loan
     */
    public function createLoan(array $data): int
    {
        return $this->loans()->createLoan($data);
    }
    
    /**
     * Generate loan agreement
     */
    public function generateLoanAgreement(int $loanId, string $format = 'html'): array
    {
        return $this->loans()->generateLoanAgreement($loanId, $format);
    }
    
    /**
     * Get provenance for object
     */
    public function getProvenance(int $objectId): array
    {
        return $this->provenance()->getProvenance($objectId);
    }
    
    /**
     * Generate provenance report
     */
    public function generateProvenanceReport(int $objectId): string
    {
        return $this->provenance()->generateProvenanceReport($objectId);
    }
    
    /**
     * Get extension info
     */
    public static function getInfo(): array
    {
        return [
            'name' => 'Spectrum Extension',
            'version' => '1.0.0',
            'description' => 'Spectrum Collections Management - Labels, Loans, Provenance',
            'author' => 'The Archives and Heritage Group',
            'services' => [
                'LabelService' => 'Generate object, storage, exhibition, loan, QR, and barcode labels',
                'LoanService' => 'Manage incoming/outgoing loans and generate loan agreements',
                'ProvenanceService' => 'Track and report object provenance/ownership history'
            ]
        ];
    }
}
