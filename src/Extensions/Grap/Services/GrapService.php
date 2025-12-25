<?php

declare(strict_types=1);

namespace AtomFramework\Extensions\Grap\Services;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

/**
 * GRAP 103 Heritage Asset Service
 * 
 * Manages GRAP 103 compliance for heritage assets
 * 
 * @package AtomFramework\Extensions\Grap
 * @author Johan Pieterse - The Archive and Heritage Group
 * @version 1.0.0
 */
class GrapService
{
    private Logger $logger;
    
    // Asset classes per GRAP 103
    public const ASSET_CLASSES = [
        'art' => 'Art Works',
        'antiques' => 'Antiques',
        'museum_collections' => 'Museum Collections',
        'library_collections' => 'Library Collections',
        'archival_collections' => 'Archival Collections',
        'natural_heritage' => 'Natural Heritage Assets',
        'cultural_heritage' => 'Cultural Heritage Assets',
        'monuments' => 'Monuments and Statues',
        'archaeological' => 'Archaeological Assets'
    ];
    
    public function __construct()
    {
        $this->logger = new Logger('grap');
        $logPath = '/var/log/atom/grap.log';
        
        if (is_writable(dirname($logPath))) {
            $this->logger->pushHandler(new RotatingFileHandler($logPath, 30, Logger::INFO));
        }
    }
    
    /**
     * Get or create GRAP data for an object
     */
    public function getAsset(int $objectId): ?object
    {
        return DB::table('grap_heritage_asset')
            ->where('object_id', $objectId)
            ->first();
    }
    
    /**
     * Save GRAP asset data
     */
    public function saveAsset(int $objectId, array $data): int
    {
        $this->logger->info("Saving GRAP data for object {$objectId}");
        
        $existing = $this->getAsset($objectId);
        
        $record = [
            'repository_id' => $data['repository_id'] ?? null,
            'asset_number' => $data['asset_number'] ?? null,
            'recognition_status' => $data['recognition_status'] ?? 'not_assessed',
            'asset_class' => $data['asset_class'] ?? null,
            'asset_subclass' => $data['asset_subclass'] ?? null,
            'acquisition_date' => $data['acquisition_date'] ?? null,
            'acquisition_method' => $data['acquisition_method'] ?? null,
            'donor_source' => $data['donor_source'] ?? null,
            'cost_of_acquisition' => $data['cost_of_acquisition'] ?? null,
            'current_carrying_amount' => $data['current_carrying_amount'] ?? null,
            'impairment_loss' => $data['impairment_loss'] ?? 0,
            'measurement_basis' => $data['measurement_basis'] ?? null,
            'valuation_date' => $data['valuation_date'] ?? null,
            'valuer' => $data['valuer'] ?? null,
            'valuation_method' => $data['valuation_method'] ?? null,
            'physical_location' => $data['physical_location'] ?? null,
            'condition_description' => $data['condition_description'] ?? null,
            'insurance_value' => $data['insurance_value'] ?? null,
            'insurance_policy' => $data['insurance_policy'] ?? null,
            'insurance_expiry' => $data['insurance_expiry'] ?? null,
            'notes' => $data['notes'] ?? null,
            'updated_at' => now(),
            'updated_by' => $data['user_id'] ?? null
        ];
        
        if ($existing) {
            DB::table('grap_heritage_asset')
                ->where('object_id', $objectId)
                ->update($record);
                
            // Log the change
            $this->logTransaction($existing->id, 'correction', [
                'description' => 'Asset data updated'
            ]);
            
            return $existing->id;
        } else {
            $record['object_id'] = $objectId;
            $record['created_at'] = now();
            $record['created_by'] = $data['user_id'] ?? null;
            
            $id = DB::table('grap_heritage_asset')->insertGetId($record);
            
            // Log acquisition
            if (!empty($data['cost_of_acquisition'])) {
                $this->logTransaction($id, 'acquisition', [
                    'amount' => $data['cost_of_acquisition'],
                    'new_value' => $data['current_carrying_amount'] ?? $data['cost_of_acquisition'],
                    'description' => 'Initial acquisition recorded'
                ]);
            }
            
            return $id;
        }
    }
    
    /**
     * Log a GRAP transaction
     */
    public function logTransaction(int $assetId, string $type, array $data): int
    {
        return DB::table('grap_transaction_log')->insertGetId([
            'asset_id' => $assetId,
            'transaction_type' => $type,
            'amount' => $data['amount'] ?? null,
            'previous_value' => $data['previous_value'] ?? null,
            'new_value' => $data['new_value'] ?? null,
            'transaction_date' => $data['date'] ?? now(),
            'reference' => $data['reference'] ?? null,
            'description' => $data['description'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
    
    /**
     * Record revaluation
     */
    public function recordRevaluation(int $assetId, float $newValue, array $data = []): bool
    {
        $asset = DB::table('grap_heritage_asset')->where('id', $assetId)->first();
        
        if (!$asset) {
            throw new \RuntimeException("Asset not found: {$assetId}");
        }
        
        $previousValue = $asset->current_carrying_amount ?? 0;
        
        DB::table('grap_heritage_asset')
            ->where('id', $assetId)
            ->update([
                'current_carrying_amount' => $newValue,
                'valuation_date' => $data['valuation_date'] ?? now(),
                'valuer' => $data['valuer'] ?? null,
                'valuation_method' => $data['valuation_method'] ?? null,
                'updated_at' => now()
            ]);
        
        $this->logTransaction($assetId, 'revaluation', [
            'previous_value' => $previousValue,
            'new_value' => $newValue,
            'amount' => $newValue - $previousValue,
            'description' => $data['description'] ?? 'Asset revaluation'
        ]);
        
        return true;
    }
    
    /**
     * Record impairment
     */
    public function recordImpairment(int $assetId, float $impairmentAmount, array $data = []): bool
    {
        $asset = DB::table('grap_heritage_asset')->where('id', $assetId)->first();
        
        if (!$asset) {
            throw new \RuntimeException("Asset not found: {$assetId}");
        }
        
        $previousValue = $asset->current_carrying_amount ?? 0;
        $previousImpairment = $asset->impairment_loss ?? 0;
        $newValue = $previousValue - $impairmentAmount;
        
        DB::table('grap_heritage_asset')
            ->where('id', $assetId)
            ->update([
                'current_carrying_amount' => $newValue,
                'impairment_loss' => $previousImpairment + $impairmentAmount,
                'updated_at' => now()
            ]);
        
        $this->logTransaction($assetId, 'impairment', [
            'previous_value' => $previousValue,
            'new_value' => $newValue,
            'amount' => -$impairmentAmount,
            'description' => $data['description'] ?? 'Impairment recorded'
        ]);
        
        return true;
    }
    
    /**
     * Run compliance check
     */
    public function runComplianceCheck(int $assetId, string $checkType = 'full'): array
    {
        $asset = DB::table('grap_heritage_asset')->where('id', $assetId)->first();
        
        if (!$asset) {
            throw new \RuntimeException("Asset not found: {$assetId}");
        }
        
        $issues = [];
        $score = 100;
        
        // Check recognition status
        if ($asset->recognition_status === 'not_assessed') {
            $issues[] = ['field' => 'recognition_status', 'severity' => 'high', 'message' => 'Recognition status not assessed'];
            $score -= 15;
        }
        
        // Check asset class
        if (empty($asset->asset_class)) {
            $issues[] = ['field' => 'asset_class', 'severity' => 'high', 'message' => 'Asset class not specified'];
            $score -= 10;
        }
        
        // Check measurement basis
        if (empty($asset->measurement_basis)) {
            $issues[] = ['field' => 'measurement_basis', 'severity' => 'medium', 'message' => 'Measurement basis not specified'];
            $score -= 10;
        }
        
        // Check carrying amount
        if ($asset->recognition_status === 'recognized' && empty($asset->current_carrying_amount)) {
            $issues[] = ['field' => 'current_carrying_amount', 'severity' => 'high', 'message' => 'Carrying amount missing for recognized asset'];
            $score -= 15;
        }
        
        // Check valuation date (should be within 5 years for fair value)
        if ($asset->measurement_basis === 'fair_value') {
            if (empty($asset->valuation_date)) {
                $issues[] = ['field' => 'valuation_date', 'severity' => 'high', 'message' => 'Valuation date required for fair value measurement'];
                $score -= 10;
            } else {
                $valuationDate = new \DateTime($asset->valuation_date);
                $fiveYearsAgo = new \DateTime('-5 years');
                if ($valuationDate < $fiveYearsAgo) {
                    $issues[] = ['field' => 'valuation_date', 'severity' => 'medium', 'message' => 'Valuation is older than 5 years'];
                    $score -= 5;
                }
            }
        }
        
        // Check insurance
        if (!empty($asset->insurance_expiry)) {
            $expiryDate = new \DateTime($asset->insurance_expiry);
            $today = new \DateTime();
            if ($expiryDate < $today) {
                $issues[] = ['field' => 'insurance_expiry', 'severity' => 'high', 'message' => 'Insurance has expired'];
                $score -= 10;
            } elseif ($expiryDate < (new \DateTime('+30 days'))) {
                $issues[] = ['field' => 'insurance_expiry', 'severity' => 'medium', 'message' => 'Insurance expiring within 30 days'];
                $score -= 5;
            }
        }
        
        $score = max(0, $score);
        
        // Save compliance check
        DB::table('grap_compliance_check')->insert([
            'asset_id' => $assetId,
            'check_date' => now(),
            'check_type' => $checkType,
            'score' => $score,
            'results' => json_encode(['score' => $score, 'checks_performed' => 7]),
            'issues' => json_encode($issues),
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // Update asset compliance score
        DB::table('grap_heritage_asset')
            ->where('id', $assetId)
            ->update([
                'compliance_score' => $score,
                'last_compliance_check' => now()
            ]);
        
        return [
            'score' => $score,
            'issues' => $issues,
            'status' => $score >= 80 ? 'compliant' : ($score >= 50 ? 'partially_compliant' : 'non_compliant')
        ];
    }
    
    /**
     * Get statistics
     */
    public function getStatistics(?int $repositoryId = null): array
    {
        $query = DB::table('grap_heritage_asset');
        
        if ($repositoryId) {
            $query->where('repository_id', $repositoryId);
        }
        
        $total = $query->count();
        
        $stats = [
            'total_assets' => $total,
            'recognized' => (clone $query)->where('recognition_status', 'recognized')->count(),
            'not_recognized' => (clone $query)->where('recognition_status', 'not_recognized')->count(),
            'pending' => (clone $query)->where('recognition_status', 'pending')->count(),
            'not_assessed' => (clone $query)->where('recognition_status', 'not_assessed')->count(),
            'total_carrying_amount' => (clone $query)->sum('current_carrying_amount'),
            'total_impairment' => (clone $query)->sum('impairment_loss'),
            'by_class' => [],
            'compliance' => [
                'average_score' => (clone $query)->avg('compliance_score'),
                'compliant' => (clone $query)->where('compliance_score', '>=', 80)->count(),
                'non_compliant' => (clone $query)->where('compliance_score', '<', 50)->count()
            ]
        ];
        
        // By class breakdown
        $byClass = DB::table('grap_heritage_asset')
            ->select('asset_class', DB::raw('COUNT(*) as count'), DB::raw('SUM(current_carrying_amount) as value'))
            ->when($repositoryId, fn($q) => $q->where('repository_id', $repositoryId))
            ->groupBy('asset_class')
            ->get();
            
        foreach ($byClass as $row) {
            $stats['by_class'][$row->asset_class ?? 'unclassified'] = [
                'count' => $row->count,
                'value' => $row->value ?? 0
            ];
        }
        
        return $stats;
    }
    
    /**
     * Generate NT Asset Register Export
     */
    public function exportAssetRegister(?int $repositoryId = null): array
    {
        $query = DB::table('grap_heritage_asset as g')
            ->join('information_object as io', 'g.object_id', '=', 'io.id')
            ->leftJoin('information_object_i18n as i18n', function($join) {
                $join->on('io.id', '=', 'i18n.id')
                     ->on('io.source_culture', '=', 'i18n.culture');
            })
            ->leftJoin('slug as s', 'io.id', '=', 's.object_id')
            ->select([
                'g.*',
                'io.identifier',
                'i18n.title',
                's.slug'
            ]);
            
        if ($repositoryId) {
            $query->where('g.repository_id', $repositoryId);
        }
        
        return $query->orderBy('g.asset_number')->get()->toArray();
    }
    
    /**
     * Create financial year snapshot
     */
    public function createSnapshot(string $financialYear, ?int $repositoryId = null): int
    {
        $stats = $this->getStatistics($repositoryId);
        
        return DB::table('grap_financial_year_snapshot')->insertGetId([
            'repository_id' => $repositoryId,
            'financial_year' => $financialYear,
            'snapshot_date' => now(),
            'total_assets' => $stats['total_assets'],
            'recognized_assets' => $stats['recognized'],
            'total_carrying_amount' => $stats['total_carrying_amount'],
            'total_impairment' => $stats['total_impairment'],
            'by_class_breakdown' => json_encode($stats['by_class']),
            'compliance_percentage' => $stats['compliance']['average_score'],
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }
}
