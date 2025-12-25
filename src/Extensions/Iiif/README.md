# IIIF Presentation API 3.0 Extension

Part of atom-framework for AtoM archival management system.

## Features

- **IIIF Presentation API 3.0** compliant manifests
- **Backward compatible** with IIIF 2.1 (use `?format=2`)
- **Object manifests** - Generate manifests for AtoM information objects
- **Collection manifests** - Generate manifests for curated IIIF collections
- **Image manifests** - Direct image manifests via Cantaloupe
- **Multi-page support** - Automatic page detection for TIFF files
- **3D integration** - Links to ar3DModelPlugin IIIF 3D manifests

## Directory Structure

```
/atom-framework/src/Extensions/Iiif/
├── Controllers/
│   └── IiifController.php      # Request handling
├── Services/
│   └── IiifManifestService.php # Manifest generation
├── public/
│   ├── iiif-routes.php         # Entry point
│   └── mirador/                # Mirador 3 viewer files
├── nginx-iiif.conf             # Nginx configuration
└── README.md                   # This file
```

## Installation

### 1. Copy Extension to Framework

```bash
cp -r src/Extensions/Iiif /usr/share/nginx/archive/atom-framework/src/Extensions/
```

### 2. Add Nginx Routes

Add the contents of `nginx-iiif.conf` to your server block:

```bash
sudo nano /etc/nginx/sites-available/archives.theahg.co.za

# Add the location blocks from nginx-iiif.conf

sudo nginx -t
sudo systemctl reload nginx
```

### 3. Download Mirador 3 (Optional)

For full IIIF 3.0 viewer support:

```bash
cd /usr/share/nginx/archive/atom-framework/src/Extensions/Iiif/public/mirador

# Download Mirador 3
wget https://unpkg.com/mirador@latest/dist/mirador.min.js
wget https://unpkg.com/mirador@latest/dist/mirador.min.css
```

### 4. Clear Cache

```bash
php /usr/share/nginx/archive/symfony cc
```

## API Endpoints

| Endpoint | Description |
|----------|-------------|
| `GET /iiif/manifest/{slug}` | Object manifest (IIIF 3.0) |
| `GET /iiif/collection/{slug}` | Collection manifest (IIIF 3.0) |
| `GET /iiif/list/{slug}` | List available manifests |
| `GET /iiif-manifest.php?id={id}` | Image manifest (IIIF 3.0) |
| `GET /iiif-manifest.php?id={id}&format=2` | Image manifest (IIIF 2.1) |

## Response Headers

All endpoints return:

```
Content-Type: application/ld+json;profile="http://iiif.io/api/presentation/3/context.json"
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, OPTIONS
```

## Example Manifests

### Object Manifest (3.0)

```json
{
  "@context": "http://iiif.io/api/presentation/3/context.json",
  "id": "https://archives.theahg.co.za/iiif/manifest/my-object",
  "type": "Manifest",
  "label": { "en": ["My Object Title"] },
  "items": [
    {
      "id": "https://archives.theahg.co.za/iiif/canvas/123",
      "type": "Canvas",
      "width": 4000,
      "height": 3000,
      "items": [...]
    }
  ]
}
```

### Collection Manifest (3.0)

```json
{
  "@context": "http://iiif.io/api/presentation/3/context.json",
  "id": "https://archives.theahg.co.za/iiif/collection/my-collection",
  "type": "Collection",
  "label": { "en": ["My Collection"] },
  "items": [
    {
      "id": "https://archives.theahg.co.za/iiif/manifest/object-1",
      "type": "Manifest",
      "label": { "en": ["Object 1"] }
    }
  ]
}
```

## IIIF 2.1 → 3.0 Changes

| 2.1 | 3.0 |
|-----|-----|
| `@id` | `id` |
| `@type` | `type` |
| `"label": "Title"` | `"label": {"en": ["Title"]}` |
| `sequences[].canvases[]` | `items[]` (direct) |
| `images[]` | `items[].items[]` (AnnotationPage) |
| `resource` | `body` |
| `on` | `target` |
| `description` | `summary` |
| `viewingHint` | `behavior` |
| `license` | `rights` |
| `attribution` | `requiredStatement` |

## Integration with Helper

Update `informationObjectHelper.php` to use IIIF 3.0 manifests:

```php
// In IIIF section, update manifest URL:
if ($resourceSlug) {
    $manifestUrl = $baseUrl . '/iiif/manifest/' . $resourceSlug;
} else {
    $manifestUrl = $baseUrl . '/iiif-manifest.php?id=' . urlencode($iiifIdentifier);
}
```

## Viewer Compatibility

| Viewer | IIIF 2.1 | IIIF 3.0 |
|--------|----------|----------|
| OpenSeadragon | ✅ | ✅ |
| Mirador 2 | ✅ | ❌ |
| Mirador 3 | ✅ | ✅ |
| Universal Viewer | ✅ | ✅ |

**Recommendation:** Use Mirador 3 for full IIIF 3.0 support.

## Validation

Test your manifests with the IIIF Validator:
https://presentation-validator.iiif.io/

## Logging

Logs are written to `/var/log/atom/iiif-manifest.log` with 30-day rotation.

## Author

Johan Pieterse - The Archive and Heritage Group
