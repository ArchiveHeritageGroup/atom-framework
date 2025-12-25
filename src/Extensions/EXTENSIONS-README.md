# AtoM Framework v2 - Extension Suites

## Overview

This package contains three comprehensive extension suites for AtoM 2.10 Laravel rebuild:

1. **Security Extension** - NARSSA/POPIA compliance, security clearances, audit logging
2. **Condition Extension** - Spectrum 5.0 compliant condition reporting, conservation tracking
3. **Privacy Extension** - POPIA/PAIA/GDPR compliance, ROPA, DSAR, breach management

All extensions follow the atom-framework architecture patterns using:
- Laravel Query Builder (no raw SQL)
- Monolog 3.0 logging with 30-day rotation
- PSR-4 autoloading
- Bootstrap 5 responsive UI
- AtoM 2.10 compatible routing

## Directory Structure

```
src/Extensions/
├── Security/
│   ├── Controllers/
│   │   └── SecurityComplianceController.php    (27K)
│   ├── Database/
│   │   └── SecurityMigrations.php              (13K)
│   ├── Routes/
│   │   └── routes.php                          (4K)
│   ├── Services/
│   │   ├── SecurityComplianceService.php       (21K)
│   │   └── AccessJustificationService.php      (17K)
│   └── Views/
│       └── compliance/
│           └── index.blade.php                 (15K)
│
├── Condition/
│   ├── Controllers/
│   │   └── ConditionController.php             (25K)
│   ├── Database/
│   │   └── ConditionMigrations.php             (14K)
│   ├── Routes/
│   │   └── routes.php                          (3K)
│   ├── Services/
│   │   ├── ConditionEventService.php           (13K)
│   │   ├── ConditionVocabularyService.php      (16K)
│   │   ├── ConditionExportService.php          (20K)
│   │   └── ConditionRiskService.php            (17K)
│   └── Views/
│       ├── index.blade.php                     (20K)
│       ├── show.blade.php                      (18K)
│       └── create.blade.php                    (17K)
│
└── Privacy/
    ├── Controllers/
    │   └── PrivacyComplianceController.php     (30K)
    ├── Database/
    │   └── PrivacyMigrations.php               (15K)
    ├── Routes/
    │   └── routes.php                          (4K)
    ├── Services/
    │   └── PrivacyComplianceService.php        (25K)
    └── Views/
        ├── index.blade.php                     (19K)
        ├── ropa/
        │   └── index.blade.php                 (9K)
        └── dsar/
            └── index.blade.php                 (12K)
```

## Security Extension

### Features
- **Security Classifications**: Hierarchical levels (Public → Top Secret)
- **User Clearances**: Grant/revoke clearances with expiry dates
- **Object Classification**: Classify archival descriptions with review dates
- **NARSSA Compliance**: Retention schedules with disposal authority references
- **POPIA Integration**: Lawful processing grounds (Section 11)
- **Audit Logging**: SHA256 hashed exports for integrity verification
- **Justification Templates**: PAIA sections (23, 34, 46, 70) templates

### Database Tables
- `security_access_condition_link` - Links classifications to ISAD access conditions
- `security_retention_schedule` - NARSSA disposal schedules
- `security_compliance_log` - Audit trail with integrity hashing
- `security_justification_template` - PAIA/POPIA aligned templates
- `security_request_justification` - Access justification records

### Routes
```
GET  /admin/security/compliance              - Dashboard
GET  /admin/security/compliance/report       - Full compliance report
GET  /admin/security/compliance/export       - Export (JSON/CSV)
GET  /admin/security/compliance/retention    - Retention schedules
GET  /admin/security/compliance/reviews      - Pending reviews
GET  /admin/security/compliance/declassification - Declassification schedule
GET  /admin/security/compliance/access-logs  - Access log viewer
GET  /admin/security/compliance/clearance-logs - Clearance log viewer
GET  /admin/security/compliance/justification-templates - Template management
```

## Condition Extension

### Features
- **Condition Events**: Structured condition checks and damage reports
- **Conservation History**: Treatment records linked to events
- **Risk Scoring**: Severity × damage multiplier (mould 1.5, pest 1.5, water 1.3)
- **Controlled Vocabularies**: 6 damage types, 5 severity levels, material types
- **IIIF Export**: Web Annotation + Presentation 3.0 manifests
- **Preservation Packages**: SHA256 checksums for archival integrity
- **Assessment Scheduling**: Frequency-based reminders

### Database Tables
- `condition_event` - Main condition assessment records
- `conservation_link` - Links events to conservation treatments
- `condition_vocabulary_term` - Controlled vocabulary terms
- `condition_assessment_schedule` - Scheduled assessment tracking
- `condition_conservation_treatment` - Spectrum 5.0 treatment records

### Routes
```
GET  /admin/condition                        - Dashboard
GET  /admin/condition/risk                   - Risk assessment view
GET  /admin/condition/vocabularies           - Vocabulary management
GET  /admin/condition/schedule               - Assessment schedule
GET  /{slug}/condition                       - Object condition history
GET  /{slug}/condition/new                   - New condition report
POST /{slug}/condition                       - Save condition report
GET  /{slug}/condition/export                - Export (JSON/IIIF)
GET  /{slug}/condition/treatment/new         - New treatment form
POST /{slug}/condition/treatment             - Save treatment
```

### Vocabulary Types
- Damage Types: physical, biological, chemical, water, fire, mould
- Severity Levels: none, minor, moderate, severe, critical
- Material Types: paper, textile, metal, organic, composite
- Location Zones: front, back, edges, corners

## Privacy Extension

### Features
- **ROPA Management**: Record of Processing Activities with DPIA tracking
- **DSAR Tracking**: 30-day deadline management with auto-reference generation
- **Breach Register**: Incident logging with regulator notification tracking
- **Template Library**: 8 categories of privacy document templates
- **Compliance Scoring**: 4-component score (ROPA, DSAR, Breach, DPIA)
- **Lawful Bases**: POPIA Section 11 conditions mapped

### Database Tables
- `privacy_processing_activity` - ROPA records with DPIA flags
- `privacy_dsar_request` - DSAR tracking (DSAR-YYYYMM-0001 format)
- `privacy_dsar_log` - DSAR activity audit trail
- `privacy_breach_incident` - Breach register (BRE-YYYY-0001 format)
- `privacy_template` - Document templates by category
- `privacy_consent_record` - Consent tracking

### Routes
```
GET  /admin/privacy                          - Dashboard with compliance score
GET  /admin/privacy/export                   - Export compliance report
GET  /admin/privacy/ropa                     - Processing activities list
POST /admin/privacy/ropa                     - Create processing activity
GET  /admin/privacy/dsar                     - DSAR list
POST /admin/privacy/dsar                     - Create DSAR
GET  /admin/privacy/breaches                 - Breach incidents
POST /admin/privacy/breaches                 - Report breach
GET  /admin/privacy/templates                - Template library
```

### DSAR Types (POPIA/GDPR)
- Access (POPIA S23 / GDPR Art.15)
- Rectification (POPIA S24 / GDPR Art.16)
- Erasure (POPIA S24 / GDPR Art.17)
- Restriction (GDPR Art.18)
- Portability (GDPR Art.20)
- Objection (POPIA S11(3) / GDPR Art.21)

## Installation

### 1. Copy Extensions
```bash
cp -r src/Extensions/{Security,Condition,Privacy} /usr/share/nginx/archive/atom-framework/src/Extensions/
```

### 2. Run Migrations
```php
use AtomExtensions\Extensions\Security\Database\SecurityMigrations;
use AtomExtensions\Extensions\Condition\Database\ConditionMigrations;
use AtomExtensions\Extensions\Privacy\Database\PrivacyMigrations;

SecurityMigrations::migrate();
ConditionMigrations::migrate();
PrivacyMigrations::migrate();
```

### 3. Register Routes
Add to your route registration file:
```php
$securityRoutes = require __DIR__ . '/Extensions/Security/Routes/routes.php';
$conditionRoutes = require __DIR__ . '/Extensions/Condition/Routes/routes.php';
$privacyRoutes = require __DIR__ . '/Extensions/Privacy/Routes/routes.php';

$securityRoutes($app);
$conditionRoutes($app);
$privacyRoutes($app);
```

### 4. Initialize Templates
```php
use AtomExtensions\Extensions\Privacy\Services\PrivacyComplianceService;
use AtomExtensions\Extensions\Security\Services\AccessJustificationService;
use AtomExtensions\Extensions\Condition\Services\ConditionVocabularyService;

PrivacyComplianceService::initializeDefaultTemplates();
AccessJustificationService::initializeDefaultTemplates();
ConditionVocabularyService::initializeDefaultVocabularies();
```

## Compliance Standards

### South African
- **POPIA** (Protection of Personal Information Act, 2013)
- **PAIA** (Promotion of Access to Information Act, 2000)
- **NARSSA** (National Archives and Records Service of South Africa)
- **GRAP 103** (Heritage asset accounting)

### International
- **GDPR** (General Data Protection Regulation - EU)
- **Spectrum 5.0** (Museum documentation procedures)
- **IIIF** (International Image Interoperability Framework)
- **ISAD(G)** (General International Standard Archival Description)

## Author

**Johan Pieterse**  
The Archive and Heritage Group  
johan@theahg.co.za

## Version

1.0.0 - December 2024
