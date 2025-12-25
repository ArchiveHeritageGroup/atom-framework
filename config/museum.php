<?php

return [
    /*
    |--------------------------------------------------------------------------
    | CCO Work Types
    |--------------------------------------------------------------------------
    |
    | Defines the CCO (Cataloging Cultural Objects) work types and their
    | required and optional fields.
    |
    */
    'work_types' => [
        'visual_works' => [
            'label' => 'Visual Works',
            'description' => 'Paintings, drawings, prints, photographs, sculptures',
            'required_fields' => [
                'title',
                'work_type',
                'creator',
                'creation_date',
                'materials',
            ],
            'optional_fields' => [
                'techniques',
                'measurements',
                'inscriptions',
                'condition',
                'provenance',
                'subject',
                'style_period',
            ],
        ],
        'built_works' => [
            'label' => 'Built Works',
            'description' => 'Architecture, monuments, gardens, landscapes',
            'required_fields' => [
                'title',
                'work_type',
                'location',
                'creation_date',
                'materials',
            ],
            'optional_fields' => [
                'creator',
                'techniques',
                'measurements',
                'condition',
                'style_period',
                'cultural_context',
            ],
        ],
        'movable_works' => [
            'label' => 'Movable Works',
            'description' => 'Decorative arts, tools, vessels, textiles, specimens',
            'required_fields' => [
                'title',
                'work_type',
                'creation_date',
                'materials',
            ],
            'optional_fields' => [
                'creator',
                'techniques',
                'measurements',
                'inscriptions',
                'condition',
                'function',
                'cultural_context',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Measurement Configuration
    |--------------------------------------------------------------------------
    */
    'measurements' => [
        'default_unit_system' => 'metric', // 'metric' or 'imperial'
        'allowed_types' => [
            'height',
            'width',
            'depth',
            'diameter',
            'circumference',
            'length',
            'weight',
            'thickness',
        ],
        'units' => [
            'length' => ['mm', 'cm', 'm', 'in', 'ft'],
            'weight' => ['g', 'kg', 'oz', 'lb'],
        ],
        'precision' => 2, // Decimal places
    ],

    /*
    |--------------------------------------------------------------------------
    | Validation Configuration
    |--------------------------------------------------------------------------
    */
    'validation' => [
        'strict_mode' => false, // If true, all required fields must be present
        'allow_custom_work_types' => true,
        'allow_custom_materials' => false,
        'allow_custom_techniques' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Export Configuration
    |--------------------------------------------------------------------------
    */
    'export' => [
        'lido' => [
            'version' => '1.0',
            'language' => 'en',
            'include_rights' => true,
        ],
        'cco_xml' => [
            'version' => '1.0',
            'include_schema_location' => true,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    'logging' => [
        'channels' => [
            'museum_metadata' => [
                'path' => '/var/log/atom/museum_metadata.log',
                'level' => 'info',
            ],
            'cco_validation' => [
                'path' => '/var/log/atom/cco_validation.log',
                'level' => 'warning',
            ],
            'object_enrichment' => [
                'path' => '/var/log/atom/object_enrichment.log',
                'level' => 'info',
            ],
        ],
    ],
];