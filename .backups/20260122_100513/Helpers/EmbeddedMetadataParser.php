<?php

declare(strict_types=1);

namespace AtomFramework\Helpers;

/**
 * Embedded Metadata Parser - Extract EXIF, IPTC, and XMP from files
 */
class EmbeddedMetadataParser
{
    /**
     * Extract all metadata from a file
     */
    public static function extract(string $filePath): ?array
    {
        if (!file_exists($filePath)) {
            return null;
        }

        $out = [];

        // EXIF
        $out = array_merge($out, self::extractExif($filePath));

        // IPTC
        $out = array_merge($out, self::extractIptc($filePath));

        // XMP
        $out = array_merge($out, self::extractXmp($filePath));

        return !empty($out) ? $out : null;
    }

    /**
     * Extract EXIF metadata
     */
    public static function extractExif(string $filePath): array
    {
        $out = [];

        try {
            $exif = @exif_read_data($filePath, null, true, false);
            if (is_array($exif)) {
                foreach ($exif as $section => $data) {
                    if (is_array($data)) {
                        foreach ($data as $key => $val) {
                            $out["EXIF.{$section}.{$key}"] = self::formatValue($val);
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore errors
        }

        return $out;
    }

    /**
     * Extract IPTC metadata
     */
    public static function extractIptc(string $filePath): array
    {
        $out = [];
        $info = [];

        @getimagesize($filePath, $info);
        
        if (!empty($info['APP13'])) {
            $iptc = @iptcparse($info['APP13']);
            if (is_array($iptc)) {
                $iptcTags = self::getIptcTagNames();
                foreach ($iptc as $tag => $vals) {
                    $tagName = $iptcTags[$tag] ?? $tag;
                    $out["IPTC.{$tagName}"] = implode(', ', $vals);
                }
            }
        }

        return $out;
    }

    /**
     * Extract XMP metadata
     */
    public static function extractXmp(string $filePath): array
    {
        $out = [];

        try {
            $content = @file_get_contents($filePath);
            if ($content === false) {
                return $out;
            }

            // Find XMP packet
            $start = strpos($content, '<x:xmpmeta');
            $end = strpos($content, '</x:xmpmeta>');

            if ($start !== false && $end !== false) {
                $xmpData = substr($content, $start, $end - $start + 12);
                
                // Parse simple XMP elements
                if (preg_match_all('/<(dc|xmp|photoshop|tiff|exif):([^>]+)>([^<]*)<\/\1:\2>/i', $xmpData, $matches, PREG_SET_ORDER)) {
                    foreach ($matches as $match) {
                        $namespace = strtoupper($match[1]);
                        $tag = $match[2];
                        $value = trim($match[3]);
                        if (!empty($value)) {
                            $out["XMP.{$namespace}.{$tag}"] = $value;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            // Ignore errors
        }

        return $out;
    }

    /**
     * Format metadata value for storage
     */
    protected static function formatValue($val): string
    {
        if (is_array($val)) {
            return implode(', ', array_map([self::class, 'formatValue'], $val));
        }

        if (is_bool($val)) {
            return $val ? 'true' : 'false';
        }

        return (string) $val;
    }

    /**
     * Get human-readable IPTC tag names
     */
    protected static function getIptcTagNames(): array
    {
        return [
            '2#005' => 'ObjectName',
            '2#010' => 'Urgency',
            '2#015' => 'Category',
            '2#020' => 'Subcategories',
            '2#025' => 'Keywords',
            '2#040' => 'SpecialInstructions',
            '2#055' => 'DateCreated',
            '2#060' => 'TimeCreated',
            '2#080' => 'Byline',
            '2#085' => 'BylineTitle',
            '2#090' => 'City',
            '2#092' => 'Sublocation',
            '2#095' => 'ProvinceState',
            '2#100' => 'CountryCode',
            '2#101' => 'CountryName',
            '2#103' => 'OriginalTransmissionReference',
            '2#105' => 'Headline',
            '2#110' => 'Credit',
            '2#115' => 'Source',
            '2#116' => 'CopyrightNotice',
            '2#118' => 'Contact',
            '2#120' => 'Caption',
            '2#122' => 'CaptionWriter',
        ];
    }

    /**
     * Get specific metadata value by key pattern
     */
    public static function getValue(array $metadata, string $pattern): ?string
    {
        foreach ($metadata as $key => $value) {
            if (stripos($key, $pattern) !== false) {
                return $value;
            }
        }
        return null;
    }

    /**
     * Get all values matching a pattern
     */
    public static function getValues(array $metadata, string $pattern): array
    {
        $results = [];
        foreach ($metadata as $key => $value) {
            if (stripos($key, $pattern) !== false) {
                $results[$key] = $value;
            }
        }
        return $results;
    }
}
