# Spectrum Extension

Spectrum Collections Management extension for AtoM Framework v2.

## Features

- **Label Generation** - Object labels, storage labels, exhibition labels, QR codes, barcodes
- **Loan Management** - Incoming/outgoing loans with agreement generation
- **Provenance Tracking** - Ownership history and timeline

## Installation

### 1. Run Migrations

```php
use AtomFramework\Extensions\Spectrum\SpectrumAdapter;
use AtomFramework\Core\Database\DatabaseManager;

$spectrum = new SpectrumAdapter();
$db = new DatabaseManager();

// Run migrations
$results = $spectrum->migrate($db);

// Check status
$status = $spectrum->getMigrationStatus($db);
```

### 2. Rollback (if needed)

```php
$results = $spectrum->rollback($db);
```

## Usage

### Labels

```php
$spectrum = new SpectrumAdapter();

// Generate object label
$label = $spectrum->generateLabel($objectId, 'object', 'standard');

// Generate QR code label
$qr = $spectrum->generateLabel($objectId, 'qr');

// Generate exhibition label
$exhibition = $spectrum->generateLabel($objectId, 'exhibition');
```

### Loans

```php
// Create loan
$loanId = $spectrum->createLoan([
    'loan_type' => 'outgoing',
    'contact_name' => 'John Smith',
    'contact_email' => 'john@museum.org',
    'purpose' => 'Exhibition',
    'loan_start_date' => '2025-01-01',
    'loan_end_date' => '2025-06-30',
    'object_ids' => [123, 456, 789]
]);

// Generate agreement
$agreement = $spectrum->generateLoanAgreement($loanId, 'pdf');
```

### Provenance

```php
// Get provenance
$provenance = $spectrum->getProvenance($objectId);

// Generate report
$report = $spectrum->generateProvenanceReport($objectId);
```

## Database Tables

- `spectrum_event` - Procedure events
- `spectrum_loan` - Loan records
- `spectrum_loan_item` - Items on loan
- `spectrum_label` - Generated labels

## Author

The Archives and Heritage Group
