<div align="center">

# 🏛️ AtoM Extensions

**Extending Access to Memory for Modern Archives**

[![AtoM Version](https://img.shields.io/badge/AtoM-2.8.x--2.10.x-blue.svg)](https://www.accesstomemory.org/)
[![PHP Version](https://img.shields.io/badge/PHP-8.3-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL--3.0-green.svg)](LICENSE)
[![Extensions](https://img.shields.io/badge/Extensions-19+-orange.svg)](#-available-extensions)
[![Bootstrap](https://img.shields.io/badge/Bootstrap-5.3-7952b3.svg)](https://getbootstrap.com/)
[![Laravel](https://img.shields.io/badge/Laravel-Query%20Builder-red.svg)](https://laravel.com/)

**The most comprehensive extension suite for AtoM archival software**

[Installation](#-installation) • [Feature Comparison](#-feature-comparison) • [Extensions](#-available-extensions) • [Compliance](#-compliance-support) • [Documentation](#-documentation)

---

**Built by [The Archive And Heritage Digital Commons Group](https://theahg.co.za)**

</div>

---

## 🎯 What is AtoM?

**AtoM (Access to Memory)** is an open-source, web-based archival description application developed by Artefactual Systems. It's trusted by national archives, universities, and cultural institutions worldwide.

---

## 📊 Feature Comparison

### Base AtoM Features (Native)

| Feature | Description | Status |
|---------|-------------|:------:|
| **Archival Description** | | |
| ISAD(G) Templates | International Standard Archival Description | ✅ |
| RAD Templates | Rules for Archival Description (Canadian) | ✅ |
| DACS Templates | Describing Archives: A Content Standard (US) | ✅ |
| Dublin Core Templates | Simple metadata standard | ✅ |
| MODS Templates | Metadata Object Description Schema | ✅ |
| Multi-level Descriptions | Fonds → Series → Files → Items | ✅ |
| Hierarchical Browse | Tree-view navigation | ✅ |
| | | |
| **Authority Records** | | |
| ISAAR(CPF) | Corporate bodies, Persons, Families | ✅ |
| Actor Relations | Relationships between authorities | ✅ |
| Maintenance History | Track changes to authorities | ✅ |
| | | |
| **Archival Institutions** | | |
| ISDIAH | Repository descriptions | ✅ |
| Contact Information | Address, phone, email, hours | ✅ |
| Holdings Statistics | Collection counts | ✅ |
| | | |
| **Functions** | | |
| ISDF | Functions/activities descriptions | ✅ |
| Function Relations | Link functions to records | ✅ |
| | | |
| **Access Points** | | |
| Subject Terms | Controlled vocabulary subjects | ✅ |
| Place Terms | Geographic access points | ✅ |
| Name Access Points | Link to authority records | ✅ |
| Genre Terms | Material types | ✅ |
| | | |
| **Digital Objects** | | |
| File Upload | Images, PDFs, audio, video | ✅ |
| Thumbnail Generation | Automatic derivatives | ✅ |
| Compound Objects | Multi-page documents | ✅ |
| External Links | URI references | ✅ |
| | | |
| **Import/Export** | | |
| CSV Import | Bulk data loading | ✅ |
| EAD 2002 Import/Export | Encoded Archival Description | ✅ |
| EAD3 Export | Latest EAD version | ✅ |
| Dublin Core Export | Simple XML | ✅ |
| MODS Export | Library metadata | ✅ |
| SKOS Export | Taxonomy terms | ✅ |
| Clipboard Export | Select and export | ✅ |
| | | |
| **Search & Discovery** | | |
| Elasticsearch | Full-text search | ✅ |
| Faceted Search | Filter by repository, date, subject | ✅ |
| Advanced Search | Boolean operators | ✅ |
| Browse by Repository | Institution grouping | ✅ |
| | | |
| **User Management** | | |
| User Accounts | Login/registration | ✅ |
| User Groups | Role-based permissions | ✅ |
| LDAP Integration | Enterprise authentication | ✅ |
| Two-Factor Auth | OTP security | ✅ |
| | | |
| **Administration** | | |
| Taxonomy Management | Edit controlled vocabularies | ✅ |
| Static Pages | Custom content pages | ✅ |
| Theming | Customize appearance | ✅ |
| Multi-language | Interface translation | ✅ |
| OAI-PMH | Harvesting protocol | ✅ |
| Accessions | Acquisition records | ✅ |
| Deaccessions | Disposal records | ✅ |
| Physical Storage | Location tracking | ✅ |
| Jobs | Background processing | ✅ |

---

### AtoM Extensions Adds

| Feature | Base AtoM | + Extensions |
|---------|:---------:|:------------:|
| **User Interface** | | |
| Modern Bootstrap 5 UI | ❌ | ✅ |
| Responsive Mobile Design | Partial | ✅ Full |
| Dark Mode Support | ❌ | ✅ |
| Drag-and-Drop Landing Pages | ❌ | ✅ |
| Display Profile System | ❌ | ✅ |
| | | |
| **Architecture** | | |
| Laravel Query Builder | ❌ | ✅ |
| Extension Manager CLI | ❌ | ✅ |
| Database-driven Plugin System | ❌ | ✅ |
| PHP 8.3 Optimized | Partial | ✅ Full |
| | | |
| **Security & Compliance** | | |
| Security Classification (5 levels) | ❌ | ✅ |
| GDPR Compliance Tools | ❌ | ✅ |
| POPIA Compliance Tools | ❌ | ✅ |
| CCPA Compliance Tools | ❌ | ✅ |
| PIPEDA Compliance Tools | ❌ | ✅ |
| LGPD Compliance Tools | ❌ | ✅ |
| PAIA Request Management | ❌ | ✅ |
| DSAR Management | ❌ | ✅ |
| Data Breach Register | ❌ | ✅ |
| Consent Management | ❌ | ✅ |
| Comprehensive Audit Trail | ❌ | ✅ |
| | | |
| **GLAM Sector Support** | | |
| Archives (ISAD/EAD) | ✅ | ✅ Enhanced |
| Libraries (RDA/MARC) | Partial | ✅ Full |
| Museums (Spectrum 5.0) | ❌ | ✅ |
| Museums (CCO) | ❌ | ✅ |
| Galleries (CCO/CDWA) | ❌ | ✅ |
| Digital Asset Management | ❌ | ✅ |
| Unified GLAM Browse | ❌ | ✅ |
| | | |
| **Heritage & Finance** | | |
| GRAP 103 Heritage Accounting | ❌ | ✅ |
| IPSAS 17 (International) | ❌ | ✅ |
| FRS 102 (UK) | ❌ | ✅ |
| GASB 34 (USA) | ❌ | ✅ |
| FASAB (USA Federal) | ❌ | ✅ |
| AASB 116 (Australia) | ❌ | ✅ |
| PSAB/PS 3150 (Canada) | ❌ | ✅ |
| Asset Valuation & Depreciation | ❌ | ✅ |
| Insurance Management | ❌ | ✅ |
| 10 Accounting Standards | ❌ | ✅ |
| | | |
| **Research & Access** | | |
| Research Portal | ❌ | ✅ |
| Researcher Workspace | ❌ | ✅ |
| Reading Room Booking | ❌ | ✅ |
| Access Request Workflow | ❌ | ✅ |
| Embargo Management | ❌ | ✅ |
| | | |
| **Collection Management** | | |
| Donor Agreement Tracking | ❌ | ✅ |
| Donor Reminders | ❌ | ✅ |
| Condition Assessment | ❌ | ✅ |
| Conservation Tracking | ❌ | ✅ |
| Condition Annotator | ❌ | ✅ |
| Provenance Research | ❌ | ✅ |
| Vendor/Supplier Management | ❌ | ✅ |
| | | |
| **Advanced Features** | | |
| IIIF Deep Zoom Viewer | ❌ | ✅ |
| 3D Model Viewer | ❌ | ✅ |
| 3D Thumbnail Generation | ❌ | ✅ |
| Records in Contexts (RiC) | ❌ | ✅ |
| Graph Visualization | ❌ | ✅ |
| AI Entity Extraction (NER) | ❌ | ✅ |
| Automated Backups | ❌ | ✅ |
| TIFF to PDF/A Merge | ❌ | ✅ |
| Metadata Extraction (EXIF/IPTC/XMP) | ❌ | ✅ |

---

### Why Both Together?

**AtoM** = Rock-solid archival foundation trusted by national archives, universities, and cultural institutions worldwide.

**AtoM Extensions** = Enterprise features, multi-sector support, regulatory compliance, modern UI, and AI capabilities.

---

## 📊 Architecture
```
┌─────────────────────────────────────────────────────────────────┐
│                     AtoM 2.10 BASE (Symfony 1.x)                │
├─────────────────────────────────────────────────────────────────┤
│  LAYER 1: atom-framework (REQUIRED)                             │
│  ├── Laravel Query Builder (Illuminate\Database)                │
│  ├── Extension Manager CLI                                      │
│  ├── Base Repository/Service Classes                            │
│  └── Helper Utilities                                           │
├─────────────────────────────────────────────────────────────────┤
│  LAYER 2: atom-ahg-plugins                                      │
│  ├── ahgThemeB5Plugin (Required - Locked)                       │
│  ├── ahgSecurityClearancePlugin (Required - Locked)             │
│  └── [Optional Plugins...]                                      │
├─────────────────────────────────────────────────────────────────┤
│  LAYER 3: Provenance AI (Optional - API)                        │
│  └── AI-powered entity extraction for archivists                │
└─────────────────────────────────────────────────────────────────┘
```

---

## ⚡ Installation

### Prerequisites

| Component | Minimum | Recommended |
|-----------|---------|-------------|
| **AtoM** | 2.8.0 | 2.10.x |
| **PHP** | 8.2 | 8.3 |
| **MySQL** | 8.0 | 8.0+ |
| **Elasticsearch** | 5.x | 7.10 (AtoM 2.10; ES 8.x not supported) |
| **OS** | Ubuntu 20.04 | Ubuntu 22.04 LTS |

### Quick Start
```bash
# Download and run installer
curl -fsSL https://raw.githubusercontent.com/ArchiveHeritageGroup/atom-framework/main/bin/ahg-installer.sh -o ahg-installer.sh
chmod +x ahg-installer.sh
sudo ./ahg-installer.sh
```

📖 **[See INSTALLATION.md for all installation options →](INSTALLATION.md)**

---

## 📦 Available Extensions

### Required (Core - Locked)

| Extension | Description |
|-----------|-------------|
| **ahgThemeB5Plugin** | Bootstrap 5 responsive theme |
| **ahgSecurityClearancePlugin** | Security classification (Public to Top Secret) |

### GLAM Sector Plugins

| Extension | Description | Standards |
|-----------|-------------|-----------|
| **ahgLibraryPlugin** | Library cataloging | RDA, MARC21 |
| **ahgMuseumPlugin** | Museum cataloging | CCO, Spectrum 5.0 |
| **arGalleryPlugin** | Visual arts/gallery | CCO, CDWA |
| **ahgDAMPlugin** | Digital Asset Management | Dublin Core, IPTC |

### Research & Access

| Extension | Description |
|-----------|-------------|
| **ahgResearchPlugin** | Researcher portal, workspace, reading room booking |
| **ahgAccessRequestPlugin** | Access request workflow management |

### Compliance & Governance

| Extension | Description |
|-----------|-------------|
| **ahgPrivacyPlugin** | Multi-jurisdiction privacy (GDPR, POPIA, CCPA, PIPEDA, LGPD) |
| **ahgHeritageAccountingPlugin** | Heritage asset accounting (10 standards) |
| **ahgExtendedRightsPlugin** | Rights statements & embargo management |

### Utilities

| Extension | Description |
|-----------|-------------|
| **ahgBackupPlugin** | Automated backup & restore |
| **ahgAuditTrailPlugin** | Comprehensive audit logging |
| **ahgDisplayPlugin** | Display profiles & viewing modes |
| **ahgDonorAgreementPlugin** | Donor agreements with reminders |
| **ahgVendorPlugin** | Vendor/supplier management |
| **ahgConditionPlugin** | Condition assessment & conservation |
| **ahgLandingPagePlugin** | Drag-and-drop landing page builder |

### AI & Advanced Features

| Extension | Description |
|-----------|-------------|
| **ahgNerPlugin** | Named Entity Recognition |
| **ahg3DModelPlugin** | 3D model viewer & thumbnails |
| **IiifViewerFramework** | IIIF deep zoom image viewer |
| **ahgRicExplorerPlugin** | Records in Contexts graph viewer |

---

## 🌍 Compliance Support

### Privacy Regulations

| Jurisdiction | Regulation | Status |
|--------------|------------|--------|
| 🇿🇦 South Africa | POPIA, PAIA, NARSSA | ✅ Full |
| 🇪🇺 European Union | GDPR | ✅ Full |
| 🇺🇸 California | CCPA | ✅ Full |
| 🇨🇦 Canada | PIPEDA | ✅ Full |
| 🇧🇷 Brazil | LGPD | ✅ Full |
| 🇳🇬 Nigeria | NDPA | ✅ Full |
| 🇰🇪 Kenya | DPA | ✅ Full |

### Heritage Accounting Standards

| Standard | Region |
|----------|--------|
| **GRAP 103** | South Africa |
| **IPSAS 17** | International |
| **FRS 102** | United Kingdom |
| **FASAB** | USA Federal |
| **GASB 34** | USA State/Local |
| **AASB 116** | Australia |
| **PSAB/PS 3150** | Canada |
| **NZ PBE IPSAS 17** | New Zealand |
| **mGAAP** | Germany |
| **Custom** | Configurable |

---

## 🔧 CLI Reference
```bash
# Extension Management
php bin/atom extension:discover          # List available
php bin/atom extension:enable <name>     # Enable
php bin/atom extension:disable <name>    # Disable

# Framework Management
php bin/atom framework:version           # Version
php bin/atom update                      # Update
php bin/atom migrate run                 # Migrations

# Maintenance
php bin/atom backup:create               # Backup
php bin/atom cc                          # Clear cache
php bin/atom help                        # Help
```

---

## 🤖 Provenance AI

AI-powered entity extraction for archivists:

- **People** - Names, roles, relationships
- **Places** - Locations, coordinates
- **Organizations** - Companies, institutions
- **Dates** - Events, periods

[Learn more →](https://theahg.co.za/provenance-ai)

---

## 📖 Documentation

| Document | Description |
|----------|-------------|
| **[INSTALLATION.md](INSTALLATION.md)** | Complete installation guide |
| [docs/](docs/) | User guides for each plugin |
| [CHANGELOG.md](CHANGELOG.md) | Version history |

---

## 🏢 About

Developed by **The Archive And Heritage Digital Commons Group**

| | |
|---|---|
| 🌐 Website | [theahg.co.za](https://theahg.co.za) |
| 📧 Email | [info@theahg.co.za](mailto:info@theahg.co.za) |
| 🐙 GitHub | [ArchiveHeritageGroup](https://github.com/ArchiveHeritageGroup) |
| 📦 Releases | [Download](https://github.com/ArchiveHeritageGroup/atom-framework/releases) |

---

## 📄 License

GPL-3.0 - see [LICENSE](LICENSE) for details.

---

<div align="center">

**Made with ❤️ for the archival community**

[![Powered by AtoM](https://img.shields.io/badge/Powered%20by-AtoM-blue.svg)](https://www.accesstomemory.org/)
[![by TAHDCG](https://img.shields.io/badge/by-TAHDCG-green.svg)](https://theahg.co.za)

</div>
