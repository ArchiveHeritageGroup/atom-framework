# Python/JS Architecture for AHG Plugins

This document defines how AHG plugins should integrate with Python and JavaScript services.

## Overview

```
┌────────────────────────────────────────────────────────────────────────────┐
│                              AtoM (PHP)                                     │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────────────────┐ │
│  │  ahgAIPlugin    │  │ ahgThemeB5Plugin│  │   Other Plugins             │ │
│  │  (NER, Spellcheck)  (UI Components) │  │                             │ │
│  └────────┬────────┘  └────────┬────────┘  └──────────────┬──────────────┘ │
│           │                    │                          │                │
│           ▼                    ▼                          ▼                │
│  ┌────────────────────────────────────────────────────────────────────────┐│
│  │                    atom-ahg SDK (Python)                               ││
│  │  - Lightweight client (httpx, pydantic)                                ││
│  │  - API wrappers for AtoM resources                                     ││
│  │  - NO heavy ML dependencies                                            ││
│  └────────────────────────────────────────────────────────────────────────┘│
└─────────────────────────────────────────────────────────────────────────────┘
                                    │
            ┌───────────────────────┼───────────────────────┐
            ▼                       ▼                       ▼
┌───────────────────┐  ┌───────────────────┐  ┌───────────────────┐
│ ahg-translation   │  │ ahg-ner-service   │  │ ahg-ocr-service   │
│     Server        │  │    (spaCy)        │  │  (Tesseract)      │
│  (OPUS-MT/Argos)  │  │                   │  │                   │
│  Port: 5100       │  │  Port: 5200       │  │  Port: 5300       │
└───────────────────┘  └───────────────────┘  └───────────────────┘
```

## Repository Structure

### 1. atom-ahg (Python SDK) - LIGHTWEIGHT
```
atom-ahg/
├── src/atom_ahg/
│   ├── client.py           # API client (httpx)
│   ├── models.py           # Pydantic models
│   ├── resources/          # API resource wrappers
│   │   ├── descriptions.py
│   │   ├── translation.py  # Calls translation service
│   │   └── ...
│   └── config.py
├── pyproject.toml
└── requirements.txt
```

**Dependencies** (kept minimal):
```
httpx>=0.25.0
pydantic>=2.0.0
python-dateutil>=2.8.0
```

### 2. ahg-translation-server (HEAVY - Separate)
```
ahg-translation-server/
├── src/
│   ├── server.py           # Flask app
│   ├── opus_mt.py          # OPUS-MT translation
│   └── argos_translate.py  # Argos Translate (alternative)
├── Dockerfile
├── docker-compose.yml
├── requirements.txt
└── systemd/
    └── ahg-translation.service
```

**Dependencies** (heavy, isolated):
```
flask>=2.0.0
transformers>=4.0.0
torch>=2.0.0
sentencepiece>=0.1.99
# OR for Argos:
argostranslate>=1.9.0
```

### 3. ahg-ner-server (HEAVY - Separate)
```
ahg-ner-server/
├── src/
│   ├── server.py           # Flask app
│   ├── extract.py          # Entity extraction
│   └── models/             # Custom NER models
├── Dockerfile
└── requirements.txt
```

**Dependencies**:
```
flask>=2.0.0
spacy>=3.7.0
en_core_web_sm (or lg/trf)
```

## How Plugins Call Python Services

### Pattern 1: Direct HTTP Call (Preferred)

PHP plugins call Python services via HTTP:

```php
// In ahgAIPlugin/lib/Services/TranslationService.php

class TranslationService
{
    private string $serviceUrl;

    public function __construct()
    {
        // Get service URL from settings
        $this->serviceUrl = sfConfig::get('app_translation_server_url', 'http://localhost:5100');
    }

    public function translate(string $text, string $source, string $target): ?string
    {
        $ch = curl_init($this->serviceUrl . '/translate');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'text' => $text,
                'source' => $source,
                'target' => $target,
            ]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            return null;
        }

        $data = json_decode($response, true);
        return $data['translatedText'] ?? null;
    }
}
```

### Pattern 2: CLI Subprocess (For Batch Jobs)

For Symfony tasks/jobs that process many records:

```php
// In ahgAIPlugin/lib/task/aiTranslateTask.class.php

class aiTranslateTask extends sfBaseTask
{
    protected function execute($arguments = [], $options = [])
    {
        // Use Python SDK for batch operations
        $pythonScript = sfConfig::get('sf_plugins_dir')
            . '/ahgAIPlugin/lib/python/batch_translate.py';

        $command = sprintf(
            'python3 %s --input %s --source %s --target %s --output %s',
            escapeshellarg($pythonScript),
            escapeshellarg($inputFile),
            escapeshellarg($source),
            escapeshellarg($target),
            escapeshellarg($outputFile)
        );

        exec($command, $output, $returnCode);

        if ($returnCode !== 0) {
            throw new RuntimeException('Translation batch failed');
        }
    }
}
```

### Pattern 3: Job Queue (For Long-Running Tasks)

```php
// Dispatch to Gearman/Redis queue
$job = new TranslationJob([
    'record_id' => $id,
    'source' => 'en',
    'target' => 'af',
]);
$this->dispatcher->dispatch($job);
```

## JavaScript Integration

### Pattern 1: NPM Package (atom-client-js)

```
atom-client-js/
├── src/
│   ├── client.ts
│   ├── resources/
│   │   ├── descriptions.ts
│   │   └── search.ts
│   └── index.ts
├── package.json
└── tsconfig.json
```

### Pattern 2: Bundled Assets in Theme

For frontend JavaScript, bundle into theme:

```javascript
// ahgThemeB5Plugin/js/src/translation.js
export async function translateField(text, source, target) {
    const response = await fetch('/api/translate', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ text, source, target }),
    });
    return response.json();
}
```

### Pattern 3: MCP Integration (For AI Features)

For Claude Code / AI assistant integration:

```json
// .mcp.json
{
    "servers": {
        "ahg-translation": {
            "url": "http://localhost:5100",
            "tools": ["translate", "detect_language"]
        }
    }
}
```

## Deployment

### Systemd Services

Each Python service runs as a separate systemd unit:

```ini
# /etc/systemd/system/ahg-translation.service
[Unit]
Description=AHG Translation Server
After=network.target

[Service]
Type=simple
User=www-data
WorkingDirectory=/opt/ahg-services/translation
ExecStart=/opt/ahg-services/translation/venv/bin/python -m server
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
```

### Docker Alternative

```yaml
# docker-compose.yml
services:
  translation:
    build: ./ahg-translation-server
    ports:
      - "5100:5100"
    volumes:
      - translation-cache:/root/.cache/huggingface
    deploy:
      resources:
        limits:
          memory: 4G

  ner:
    build: ./ahg-ner-server
    ports:
      - "5200:5200"

volumes:
  translation-cache:
```

## Configuration

### AtoM Settings (apps/qubit/config/config.php)

```php
// Python service URLs
$app['ai_translation_url'] = 'http://localhost:5100';
$app['ai_ner_url'] = 'http://localhost:5200';
$app['ai_ocr_url'] = 'http://localhost:5300';

// Feature toggles
$app['ai_translation_enabled'] = true;
$app['ai_ner_enabled'] = true;
```

### Plugin Access

```php
// In any plugin
$translationUrl = sfConfig::get('app_ai_translation_url', 'http://localhost:5100');
```

## Summary

| Component | Location | Dependencies | Runs As |
|-----------|----------|--------------|---------|
| atom-ahg SDK | atom-ahg-python/ | httpx, pydantic | Library |
| Translation Server | ahg-translation-server/ | Flask, transformers/torch | systemd/docker |
| NER Server | ahg-ner-server/ | Flask, spaCy | systemd/docker |
| OCR Server | ahg-ocr-server/ | Flask, pytesseract | systemd/docker |
| JS Client | atom-client-js/ | fetch | Browser/Node |

## Best Practices

1. **Keep SDK lightweight** - No ML libraries in atom-ahg
2. **HTTP-first** - Plugins call services via HTTP, not subprocess
3. **Health checks** - All services expose /health endpoint
4. **Graceful degradation** - If service unavailable, feature disabled
5. **Configurable URLs** - Service URLs in sfConfig, not hardcoded
6. **Separate venvs** - Each service has its own Python environment
7. **Docker for ML** - Heavy models run in containers with GPU support
