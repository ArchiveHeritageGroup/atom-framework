<?php

declare(strict_types=1);

namespace AtomFramework\Services;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Language Service.
 *
 * Handles language code conversion between:
 * - ISO 639-1 (2-letter): Used by AtoM (en, af, zu)
 * - ISO 639-2 (3-letter): Used by book APIs (eng, afr, zul)
 */
class LanguageService
{
    /**
     * AtoM Language taxonomy ID.
     */
    private const LANGUAGE_TAXONOMY_ID = 12;

    /**
     * ISO 639-2 (3-letter) to ISO 639-1 (2-letter) mapping.
     */
    private const ISO_639_2_TO_1 = [
        // Major world languages
        'eng' => 'en',
        'fre' => 'fr',
        'fra' => 'fr',
        'ger' => 'de',
        'deu' => 'de',
        'spa' => 'es',
        'ita' => 'it',
        'por' => 'pt',
        'rus' => 'ru',
        'chi' => 'zh',
        'zho' => 'zh',
        'jpn' => 'ja',
        'ara' => 'ar',
        'hin' => 'hi',
        'kor' => 'ko',
        'vie' => 'vi',
        'tha' => 'th',
        'tur' => 'tr',
        'pol' => 'pl',
        'ukr' => 'uk',
        'nld' => 'nl',
        'dut' => 'nl',
        'swe' => 'sv',
        'nor' => 'no',
        'dan' => 'da',
        'fin' => 'fi',
        'gre' => 'el',
        'ell' => 'el',
        'heb' => 'he',
        'per' => 'fa',
        'fas' => 'fa',
        'ind' => 'id',
        'msa' => 'ms',
        'may' => 'ms',
        'ben' => 'bn',
        'urd' => 'ur',
        'tam' => 'ta',
        'tel' => 'te',
        'mar' => 'mr',
        'guj' => 'gu',
        'pan' => 'pa',
        'lat' => 'la',

        // South African languages
        'afr' => 'af',
        'zul' => 'zu',
        'xho' => 'xh',
        'nso' => 'nso',  // No 2-letter code, use 3-letter
        'sot' => 'st',
        'tsn' => 'tn',
        'ven' => 've',
        'tso' => 'ts',
        'ssw' => 'ss',
        'nbl' => 'nr',
        'nde' => 'nd',

        // Other African languages
        'swa' => 'sw',
        'hau' => 'ha',
        'yor' => 'yo',
        'ibo' => 'ig',
        'amh' => 'am',
        'orm' => 'om',
        'som' => 'so',

        // Celtic languages
        'gle' => 'ga',
        'cym' => 'cy',
        'wel' => 'cy',
        'gla' => 'gd',
        'bre' => 'br',

        // European languages
        'cat' => 'ca',
        'eus' => 'eu',
        'baq' => 'eu',
        'glg' => 'gl',
        'ron' => 'ro',
        'rum' => 'ro',
        'hun' => 'hu',
        'ces' => 'cs',
        'cze' => 'cs',
        'slk' => 'sk',
        'slo' => 'sk',
        'slv' => 'sl',
        'hrv' => 'hr',
        'srp' => 'sr',
        'bos' => 'bs',
        'mkd' => 'mk',
        'mac' => 'mk',
        'bul' => 'bg',
        'lit' => 'lt',
        'lav' => 'lv',
        'est' => 'et',
        'bel' => 'be',
        'kat' => 'ka',
        'geo' => 'ka',
        'hye' => 'hy',
        'arm' => 'hy',
        'aze' => 'az',
        'kaz' => 'kk',
        'uzb' => 'uz',
        'tgk' => 'tg',
        'kir' => 'ky',
        'mon' => 'mn',
        'nep' => 'ne',
        'sin' => 'si',
        'mya' => 'my',
        'bur' => 'my',
        'khm' => 'km',
        'lao' => 'lo',
        'tgl' => 'tl',
        'fil' => 'tl',
        'jav' => 'jv',
        'sun' => 'su',
    ];

    /**
     * ISO 639-1 (2-letter) to language name mapping.
     * These should match AtoM's language taxonomy names.
     */
    private const ISO_639_1_TO_NAME = [
        'en' => 'English',
        'fr' => 'French',
        'de' => 'German',
        'es' => 'Spanish',
        'it' => 'Italian',
        'pt' => 'Portuguese',
        'ru' => 'Russian',
        'zh' => 'Chinese',
        'ja' => 'Japanese',
        'ar' => 'Arabic',
        'hi' => 'Hindi',
        'ko' => 'Korean',
        'vi' => 'Vietnamese',
        'th' => 'Thai',
        'tr' => 'Turkish',
        'pl' => 'Polish',
        'uk' => 'Ukrainian',
        'nl' => 'Dutch',
        'sv' => 'Swedish',
        'no' => 'Norwegian',
        'da' => 'Danish',
        'fi' => 'Finnish',
        'el' => 'Greek',
        'he' => 'Hebrew',
        'fa' => 'Persian',
        'id' => 'Indonesian',
        'ms' => 'Malay',
        'bn' => 'Bengali',
        'ur' => 'Urdu',
        'ta' => 'Tamil',
        'te' => 'Telugu',
        'mr' => 'Marathi',
        'gu' => 'Gujarati',
        'pa' => 'Punjabi',
        'la' => 'Latin',

        // South African languages
        'af' => 'Afrikaans',
        'zu' => 'Zulu',
        'xh' => 'Xhosa',
        'nso' => 'Northern Sotho',
        'st' => 'Southern Sotho',
        'tn' => 'Tswana',
        've' => 'Venda',
        'ts' => 'Tsonga',
        'ss' => 'Swati',
        'nr' => 'South Ndebele',
        'nd' => 'North Ndebele',

        // Other African
        'sw' => 'Swahili',
        'ha' => 'Hausa',
        'yo' => 'Yoruba',
        'ig' => 'Igbo',
        'am' => 'Amharic',
        'om' => 'Oromo',
        'so' => 'Somali',

        // Celtic
        'ga' => 'Irish',
        'cy' => 'Welsh',
        'gd' => 'Scottish Gaelic',
        'br' => 'Breton',

        // European
        'ca' => 'Catalan',
        'eu' => 'Basque',
        'gl' => 'Galician',
        'ro' => 'Romanian',
        'hu' => 'Hungarian',
        'cs' => 'Czech',
        'sk' => 'Slovak',
        'sl' => 'Slovenian',
        'hr' => 'Croatian',
        'sr' => 'Serbian',
        'bs' => 'Bosnian',
        'mk' => 'Macedonian',
        'bg' => 'Bulgarian',
        'lt' => 'Lithuanian',
        'lv' => 'Latvian',
        'et' => 'Estonian',
        'be' => 'Belarusian',
        'ka' => 'Georgian',
        'hy' => 'Armenian',
        'az' => 'Azerbaijani',
        'kk' => 'Kazakh',
        'uz' => 'Uzbek',
        'tg' => 'Tajik',
        'ky' => 'Kyrgyz',
        'mn' => 'Mongolian',
        'ne' => 'Nepali',
        'si' => 'Sinhala',
        'my' => 'Burmese',
        'km' => 'Khmer',
        'lo' => 'Lao',
        'tl' => 'Tagalog',
        'jv' => 'Javanese',
        'su' => 'Sundanese',
    ];

    /**
     * Convert ISO 639-2 (3-letter) to ISO 639-1 (2-letter).
     */
    public static function iso639_2to1(string $code): string
    {
        $code = strtolower(trim($code));

        // Already 2-letter
        if (strlen($code) === 2) {
            return $code;
        }

        return self::ISO_639_2_TO_1[$code] ?? $code;
    }

    /**
     * Convert ISO 639-1 (2-letter) to ISO 639-2 (3-letter).
     */
    public static function iso639_1to2(string $code): string
    {
        $code = strtolower(trim($code));

        // Already 3-letter
        if (strlen($code) === 3) {
            return $code;
        }

        // Reverse lookup
        $reversed = array_flip(self::ISO_639_2_TO_1);

        return $reversed[$code] ?? $code;
    }

    /**
     * Get language name from any ISO code (2 or 3 letter).
     */
    public static function getNameFromCode(string $code, string $culture = 'en'): string
    {
        $code = strtolower(trim($code));

        // Convert 3-letter to 2-letter
        if (strlen($code) === 3) {
            $code = self::iso639_2to1($code);
        }

        // Try database first
        $term = self::findByCode($code, $culture);
        if ($term) {
            return $term->name;
        }

        // Fallback to static mapping
        return self::ISO_639_1_TO_NAME[$code] ?? strtoupper($code);
    }

    /**
     * Alias for getNameFromCode.
     */
    public static function getNameFromIsoCode(string $code, string $culture = 'en'): string
    {
        return self::getNameFromCode($code, $culture);
    }

    /**
     * Get all languages, as {id, name, culture} sorted by name.
     *
     * The `id` is the ISO 639-1 code, not a term id. AtoM does not hold
     * languages as taxonomy terms at all: its own taxonomy ids begin at 30
     * (QubitTaxonomy::ROOT_ID), so LANGUAGE_TAXONOMY_ID = 12 names a taxonomy
     * that exists on no instance, and the query this replaces returned an empty
     * collection everywhere - PSIS and archaeology both measured at zero rows on
     * 2026-08-24. The visible symptom was the Language preference select on the
     * authority record contact form offering nothing but "Select...", reported
     * by Stefan du Toit.
     *
     * Codes also match what the consumers store: ahgContactPlugin's
     * `contact_information_extended.language_preference` is varchar(16), and the
     * one value in it on PSIS is an ISO code. The previous shape would have
     * written a numeric term id into that column had it ever returned a row.
     *
     * ⚠️ findByCode(), findByName() and getTermIdFromCode() still query the same
     * absent taxonomy and so always return null. Nothing calls them today; they
     * need the same treatment before anything does.
     *
     * @param string $culture reserved - the ISO names are English for now, and
     *                        an unknown culture must not empty the list
     */
    public static function getAll(string $culture = 'en'): Collection
    {
        $languages = [];

        foreach (self::ISO_639_1_TO_NAME as $code => $name) {
            $languages[] = (object) [
                'id' => $code,
                'name' => $name,
                'culture' => $culture,
            ];
        }

        usort($languages, static function ($a, $b) {
            return strcasecmp($a->name, $b->name);
        });

        return new Collection($languages);
    }

    /**
     * Find language term by ISO 639-1 code.
     *
     * AtoM stores languages with 2-letter codes in the term name or as the culture.
     */
    public static function findByCode(string $code, string $culture = 'en'): ?object
    {
        $code = strtolower(trim($code));

        // Convert 3-letter to 2-letter if needed
        if (strlen($code) === 3) {
            $code = self::iso639_2to1($code);
        }

        // Get the expected name for this code
        $expectedName = self::ISO_639_1_TO_NAME[$code] ?? null;

        if (!$expectedName) {
            return null;
        }

        return DB::table('term as t')
            ->join('term_i18n as ti', 't.id', '=', 'ti.id')
            ->where('t.taxonomy_id', self::LANGUAGE_TAXONOMY_ID)
            ->where('ti.culture', $culture)
            ->where(function ($query) use ($expectedName, $code) {
                $query->where('ti.name', $expectedName)
                    ->orWhere('ti.name', 'LIKE', $expectedName . '%')
                    ->orWhere('ti.name', $code);
            })
            ->select(['t.id', 'ti.name'])
            ->first();
    }

    /**
     * Find language term by name.
     */
    public static function findByName(string $name, string $culture = 'en'): ?object
    {
        return DB::table('term as t')
            ->join('term_i18n as ti', 't.id', '=', 'ti.id')
            ->where('t.taxonomy_id', self::LANGUAGE_TAXONOMY_ID)
            ->where('ti.culture', $culture)
            ->where(function ($query) use ($name) {
                $query->where('ti.name', $name)
                    ->orWhere('ti.name', 'LIKE', $name . '%');
            })
            ->select(['t.id', 'ti.name'])
            ->first();
    }

    /**
     * Get term ID for a language code.
     */
    public static function getTermIdFromCode(string $code, string $culture = 'en'): ?int
    {
        $term = self::findByCode($code, $culture);

        return $term ? (int) $term->id : null;
    }

    /**
     * Get language code from name.
     */
    public static function getCodeFromName(string $name): ?string
    {
        $name = trim($name);
        $reversed = array_flip(self::ISO_639_1_TO_NAME);

        // Exact match
        if (isset($reversed[$name])) {
            return $reversed[$name];
        }

        // Case-insensitive search
        foreach (self::ISO_639_1_TO_NAME as $code => $langName) {
            if (strcasecmp($langName, $name) === 0) {
                return $code;
            }
        }

        return null;
    }
}
