<?php

declare(strict_types=1);

namespace AtomFramework\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * GLAM Identifier Service
 *
 * Handles identifier management across all GLAM sectors.
 * NO slug-based identifiers for barcode generation.
 */
class GlamIdentifierService
{
    public const TYPE_ISBN13 = 'isbn13';
    public const TYPE_ISBN10 = 'isbn10';
    public const TYPE_ISSN = 'issn';
    public const TYPE_LCCN = 'lccn';
    public const TYPE_DOI = 'doi';
    public const TYPE_REFERENCE_CODE = 'reference_code';
    public const TYPE_IDENTIFIER = 'identifier';
    public const TYPE_ACCESSION = 'accession_number';
    public const TYPE_OBJECT_NUMBER = 'object_number';
    public const TYPE_ARTWORK_ID = 'artwork_id';
    public const TYPE_CATALOGUE_NUMBER = 'catalogue_number';
    public const TYPE_ASSET_ID = 'asset_id';
    public const TYPE_BARCODE = 'barcode';

    public const SECTOR_LIBRARY = 'library';
    public const SECTOR_ARCHIVE = 'archive';
    public const SECTOR_MUSEUM = 'museum';
    public const SECTOR_GALLERY = 'gallery';
    public const SECTOR_DAM = 'dam';

    private array $sectorIdentifiers = [
        self::SECTOR_LIBRARY => [
            self::TYPE_ISBN13 => ['label' => 'ISBN-13', 'icon' => 'barcode', 'primary' => true],
            self::TYPE_ISBN10 => ['label' => 'ISBN-10', 'icon' => 'barcode', 'primary' => false],
            self::TYPE_ISSN => ['label' => 'ISSN', 'icon' => 'newspaper', 'primary' => false],
            self::TYPE_LCCN => ['label' => 'LCCN', 'icon' => 'building-columns', 'primary' => false],
            self::TYPE_DOI => ['label' => 'DOI', 'icon' => 'link', 'primary' => false],
            self::TYPE_BARCODE => ['label' => 'Barcode', 'icon' => 'qrcode', 'primary' => false],
        ],
        self::SECTOR_ARCHIVE => [
            self::TYPE_REFERENCE_CODE => ['label' => 'Reference Code', 'icon' => 'folder-tree', 'primary' => true],
            self::TYPE_IDENTIFIER => ['label' => 'Identifier', 'icon' => 'hashtag', 'primary' => false],
            self::TYPE_BARCODE => ['label' => 'Barcode', 'icon' => 'qrcode', 'primary' => false],
        ],
        self::SECTOR_MUSEUM => [
            self::TYPE_ACCESSION => ['label' => 'Accession Number', 'icon' => 'stamp', 'primary' => true],
            self::TYPE_OBJECT_NUMBER => ['label' => 'Object Number', 'icon' => 'cube', 'primary' => false],
            self::TYPE_BARCODE => ['label' => 'Barcode', 'icon' => 'qrcode', 'primary' => false],
        ],
        self::SECTOR_GALLERY => [
            self::TYPE_ARTWORK_ID => ['label' => 'Artwork ID', 'icon' => 'palette', 'primary' => true],
            self::TYPE_CATALOGUE_NUMBER => ['label' => 'Catalogue Number', 'icon' => 'book', 'primary' => false],
            self::TYPE_BARCODE => ['label' => 'Barcode', 'icon' => 'qrcode', 'primary' => false],
        ],
        self::SECTOR_DAM => [
            self::TYPE_ASSET_ID => ['label' => 'Asset ID', 'icon' => 'photo-film', 'primary' => true],
            self::TYPE_IDENTIFIER => ['label' => 'Identifier', 'icon' => 'hashtag', 'primary' => false],
            self::TYPE_BARCODE => ['label' => 'Barcode', 'icon' => 'qrcode', 'primary' => false],
        ],
    ];

    public function getIdentifierTypesForSector(string $sector): array
    {
        return $this->sectorIdentifiers[$sector] ?? $this->sectorIdentifiers[self::SECTOR_ARCHIVE];
    }

    public function getPrimaryIdentifierType(string $sector): string
    {
        $types = $this->sectorIdentifiers[$sector] ?? [];
        foreach ($types as $type => $config) {
            if ($config['primary'] ?? false) {
                return $type;
            }
        }

        return self::TYPE_IDENTIFIER;
    }

    public function validateIdentifier(string $value, string $type): array
    {
        return match ($type) {
            self::TYPE_ISBN13 => $this->validateIsbn13($value),
            self::TYPE_ISBN10 => $this->validateIsbn10($value),
            self::TYPE_ISSN => $this->validateIssn($value),
            self::TYPE_DOI => $this->validateDoi($value),
            default => [
                'valid' => !empty(trim($value)),
                'message' => !empty(trim($value)) ? 'Valid' : 'Cannot be empty',
                'normalized' => trim($value),
            ]
        };
    }

    public function validateIsbn13(string $isbn): array
    {
        $result = ['valid' => false, 'message' => '', 'normalized' => ''];
        $clean = preg_replace('/[\s-]/', '', $isbn);
        $result['normalized'] = $clean;

        if (strlen($clean) !== 13 || !ctype_digit($clean)) {
            $result['message'] = 'ISBN-13 must be exactly 13 digits';

            return $result;
        }

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $clean[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        $checkDigit = (10 - ($sum % 10)) % 10;

        if ((int) $clean[12] !== $checkDigit) {
            $result['message'] = 'Invalid ISBN-13 check digit';

            return $result;
        }

        $result['valid'] = true;
        $result['message'] = 'Valid ISBN-13';

        return $result;
    }

    public function validateIsbn10(string $isbn): array
    {
        $result = ['valid' => false, 'message' => '', 'normalized' => ''];
        $clean = preg_replace('/[\s-]/', '', strtoupper($isbn));
        $result['normalized'] = $clean;

        if (strlen($clean) !== 10 || !preg_match('/^[0-9]{9}[0-9X]$/', $clean)) {
            $result['message'] = 'ISBN-10 must be 9 digits + check digit';

            return $result;
        }

        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += (int) $clean[$i] * (10 - $i);
        }
        $lastValue = $clean[9] === 'X' ? 10 : (int) $clean[9];
        $sum += $lastValue;

        if ($sum % 11 !== 0) {
            $result['message'] = 'Invalid ISBN-10 check digit';

            return $result;
        }

        $result['valid'] = true;
        $result['message'] = 'Valid ISBN-10';

        return $result;
    }

    public function validateIssn(string $issn): array
    {
        $result = ['valid' => false, 'message' => '', 'normalized' => ''];
        $clean = preg_replace('/[\s-]/', '', strtoupper($issn));
        $result['normalized'] = substr($clean, 0, 4) . '-' . substr($clean, 4);

        if (strlen($clean) !== 8 || !preg_match('/^[0-9]{7}[0-9X]$/', $clean)) {
            $result['message'] = 'ISSN must be 8 characters (NNNN-NNNC)';

            return $result;
        }

        $sum = 0;
        for ($i = 0; $i < 7; $i++) {
            $sum += (int) $clean[$i] * (8 - $i);
        }
        $lastValue = $clean[7] === 'X' ? 10 : (int) $clean[7];
        $checkDigit = (11 - ($sum % 11)) % 11;

        if ($lastValue !== $checkDigit) {
            $result['message'] = 'Invalid ISSN check digit';

            return $result;
        }

        $result['valid'] = true;
        $result['message'] = 'Valid ISSN';

        return $result;
    }

    public function validateDoi(string $doi): array
    {
        $result = ['valid' => false, 'message' => '', 'normalized' => ''];
        $clean = preg_replace('/^(https?:\/\/)?(dx\.)?doi\.org\//', '', trim($doi));
        $clean = preg_replace('/^doi:\s*/i', '', $clean);
        $result['normalized'] = $clean;

        if (!preg_match('/^10\.\d{4,}\/\S+$/', $clean)) {
            $result['message'] = 'DOI must be in format 10.XXXX/identifier';

            return $result;
        }

        $result['valid'] = true;
        $result['message'] = 'Valid DOI';

        return $result;
    }

    public function convertIsbn10ToIsbn13(string $isbn10): ?string
    {
        $validation = $this->validateIsbn10($isbn10);
        if (!$validation['valid']) {
            return null;
        }

        $clean = $validation['normalized'];
        $base = '978' . substr($clean, 0, 9);

        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int) $base[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        $checkDigit = (10 - ($sum % 10)) % 10;

        return $base . $checkDigit;
    }

    public function detectIdentifierType(string $value): ?string
    {
        $clean = preg_replace('/[\s-]/', '', $value);

        if (preg_match('/^97[89]\d{10}$/', $clean)) {
            return self::TYPE_ISBN13;
        }
        if (preg_match('/^\d{9}[\dX]$/i', $clean) && strlen($clean) === 10) {
            return self::TYPE_ISBN10;
        }
        if (preg_match('/^\d{7}[\dX]$/i', $clean) && strlen($clean) === 8) {
            return self::TYPE_ISSN;
        }
        if (preg_match('/^10\.\d{4,}\//', $value)) {
            return self::TYPE_DOI;
        }
        if (preg_match('/^\d{4}\.\d{3}\.\d+$/', $value)) {
            return self::TYPE_ACCESSION;
        }

        return null;
    }

    public function detectObjectSector(int $objectId): string
    {
        $config = DB::table('display_object_config')
            ->where('object_id', $objectId)
            ->value('object_type');

        if ($config) {
            return $config;
        }

        if (DB::table('library_item')->where('object_id', $objectId)->exists()) {
            return self::SECTOR_LIBRARY;
        }
        if (DB::table('museum_object')->where('object_id', $objectId)->exists()) {
            return self::SECTOR_MUSEUM;
        }
        if (DB::table('gallery_artwork')->where('object_id', $objectId)->exists()) {
            return self::SECTOR_GALLERY;
        }
        if (DB::table('dam_asset')->where('object_id', $objectId)->exists()) {
            return self::SECTOR_DAM;
        }

        return self::SECTOR_ARCHIVE;
    }

    public function getBestBarcodeIdentifier(int $objectId, ?string $sector = null): ?array
    {
        $object = DB::table('information_object AS io')
            ->leftJoin('information_object_i18n AS i18n', function ($join) {
                $join->on('io.id', '=', 'i18n.id')->where('i18n.culture', '=', 'en');
            })
            ->where('io.id', $objectId)
            ->first(['io.*', 'i18n.title']);

        if (!$object) {
            return null;
        }

        if (!$sector) {
            $sector = $this->detectObjectSector($objectId);
        }

        $priorityOrder = $this->getIdentifierPriority($sector);

        foreach ($priorityOrder as $type) {
            $value = $this->getIdentifierValue($objectId, $type);
            if (!empty($value)) {
                return [
                    'type' => $type,
                    'value' => $value,
                    'label' => $this->sectorIdentifiers[$sector][$type]['label'] ?? $type,
                    'sector' => $sector,
                ];
            }
        }

        if (!empty($object->identifier)) {
            return [
                'type' => self::TYPE_IDENTIFIER,
                'value' => $object->identifier,
                'label' => 'Identifier',
                'sector' => $sector,
            ];
        }

        return null;
    }

    private function getIdentifierPriority(string $sector): array
    {
        return match ($sector) {
            self::SECTOR_LIBRARY => [
                self::TYPE_ISBN13, self::TYPE_ISBN10, self::TYPE_ISSN,
                self::TYPE_BARCODE, self::TYPE_LCCN, self::TYPE_DOI, self::TYPE_IDENTIFIER,
            ],
            self::SECTOR_ARCHIVE => [
                self::TYPE_REFERENCE_CODE, self::TYPE_IDENTIFIER, self::TYPE_BARCODE,
            ],
            self::SECTOR_MUSEUM => [
                self::TYPE_ACCESSION, self::TYPE_OBJECT_NUMBER, self::TYPE_BARCODE, self::TYPE_IDENTIFIER,
            ],
            self::SECTOR_GALLERY => [
                self::TYPE_ARTWORK_ID, self::TYPE_CATALOGUE_NUMBER, self::TYPE_BARCODE, self::TYPE_IDENTIFIER,
            ],
            self::SECTOR_DAM => [
                self::TYPE_ASSET_ID, self::TYPE_IDENTIFIER, self::TYPE_BARCODE,
            ],
            default => [self::TYPE_IDENTIFIER, self::TYPE_BARCODE]
        };
    }

    private function getIdentifierValue(int $objectId, string $type): ?string
    {
        return match ($type) {
            self::TYPE_ISBN13, self::TYPE_ISBN10 => DB::table('library_item')
                ->where('object_id', $objectId)->value('isbn'),
            self::TYPE_ISSN => DB::table('library_item')
                ->where('object_id', $objectId)->value('issn'),
            self::TYPE_LCCN => DB::table('library_item')
                ->where('object_id', $objectId)->value('lccn'),
            self::TYPE_DOI => DB::table('library_item')
                ->where('object_id', $objectId)->value('doi'),
            self::TYPE_BARCODE => DB::table('library_item')
                ->where('object_id', $objectId)->value('barcode')
                ?? DB::table('museum_object')->where('object_id', $objectId)->value('barcode'),
            self::TYPE_ACCESSION => DB::table('museum_object')
                ->where('object_id', $objectId)->value('accession_number'),
            self::TYPE_OBJECT_NUMBER => DB::table('museum_object')
                ->where('object_id', $objectId)->value('object_number'),
            self::TYPE_IDENTIFIER, self::TYPE_REFERENCE_CODE => DB::table('information_object')
                ->where('id', $objectId)->value('identifier'),
            default => null
        };
    }
}
