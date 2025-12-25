<?php

declare(strict_types=1);

namespace AtomFramework\Services;

/**
 * Maps ISBN metadata to AtoM information object fields.
 */
class IsbnMetadataMapper
{
    private const FIELD_MAP = [
        'title' => ['table' => 'information_object_i18n', 'field' => 'title', 'type' => 'string'],
        'authors' => ['table' => 'relation', 'field' => 'creator', 'type' => 'relation', 'entity_type' => 'QubitActor'],
        'publishers' => ['table' => 'relation', 'field' => 'publisher', 'type' => 'relation', 'entity_type' => 'QubitActor'],
        'publish_date' => ['table' => 'event', 'field' => 'date', 'type' => 'event', 'event_type' => 'publication'],
        'number_of_pages' => ['table' => 'information_object_i18n', 'field' => 'extentAndMedium', 'type' => 'string', 'format' => '{value} pages'],
        'subjects' => ['table' => 'object_term_relation', 'field' => 'subject', 'type' => 'term', 'taxonomy' => 'subjects'],
        'description' => ['table' => 'information_object_i18n', 'field' => 'scopeAndContent', 'type' => 'string'],
        'language' => ['table' => 'object_term_relation', 'field' => 'language', 'type' => 'term', 'taxonomy' => 'languages'],
    ];

    public function mapToAtom(array $metadata): array
    {
        $result = [
            'fields' => [],
            'relations' => [],
            'events' => [],
            'notes' => [],
            'terms' => [],
            'properties' => [],
        ];

        foreach ($metadata as $key => $value) {
            if (empty($value) || !isset(self::FIELD_MAP[$key])) {
                continue;
            }

            $mapping = self::FIELD_MAP[$key];

            switch ($mapping['type']) {
                case 'string':
                    $result['fields'][$mapping['field']] = $this->formatValue($value, $mapping);
                    break;

                case 'relation':
                    $values = is_array($value) ? $value : [$value];
                    foreach ($values as $v) {
                        $result['relations'][] = [
                            'type' => $mapping['field'],
                            'entity_type' => $mapping['entity_type'],
                            'name' => $this->cleanName($v),
                        ];
                    }
                    break;

                case 'event':
                    $result['events'][] = [
                        'type' => $mapping['event_type'],
                        'field' => $mapping['field'],
                        'value' => $value,
                    ];
                    break;

                case 'term':
                    $values = is_array($value) ? $value : [$value];
                    foreach ($values as $v) {
                        $termValue = $v;

                        // Handle language ISO codes
                        if ($mapping['taxonomy'] === 'languages' && strlen($v) <= 3) {
                            $termValue = LanguageService::getNameFromIsoCode($v);
                        }

                        $result['terms'][] = [
                            'taxonomy' => $mapping['taxonomy'],
                            'term' => $this->cleanTerm($termValue),
                        ];
                    }
                    break;
            }
        }

        return $result;
    }

    public function getPreviewData(array $metadata): array
    {
        $preview = [];

        if (!empty($metadata['title'])) {
            $title = $metadata['title'];
            if (!empty($metadata['subtitle'])) {
                $title .= ': ' . $metadata['subtitle'];
            }
            $preview['title'] = $title;
        }

        if (!empty($metadata['authors'])) {
            $preview['creators'] = is_array($metadata['authors'])
                ? implode('; ', array_map([$this, 'cleanName'], $metadata['authors']))
                : $this->cleanName($metadata['authors']);
        }

        $pubParts = [];
        if (!empty($metadata['publishers'])) {
            $pubs = is_array($metadata['publishers']) ? $metadata['publishers'] : [$metadata['publishers']];
            $pubParts[] = $this->cleanName($pubs[0]);
        }
        if (!empty($metadata['publish_date'])) {
            $pubParts[] = $metadata['publish_date'];
        }
        if ($pubParts) {
            $preview['publication'] = implode(', ', $pubParts);
        }

        if (!empty($metadata['number_of_pages'])) {
            $preview['extent'] = $metadata['number_of_pages'] . ' pages';
        }

        if (!empty($metadata['subjects'])) {
            $subjects = is_array($metadata['subjects']) ? $metadata['subjects'] : [$metadata['subjects']];
            $preview['subjects'] = array_slice($subjects, 0, 5);
        }

        $preview['identifiers'] = [];
        if (!empty($metadata['isbn_13'])) {
            $preview['identifiers']['ISBN-13'] = $metadata['isbn_13'];
        }
        if (!empty($metadata['isbn_10'])) {
            $preview['identifiers']['ISBN-10'] = $metadata['isbn_10'];
        }

        // Language - convert ISO code to name from database
        if (!empty($metadata['language'])) {
            $lang = $metadata['language'];
            if (strlen($lang) <= 3) {
                $preview['language'] = LanguageService::getNameFromIsoCode($lang);
            } else {
                $preview['language'] = $lang;
            }
        }

        // Cover URL
        if (!empty($metadata['isbn_13']) || !empty($metadata['isbn_10'])) {
            $isbn = $metadata['isbn_13'] ?? $metadata['isbn_10'];
            $preview['cover_url'] = "https://covers.openlibrary.org/b/isbn/{$isbn}-M.jpg";
        } elseif (!empty($metadata['cover_url'])) {
            $preview['cover_url'] = $metadata['cover_url'];
        }

        if (!empty($metadata['description'])) {
            $preview['description'] = $this->truncate($metadata['description'], 500);
        }

        return $preview;
    }

    private function formatValue($value, array $mapping): string
    {
        if (isset($mapping['format'])) {
            return str_replace('{value}', (string) $value, $mapping['format']);
        }
        return is_array($value) ? implode('; ', $value) : (string) $value;
    }

    private function cleanName(string $name): string
    {
        $name = rtrim($name, '.,;:');
        $name = preg_replace('/\s*\(\d{4}-?\d{0,4}\)/', '', $name);
        return trim(preg_replace('/\s+/', ' ', $name));
    }

    private function cleanTerm(string $term): string
    {
        return trim(ucfirst(strtolower(rtrim($term, '.'))));
    }

    private function truncate(string $text, int $length): string
    {
        if (strlen($text) <= $length) {
            return $text;
        }
        return substr($text, 0, $length - 3) . '...';
    }
}
