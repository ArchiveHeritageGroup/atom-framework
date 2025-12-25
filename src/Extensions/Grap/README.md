# GRAP 103 Extension

GRAP 103 Heritage Asset accounting and compliance extension for AtoM Framework v2.

## Features

- **Heritage Asset Management** - Track assets per GRAP 103 requirements
- **Recognition Status** - Manage asset recognition
- **Valuation & Revaluation** - Record and track valuations
- **Impairment Tracking** - Record impairment losses
- **Compliance Checking** - Automated compliance scoring
- **Export** - National Treasury format exports
- **Financial Snapshots** - Year-end snapshots for reporting

## Installation

### 1. Run Migrations

```php
use AtomFramework\Extensions\Grap\GrapAdapter;
use AtomFramework\Core\Database\DatabaseManager;

$grap = new GrapAdapter();
$db = new DatabaseManager();

// Run migrations
$results = $grap->migrate($db);

// Check status
$status = $grap->getMigrationStatus($db);
```

### 2. Rollback (if needed)

```php
$results = $grap->rollback($db);
```

## Usage

### Asset Management

```php
$grap = new GrapAdapter();

// Get asset data
$asset = $grap->getAsset($objectId);

// Save asset data
$assetId = $grap->saveAsset($objectId, [
    'recognition_status' => 'recognized',
    'asset_class' => 'art',
    'cost_of_acquisition' => 50000.00,
    'current_carrying_amount' => 75000.00,
    'measurement_basis' => 'fair_value',
    'valuation_date' => '2024-06-30',
    'valuer' => 'ABC Valuers'
]);
```

### Compliance

```php
// Run compliance check
$result = $grap->runComplianceCheck($assetId);
// Returns: ['score' => 85, 'issues' => [...], 'status' => 'compliant']
```

### Statistics & Reports

```php
// Get statistics
$stats = $grap->getStatistics($repositoryId);

// Export asset register
$assets = $grap->exportAssetRegister($repositoryId);
```

## Database Tables

- `grap_heritage_asset` - Main asset data
- `grap_transaction_log` - Audit trail
- `grap_financial_year_snapshot` - Year-end snapshots
- `grap_compliance_check` - Compliance check history

## Asset Classes (GRAP 103)

- Art Works
- Antiques
- Museum Collections
- Library Collections
- Archival Collections
- Natural Heritage Assets
- Cultural Heritage Assets
- Monuments and Statues
- Archaeological Assets

## Author

The Archives and Heritage Group
