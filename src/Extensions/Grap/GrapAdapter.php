<?php

declare(strict_types=1);

namespace AtomFramework\Extensions\Grap;

use AtomFramework\Extensions\Grap\Services\GrapService;
use AtomFramework\Extensions\Grap\Database\Migrations\MigrationRunner;
use AtomFramework\Core\Database\DatabaseManager;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

/**
 * GRAP 103 Extension Adapter
 * 
 * Main entry point for GRAP 103 Heritage Asset functionality
 * 
 * @package AtomFramework\Extensions\Grap
 * @author Johan Pieterse - The Archive and Heritage Group
 * @version 1.0.0
 */
class GrapAdapter
{
    private Logger $logger;
    private ?GrapService $grapService = null;
    
    public function __construct()
    {
        $this->logger = new Logger('grap');
        $logPath = '/var/log/atom/grap.log';
        
        if (is_writable(dirname($logPath))) {
            $this->logger->pushHandler(new RotatingFileHandler($logPath, 30, Logger::INFO));
        }
    }
    
    /**
     * Get GRAP Service
     */
    public function service(): GrapService
    {
        if ($this->grapService === null) {
            $this->grapService = new GrapService();
        }
        return $this->grapService;
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
    
    // Convenience methods
    
    public function getAsset(int $objectId): ?object
    {
        return $this->service()->getAsset($objectId);
    }
    
    public function saveAsset(int $objectId, array $data): int
    {
        return $this->service()->saveAsset($objectId, $data);
    }
    
    public function runComplianceCheck(int $assetId): array
    {
        return $this->service()->runComplianceCheck($assetId);
    }
    
    public function getStatistics(?int $repositoryId = null): array
    {
        return $this->service()->getStatistics($repositoryId);
    }
    
    public function exportAssetRegister(?int $repositoryId = null): array
    {
        return $this->service()->exportAssetRegister($repositoryId);
    }
    
    /**
     * Get extension info
     */
    public static function getInfo(): array
    {
        return [
            'name' => 'GRAP 103 Extension',
            'version' => '1.0.0',
            'description' => 'GRAP 103 Heritage Asset accounting and compliance',
            'author' => 'The Archives and Heritage Group',
            'features' => [
                'Heritage asset tracking per GRAP 103',
                'Recognition status management',
                'Valuation and revaluation recording',
                'Impairment tracking',
                'Compliance checking',
                'National Treasury export format',
                'Financial year snapshots'
            ]
        ];
    }
}
