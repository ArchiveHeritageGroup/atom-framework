<?php

declare(strict_types=1);

namespace AtomExtensions\Services;

use Com\Tecnick\Barcode\Barcode;

/**
 * Barcode and QR code generation, rendered locally.
 *
 * Replaces the third-party image services the label screens used to point
 * <img src> at (barcodeapi.org and api.qrserver.com). Those transmitted the
 * record identifier and public URL to parties we do not control, produced
 * nothing without outbound internet, and are not in the CSP img-src allowance,
 * so they would break the moment CSP is enforced (#248). See #260.
 *
 * Output is returned as a data URI so a label is self-contained: it prints
 * correctly, survives the html2canvas PNG download, needs no extra request, and
 * works on an air-gapped deployment. data: is already permitted by img-src.
 *
 * Backed by tecnickcom/tc-lib-barcode. There is deliberately no fallback: a
 * barcode that renders but does not scan is worse than an obvious failure,
 * which is exactly what the previous hand-rolled fallback produced.
 */
class BarcodeService
{
    /** Linear barcode types. */
    public const TYPE_CODE128 = 'C128';
    public const TYPE_CODE39 = 'C39';
    public const TYPE_EAN13 = 'EAN13';
    public const TYPE_UPCA = 'UPCA';

    /** QR error-correction level: L, M, Q or H. H tolerates the most damage. */
    public const QR_ERROR_LEVEL = 'H';

    /**
     * Whether local generation is available.
     *
     * Should always be true - tc-lib-barcode is a hard dependency of
     * atom-framework - but callers rendering a label may prefer to omit the
     * image rather than fail the whole page.
     */
    public static function isAvailable(): bool
    {
        return class_exists(Barcode::class);
    }

    /**
     * A linear barcode as a PNG data URI.
     *
     * @param string $data    value to encode
     * @param array  $options height (px, default 50), width (module width,
     *                        default 2), type (default Code 128), padding
     *
     * @return string data:image/png;base64,... or '' if the value is empty
     */
    public static function barcodeDataUri(string $data, array $options = []): string
    {
        $data = trim($data);
        if ('' === $data) {
            return '';
        }

        $type = $options['type'] ?? self::TYPE_CODE128;
        $width = (int) ($options['width'] ?? 2);
        $height = (int) ($options['height'] ?? 50);
        $padding = (int) ($options['padding'] ?? 0);

        return self::render($type, $data, $width, $height, $padding);
    }

    /**
     * A QR code as a PNG data URI.
     *
     * @param string $data    value to encode, typically the record's public URL
     * @param array  $options size (module size, default 4), padding (default 2
     *                        modules - the quiet zone, without which many
     *                        scanners fail), error_level (L/M/Q/H)
     *
     * @return string data:image/png;base64,... or '' if the value is empty
     */
    public static function qrDataUri(string $data, array $options = []): string
    {
        $data = trim($data);
        if ('' === $data) {
            return '';
        }

        $size = (int) ($options['size'] ?? 4);
        $padding = (int) ($options['padding'] ?? 2);
        $level = strtoupper((string) ($options['error_level'] ?? self::QR_ERROR_LEVEL));

        if (!in_array($level, ['L', 'M', 'Q', 'H'], true)) {
            $level = self::QR_ERROR_LEVEL;
        }

        return self::render('QRCODE,'.$level, $data, $size, $size, $padding);
    }

    /**
     * Pre-render a set of values, keyed the same way as the input.
     *
     * Lets a template hand the browser every option a barcode-source dropdown
     * offers, so switching source is a local src swap rather than a network
     * request to an external service.
     *
     * @param array $values key => value to encode
     */
    public static function barcodeDataUriMap(array $values, array $options = []): array
    {
        $out = [];

        foreach ($values as $key => $value) {
            $out[$key] = self::barcodeDataUri((string) $value, $options);
        }

        return $out;
    }

    /**
     * Render through tc-lib-barcode.
     *
     * Negative width/height are tc-lib's convention for "absolute pixels per
     * module" rather than "total image size", which is what a label wants:
     * the symbol grows with the payload instead of being squashed to fit.
     */
    private static function render(string $type, string $data, int $width, int $height, int $padding): string
    {
        if (!self::isAvailable()) {
            return '';
        }

        try {
            $barcode = new Barcode();
            $obj = $barcode->getBarcodeObj(
                $type,
                $data,
                -abs($width),
                -abs($height),
                'black',
                array_fill(0, 4, $padding)
            );

            $png = $obj->getPngData();
        } catch (\Throwable $e) {
            // An unencodable value (for example letters in an EAN13) must not
            // take the page down with it.
            return '';
        }

        if (empty($png)) {
            return '';
        }

        return 'data:image/png;base64,'.base64_encode($png);
    }
}
