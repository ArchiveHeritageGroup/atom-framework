<?php

/**
 * External Services Configuration
 *
 * Centralized configuration for external API endpoints and services.
 * Plugin-specific configurations should extend or override these defaults.
 *
 * Override in app.yml or environment variables as needed.
 */

return [
    // Getty Vocabularies (AAT, TGN, ULAN)
    'getty' => [
        'sparql_endpoint' => env('GETTY_SPARQL_ENDPOINT', 'http://vocab.getty.edu/sparql'),
        'timeout' => 30,
        'namespaces' => [
            'aat' => 'http://vocab.getty.edu/aat/',
            'tgn' => 'http://vocab.getty.edu/tgn/',
            'ulan' => 'http://vocab.getty.edu/ulan/',
        ],
    ],

    // Wikidata (future integration)
    'wikidata' => [
        'sparql_endpoint' => env('WIKIDATA_SPARQL_ENDPOINT', 'https://query.wikidata.org/sparql'),
        'entity_base' => 'https://www.wikidata.org/entity/',
        'timeout' => 30,
    ],

    // VIAF (Virtual International Authority File)
    'viaf' => [
        'search_endpoint' => env('VIAF_SEARCH_ENDPOINT', 'https://viaf.org/viaf/search'),
        'autosuggest_endpoint' => 'https://viaf.org/viaf/AutoSuggest',
        'timeout' => 30,
    ],

    // WorldCat
    'worldcat' => [
        'base_url' => env('WORLDCAT_BASE_URL', 'https://www.worldcat.org'),
        'api_url' => env('WORLDCAT_API_URL', 'https://www.worldcat.org/webservices'),
        'timeout' => 30,
    ],

    // Open Library (ISBN lookup)
    'openlibrary' => [
        'api_url' => env('OPENLIBRARY_API_URL', 'https://openlibrary.org'),
        'covers_url' => 'https://covers.openlibrary.org',
        'timeout' => 15,
    ],

    // IIIF
    'iiif' => [
        'image_api_version' => '3',
        'presentation_api_version' => '3',
        'validator_url' => env('IIIF_VALIDATOR_URL', 'https://iiif.io/api/presentation/validator/'),
    ],

    // CDN Resources (can be self-hosted alternatives)
    'cdn' => [
        'd3js' => env('CDN_D3JS', 'https://d3js.org/d3.v7.min.js'),
        'leaflet' => env('CDN_LEAFLET', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js'),
        'leaflet_css' => env('CDN_LEAFLET_CSS', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css'),
        'model_viewer' => env('CDN_MODEL_VIEWER', 'https://ajax.googleapis.com/ajax/libs/model-viewer/3.3.0/model-viewer.min.js'),
    ],

    // Rights & Licensing
    'rights' => [
        'rightsstatements_org' => 'https://rightsstatements.org/vocab/',
        'creativecommons_org' => 'https://creativecommons.org/licenses/',
        'local_contexts' => 'https://localcontexts.org',
    ],

    // Linked Data / Ontologies
    'ontologies' => [
        'schema_org' => 'http://schema.org/',
        'dcterms' => 'http://purl.org/dc/terms/',
        'skos' => 'http://www.w3.org/2004/02/skos/core#',
        'foaf' => 'http://xmlns.com/foaf/0.1/',
        'edm' => 'http://www.europeana.eu/schemas/edm/',
        'cidoc_crm' => 'http://www.cidoc-crm.org/cidoc-crm/',
        'ric_o' => 'https://www.ica.org/standards/RiC/ontology#',
    ],

    // AI Services (configured separately with API keys)
    'ai' => [
        'openai_endpoint' => env('OPENAI_ENDPOINT', 'https://api.openai.com/v1'),
        'anthropic_endpoint' => env('ANTHROPIC_ENDPOINT', 'https://api.anthropic.com/v1'),
    ],

    // Payment Gateways
    'payment' => [
        'payfast' => [
            'sandbox_url' => 'https://sandbox.payfast.co.za/eng/process',
            'live_url' => 'https://www.payfast.co.za/eng/process',
        ],
        'paygate' => [
            'sandbox_url' => 'https://secure.paygate.co.za/PayHost/process.trans',
            'live_url' => 'https://secure.paygate.co.za/PayHost/process.trans',
        ],
    ],

    // South African Regulatory URLs
    'regulatory_za' => [
        'information_regulator' => 'https://www.justice.gov.za/inforeg/',
        'national_archives' => 'https://www.nationalarchives.gov.za/',
    ],
];
