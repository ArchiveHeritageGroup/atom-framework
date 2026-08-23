<?php

declare(strict_types=1);

namespace AtomExtensions\Services;

/**
 * Remove embedded location metadata from image files.
 *
 * WHY THIS EXISTS
 *
 * A photograph carries its own coordinate in EXIF, and that coordinate does not
 * go through any access control. An instance can coarsen a site's position to
 * ~11 km through LocalityVisibilityService, publish the derivative of a field
 * photograph of that site, and hand out the exact position in the file - the gate
 * holds while the picture walks around it. For rock art, which is what the gate
 * was built to protect, that is the difference between a protected site and a
 * findable one.
 *
 * Two related facts made this worse than it looks:
 *   - `photo_exif_strip` existed as a setting and was READ BY NOTHING. A control
 *     that appears in the settings and does nothing is worse than no control,
 *     because someone will rely on it.
 *   - Nothing else strips the served file. EmbeddedMetadataPiiGate scrubs GPS
 *     from C2PA metadata blocks and ahgAPIPlugin scrubs API responses; neither
 *     touches the bytes on disk.
 *
 * SCOPE - DERIVATIVES ONLY
 *
 * Masters are preservation copies and keep their metadata: destroying provenance
 * to compensate for a serving problem would be the wrong trade. Masters must be
 * protected by access control instead (see the static-nginx issue).
 *
 * The ICC profile is deliberately preserved. Imagick's stripImage() removes every
 * profile including colour, which visibly shifts wide-gamut images - a scrubber
 * that quietly degrades the picture would not survive contact with a photographer.
 */
class ImageMetadataScrubber
{
    /** Formats where EXIF can carry a position. */
    private const SCRUBBABLE = ['jpg', 'jpeg', 'tif', 'tiff', 'png', 'webp', 'heic', 'heif'];

    /**
     * Strip location metadata from one file, in place.
     *
     * @return array{ok: bool, method: string, reason: ?string} never throws - a
     *                                                          scrub failure must not take an upload down, but it must be
     *                                                          reportable, so the caller can refuse to publish
     */
    public static function scrub(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            return ['ok' => false, 'method' => 'none', 'reason' => 'file not readable'];
        }

        if (!is_writable($path)) {
            return ['ok' => false, 'method' => 'none', 'reason' => 'file not writable'];
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (!in_array($ext, self::SCRUBBABLE, true)) {
            return ['ok' => true, 'method' => 'skipped', 'reason' => 'format carries no EXIF'];
        }

        // exiftool removes GPS tags precisely and leaves everything else alone.
        // It is not installed everywhere, so it is a fast path, never the
        // dependency.
        if (self::hasExiftool()) {
            return self::scrubWithExiftool($path);
        }

        return self::scrubWithImagick($path);
    }

    /**
     * Does this file declare a position?
     *
     * Returns NULL for "cannot tell", which is not the same as "no" and must not
     * be collapsed into it. PHP's exif_read_data only reads JPEG and TIFF, so a
     * PNG, WebP or HEIC carrying GPS answers "no" to it - and phones increasingly
     * shoot HEIC. A caller that treats an unknown as clean will skip exactly the
     * files it most needs to scrub.
     *
     * @return bool|null true = carries a position, false = does not, null = unknown
     */
    public static function hasGps(string $path): ?bool
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (!in_array($ext, self::SCRUBBABLE, true)) {
            return false;   // format cannot carry EXIF at all
        }

        // exiftool reads every format we care about, so prefer it when present.
        if (self::hasExiftool()) {
            exec('exiftool -q -q -n -GPSLatitude -GPSLongitude '.escapeshellarg($path).' 2>/dev/null', $out, $code);

            if (0 !== $code) {
                return null;
            }

            return !empty(array_filter($out, static fn ($l) => '' !== trim($l)));
        }

        if (!function_exists('exif_read_data') || !in_array($ext, ['jpg', 'jpeg', 'tif', 'tiff'], true)) {
            return null;    // scrubbable format we cannot inspect here
        }

        $data = @exif_read_data($path, 'GPS', true);

        if (!is_array($data) || empty($data['GPS'])) {
            return false;
        }

        foreach ($data['GPS'] as $key => $value) {
            if (0 === stripos((string) $key, 'GPS') && '' !== (string) $value) {
                return true;
            }
        }

        return false;
    }

    private static function hasExiftool(): bool
    {
        static $has = null;

        if (null === $has) {
            exec('command -v exiftool 2>/dev/null', $out, $code);
            $has = 0 === $code && !empty($out);
        }

        return $has;
    }

    private static function scrubWithExiftool(string $path): array
    {
        // -gps:all= drops every GPS tag; -overwrite_original avoids the _original
        // copy, which would otherwise leave the coordinate sitting next to the file.
        $cmd = 'exiftool -q -q -gps:all= -overwrite_original '.escapeshellarg($path).' 2>&1';
        exec($cmd, $out, $code);

        if (0 !== $code) {
            return ['ok' => false, 'method' => 'exiftool', 'reason' => implode(' ', array_slice($out, 0, 2))];
        }

        return ['ok' => true, 'method' => 'exiftool', 'reason' => null];
    }

    private static function scrubWithImagick(string $path): array
    {
        if (!class_exists('\Imagick')) {
            return ['ok' => false, 'method' => 'none', 'reason' => 'neither exiftool nor imagick available'];
        }

        try {
            $image = new \Imagick($path);

            // Keep colour. stripImage() takes the ICC profile with everything
            // else, which shifts the rendering of any wide-gamut image.
            $icc = null;

            try {
                $icc = $image->getImageProfile('icc');
            } catch (\Throwable $e) {
                $icc = null;    // no colour profile on this file, nothing to keep
            }

            $image->stripImage();

            if (null !== $icc && '' !== $icc) {
                $image->profileImage('icc', $icc);
            }

            $image->writeImage($path);
            $image->clear();
            $image->destroy();

            return ['ok' => true, 'method' => 'imagick', 'reason' => null];
        } catch (\Throwable $e) {
            return ['ok' => false, 'method' => 'imagick', 'reason' => $e->getMessage()];
        }
    }
}
