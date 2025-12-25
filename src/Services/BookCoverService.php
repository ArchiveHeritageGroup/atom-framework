<?php

declare(strict_types=1);

namespace AtomFramework\Services;

class BookCoverService
{
    private const OPENLIBRARY_BASE = 'https://covers.openlibrary.org/b';
    public const SIZE_SMALL = 'S';
    public const SIZE_MEDIUM = 'M';
    public const SIZE_LARGE = 'L';
    private const PLACEHOLDER = '/plugins/arAHGThemeB5Plugin/images/no-cover.png';

    public static function getOpenLibraryUrl(string $isbn, string $size = self::SIZE_MEDIUM): string
    {
        $isbn = self::normalizeIsbn($isbn);
        return self::OPENLIBRARY_BASE . "/isbn/{$isbn}-{$size}.jpg";
    }

    public static function getAllSizes(string $isbn): array
    {
        $isbn = self::normalizeIsbn($isbn);
        return [
            'small' => self::OPENLIBRARY_BASE . "/isbn/{$isbn}-S.jpg",
            'medium' => self::OPENLIBRARY_BASE . "/isbn/{$isbn}-M.jpg",
            'large' => self::OPENLIBRARY_BASE . "/isbn/{$isbn}-L.jpg",
        ];
    }

    public static function getByOclc(string $oclc, string $size = self::SIZE_MEDIUM): string
    {
        return self::OPENLIBRARY_BASE . '/oclc/' . urlencode($oclc) . "-{$size}.jpg";
    }

    public static function imgTag(string $isbn, string $size = self::SIZE_MEDIUM, array $attributes = []): string
    {
        $isbn = self::normalizeIsbn($isbn);
        $url = self::getOpenLibraryUrl($isbn, $size);

        $attrs = array_merge([
            'src' => $url,
            'alt' => "Cover for ISBN {$isbn}",
            'class' => 'book-cover',
            'loading' => 'lazy',
            'onerror' => "this.onerror=null; this.src='" . self::PLACEHOLDER . "';",
        ], $attributes);

        $attrString = '';
        foreach ($attrs as $key => $value) {
            $attrString .= ' ' . htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
        }

        return "<img{$attrString}>";
    }

    private static function normalizeIsbn(string $isbn): string
    {
        return preg_replace('/[\s-]/', '', trim($isbn));
    }
}
