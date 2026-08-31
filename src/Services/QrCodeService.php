<?php

declare(strict_types=1);

namespace AtomExtensions\Services;

/**
 * QR code generation, with no network call and no composer dependency.
 *
 * Why not a library: `vendor/` is gitignored in this repository, so a composer
 * dependency does not arrive with a `git pull` - every instance would need a
 * separate `composer install`, and an instance that skipped it would fatal at
 * the point of use while looking perfectly deployed. That is the same trap as
 * the generated runtime plugin (issue #301). A self-contained encoder in `src/`
 * travels with the framework itself.
 *
 * Why not a hosted service: the previous implementation called
 * chart.googleapis.com, which now returns 404, so every label produced a broken
 * image. It also sent the URL of every record to a third party on each render,
 * and could never work in the offline portable export.
 *
 * Scope: QR Model 2, byte mode, error correction level M (~15% recovery),
 * versions 1-10 - up to 216 bytes, comfortably more than any record URL. Output
 * as SVG (default, scales cleanly and needs no extension) or PNG data URI.
 *
 * Correctness is verified by decoding the output with zbarimg in
 * test/qr-roundtrip.sh rather than by inspection.
 */
class QrCodeService
{
    /** Total codewords (data + error correction) per version, 1-10. */
    private const TOTAL_CODEWORDS = [1 => 26, 44, 70, 100, 134, 172, 196, 242, 292, 346];

    /** Level M per version: [ec codewords per block, [[block count, data codewords], ...]]. */
    private const ECC_M = [
        1 => [10, [[1, 16]]],
        2 => [16, [[1, 28]]],
        3 => [26, [[1, 44]]],
        4 => [18, [[2, 32]]],
        5 => [24, [[2, 43]]],
        6 => [16, [[4, 27]]],
        7 => [18, [[4, 31]]],
        8 => [22, [[2, 38], [2, 39]]],
        9 => [22, [[3, 36], [2, 37]]],
        10 => [26, [[4, 43], [1, 44]]],
    ];

    /** Alignment pattern centre coordinates per version. */
    private const ALIGNMENT = [
        1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
        6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
    ];

    private static ?array $expTable = null;
    private static ?array $logTable = null;

    /**
     * QR as an SVG string. Preferred: no extension needed, scales without
     * blurring, and embeds in HTML and dompdf alike.
     */
    public function svg(string $text, int $moduleSize = 4, int $quietZone = 4): string
    {
        $m = $this->matrix($text);
        $n = count($m);
        $dim = ($n + 2 * $quietZone) * $moduleSize;

        $rects = '';
        for ($y = 0; $y < $n; ++$y) {
            // Emit each run of dark modules as ONE rect rather than one per
            // module: a v10 code is 57x57, and 3249 rects bloats every page it
            // appears on.
            $x = 0;
            while ($x < $n) {
                if (!$m[$y][$x]) { ++$x; continue; }
                $run = 0;
                while ($x + $run < $n && $m[$y][$x + $run]) { ++$run; }
                $rects .= sprintf('<rect x="%d" y="%d" width="%d" height="%d"/>',
                    ($x + $quietZone) * $moduleSize, ($y + $quietZone) * $moduleSize,
                    $run * $moduleSize, $moduleSize);
                $x += $run;
            }
        }

        return sprintf(
            '<svg xmlns="http://www.w3.org/2000/svg" width="%d" height="%d" viewBox="0 0 %d %d" role="img" aria-label="QR code">'
            . '<rect width="%d" height="%d" fill="#fff"/><g fill="#000">%s</g></svg>',
            $dim, $dim, $dim, $dim, $dim, $dim, $rects
        );
    }

    /** QR as a PNG data URI, for contexts that will not take inline SVG. */
    public function pngDataUri(string $text, int $moduleSize = 4, int $quietZone = 4): string
    {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('PNG output needs the gd extension; use svg() instead.');
        }
        $m = $this->matrix($text);
        $n = count($m);
        $dim = ($n + 2 * $quietZone) * $moduleSize;

        $img = imagecreatetruecolor($dim, $dim);
        $white = imagecolorallocate($img, 255, 255, 255);
        $black = imagecolorallocate($img, 0, 0, 0);
        imagefilledrectangle($img, 0, 0, $dim, $dim, $white);
        for ($y = 0; $y < $n; ++$y) {
            for ($x = 0; $x < $n; ++$x) {
                if ($m[$y][$x]) {
                    imagefilledrectangle($img,
                        ($x + $quietZone) * $moduleSize, ($y + $quietZone) * $moduleSize,
                        ($x + $quietZone + 1) * $moduleSize - 1, ($y + $quietZone + 1) * $moduleSize - 1,
                        $black);
                }
            }
        }
        ob_start();
        imagepng($img);
        $png = (string) ob_get_clean();
        imagedestroy($img);

        return 'data:image/png;base64,' . base64_encode($png);
    }

    /** The module matrix: true = dark. Exposed for tests and custom renderers. */
    public function matrix(string $text): array
    {
        $bytes = array_values(unpack('C*', $text));
        $version = $this->chooseVersion(count($bytes));
        $bits = $this->encodeData($bytes, $version);
        $codewords = $this->interleave($bits, $version);

        $best = null;
        $bestScore = PHP_INT_MAX;
        for ($mask = 0; $mask < 8; ++$mask) {
            $m = $this->build($codewords, $version, $mask);
            $score = $this->penalty($m);
            if ($score < $bestScore) { $bestScore = $score; $best = $m; }
        }

        return $best;
    }

    private function chooseVersion(int $byteCount): int
    {
        foreach (self::ECC_M as $v => [$ecPerBlock, $groups]) {
            $dataCodewords = 0;
            foreach ($groups as [$count, $size]) { $dataCodewords += $count * $size; }
            $countBits = $v < 10 ? 8 : 16;
            $needed = (int) ceil((4 + $countBits + $byteCount * 8) / 8);
            if ($needed <= $dataCodewords) { return $v; }
        }
        throw new \RuntimeException(sprintf(
            'Text is %d bytes; this encoder handles up to 216 (QR version 10, level M). '
            . 'Shorten the URL, or extend the version tables.', $byteCount));
    }

    private function encodeData(array $bytes, int $version): array
    {
        [$ecPerBlock, $groups] = self::ECC_M[$version];
        $dataCodewords = 0;
        foreach ($groups as [$count, $size]) { $dataCodewords += $count * $size; }

        $bits = [];
        $push = function (int $value, int $length) use (&$bits): void {
            for ($i = $length - 1; $i >= 0; --$i) { $bits[] = ($value >> $i) & 1; }
        };

        $push(0b0100, 4);                                  // byte mode
        $push(count($bytes), $version < 10 ? 8 : 16);      // character count
        foreach ($bytes as $b) { $push($b, 8); }

        $capacityBits = $dataCodewords * 8;
        for ($i = 0; $i < 4 && count($bits) < $capacityBits; ++$i) { $bits[] = 0; }   // terminator
        while (count($bits) % 8 !== 0) { $bits[] = 0; }

        $pad = [0xEC, 0x11];
        $i = 0;
        while (count($bits) < $capacityBits) { $push($pad[$i++ % 2], 8); }

        return $bits;
    }

    private function interleave(array $bits, int $version): array
    {
        [$ecPerBlock, $groups] = self::ECC_M[$version];

        $data = [];
        for ($i = 0; $i < count($bits); $i += 8) {
            $byte = 0;
            for ($j = 0; $j < 8; ++$j) { $byte = ($byte << 1) | $bits[$i + $j]; }
            $data[] = $byte;
        }

        $blocks = [];
        $offset = 0;
        foreach ($groups as [$count, $size]) {
            for ($b = 0; $b < $count; ++$b) {
                $blocks[] = array_slice($data, $offset, $size);
                $offset += $size;
            }
        }

        $ecBlocks = array_map(fn(array $blk) => $this->reedSolomon($blk, $ecPerBlock), $blocks);

        $out = [];
        $maxData = max(array_map('count', $blocks));
        for ($i = 0; $i < $maxData; ++$i) {
            foreach ($blocks as $blk) { if (isset($blk[$i])) { $out[] = $blk[$i]; } }
        }
        for ($i = 0; $i < $ecPerBlock; ++$i) {
            foreach ($ecBlocks as $blk) { $out[] = $blk[$i]; }
        }

        return $out;
    }

    private static function initGf(): void
    {
        if (self::$expTable !== null) { return; }
        self::$expTable = []; self::$logTable = [];
        $x = 1;
        for ($i = 0; $i < 256; ++$i) {
            self::$expTable[$i] = $x;
            self::$logTable[$x] = $i;
            $x <<= 1;
            if ($x & 0x100) { $x ^= 0x11D; }
        }
    }

    private function reedSolomon(array $data, int $ecCount): array
    {
        self::initGf();

        // Generator polynomial for ecCount symbols.
        $gen = [1];
        for ($i = 0; $i < $ecCount; ++$i) {
            $next = array_fill(0, count($gen) + 1, 0);
            foreach ($gen as $j => $coef) {
                $next[$j] ^= $coef;
                $next[$j + 1] ^= $this->gfMul($coef, self::$expTable[$i]);
            }
            $gen = $next;
        }

        $rem = array_merge($data, array_fill(0, $ecCount, 0));
        for ($i = 0; $i < count($data); ++$i) {
            $factor = $rem[$i];
            if ($factor === 0) { continue; }
            foreach ($gen as $j => $coef) {
                $rem[$i + $j] ^= $this->gfMul($coef, $factor);
            }
        }

        return array_slice($rem, count($data));
    }

    private function gfMul(int $a, int $b): int
    {
        if ($a === 0 || $b === 0) { return 0; }
        return self::$expTable[(self::$logTable[$a] + self::$logTable[$b]) % 255];
    }

    private function build(array $codewords, int $version, int $mask): array
    {
        $n = 17 + 4 * $version;
        $m = array_fill(0, $n, array_fill(0, $n, false));
        $reserved = array_fill(0, $n, array_fill(0, $n, false));

        $finder = function (int $r, int $c) use (&$m, &$reserved, $n): void {
            for ($dy = -1; $dy <= 7; ++$dy) {
                for ($dx = -1; $dx <= 7; ++$dx) {
                    $y = $r + $dy; $x = $c + $dx;
                    if ($y < 0 || $y >= $n || $x < 0 || $x >= $n) { continue; }
                    $inner = ($dy >= 0 && $dy <= 6 && $dx >= 0 && $dx <= 6)
                        && ($dy === 0 || $dy === 6 || $dx === 0 || $dx === 6
                            || ($dy >= 2 && $dy <= 4 && $dx >= 2 && $dx <= 4));
                    $m[$y][$x] = $inner;
                    $reserved[$y][$x] = true;
                }
            }
        };
        $finder(0, 0); $finder(0, $n - 7); $finder($n - 7, 0);

        // Timing patterns
        for ($i = 8; $i < $n - 8; ++$i) {
            $m[6][$i] = ($i % 2 === 0); $reserved[6][$i] = true;
            $m[$i][6] = ($i % 2 === 0); $reserved[$i][6] = true;
        }

        // Alignment patterns, skipping those overlapping a finder
        $centres = self::ALIGNMENT[$version];
        foreach ($centres as $cy) {
            foreach ($centres as $cx) {
                if (($cy <= 8 && $cx <= 8) || ($cy <= 8 && $cx >= $n - 9) || ($cy >= $n - 9 && $cx <= 8)) { continue; }
                for ($dy = -2; $dy <= 2; ++$dy) {
                    for ($dx = -2; $dx <= 2; ++$dx) {
                        $m[$cy + $dy][$cx + $dx] = (max(abs($dy), abs($dx)) !== 1);
                        $reserved[$cy + $dy][$cx + $dx] = true;
                    }
                }
            }
        }

        // Dark module and format-info reservation
        $m[$n - 8][8] = true; $reserved[$n - 8][8] = true;
        for ($i = 0; $i < 9; ++$i) {
            if (!$reserved[8][$i]) { $reserved[8][$i] = true; }
            if (!$reserved[$i][8]) { $reserved[$i][8] = true; }
        }
        for ($i = 0; $i < 8; ++$i) {
            $reserved[8][$n - 1 - $i] = true;
            $reserved[$n - 1 - $i][8] = true;
        }

        // Version information for versions 7 and above
        if ($version >= 7) {
            $vinfo = $this->versionBits($version);
            for ($i = 0; $i < 18; ++$i) {
                $bit = (bool) (($vinfo >> $i) & 1);
                $a = intdiv($i, 3); $b = $i % 3;
                $m[$n - 11 + $b][$a] = $bit; $reserved[$n - 11 + $b][$a] = true;
                $m[$a][$n - 11 + $b] = $bit; $reserved[$a][$n - 11 + $b] = true;
            }
        }

        // Data placement: two columns at a time, right to left, skipping column 6
        $bitIndex = 0;
        $total = count($codewords) * 8;
        $up = true;
        for ($col = $n - 1; $col > 0; $col -= 2) {
            if ($col === 6) { --$col; }
            for ($i = 0; $i < $n; ++$i) {
                $row = $up ? $n - 1 - $i : $i;
                for ($c = 0; $c < 2; ++$c) {
                    $x = $col - $c;
                    if ($reserved[$row][$x]) { continue; }
                    $bit = false;
                    if ($bitIndex < $total) {
                        $bit = (bool) (($codewords[$bitIndex >> 3] >> (7 - ($bitIndex & 7))) & 1);
                        ++$bitIndex;
                    }
                    if ($this->maskAt($mask, $row, $x)) { $bit = !$bit; }
                    $m[$row][$x] = $bit;
                }
            }
            $up = !$up;
        }

        // Format information (level M = 0b00), written last over reserved cells
        $fmt = $this->formatBits($mask);
        for ($i = 0; $i < 15; ++$i) {
            $bit = (bool) (($fmt >> $i) & 1);
            if ($i < 6)       { $m[$i][8] = $bit; }
            elseif ($i === 6) { $m[7][8] = $bit; }
            elseif ($i === 7) { $m[8][8] = $bit; }
            elseif ($i === 8) { $m[8][7] = $bit; }
            else              { $m[8][14 - $i] = $bit; }

            if ($i < 8)  { $m[8][$n - 1 - $i] = $bit; }
            else         { $m[$n - 15 + $i][8] = $bit; }
        }

        return $m;
    }

    private function maskAt(int $mask, int $row, int $col): bool
    {
        switch ($mask) {
            case 0: return ($row + $col) % 2 === 0;
            case 1: return $row % 2 === 0;
            case 2: return $col % 3 === 0;
            case 3: return ($row + $col) % 3 === 0;
            case 4: return (intdiv($row, 2) + intdiv($col, 3)) % 2 === 0;
            case 5: return (($row * $col) % 2) + (($row * $col) % 3) === 0;
            case 6: return ((($row * $col) % 2) + (($row * $col) % 3)) % 2 === 0;
            default: return (((($row + $col) % 2) + (($row * $col) % 3)) % 2) === 0;
        }
    }

    private function formatBits(int $mask): int
    {
        $data = (0b00 << 3) | $mask;          // level M
        $rem = $data << 10;
        for ($i = 14; $i >= 10; --$i) {
            if (($rem >> $i) & 1) { $rem ^= 0b10100110111 << ($i - 10); }
        }
        return (($data << 10) | $rem) ^ 0b101010000010010;
    }

    private function versionBits(int $version): int
    {
        $rem = $version << 12;
        for ($i = 17; $i >= 12; --$i) {
            if (($rem >> $i) & 1) { $rem ^= 0b1111100100101 << ($i - 12); }
        }
        return ($version << 12) | $rem;
    }

    private function penalty(array $m): int
    {
        $n = count($m);
        $score = 0;

        // Rule 1: runs of five or more same-colour modules
        for ($pass = 0; $pass < 2; ++$pass) {
            for ($a = 0; $a < $n; ++$a) {
                $run = 1;
                for ($b = 1; $b < $n; ++$b) {
                    $cur  = $pass ? $m[$b][$a] : $m[$a][$b];
                    $prev = $pass ? $m[$b - 1][$a] : $m[$a][$b - 1];
                    if ($cur === $prev) { ++$run; continue; }
                    if ($run >= 5) { $score += 3 + ($run - 5); }
                    $run = 1;
                }
                if ($run >= 5) { $score += 3 + ($run - 5); }
            }
        }

        // Rule 2: 2x2 blocks of one colour
        for ($y = 0; $y < $n - 1; ++$y) {
            for ($x = 0; $x < $n - 1; ++$x) {
                $v = $m[$y][$x];
                if ($m[$y][$x + 1] === $v && $m[$y + 1][$x] === $v && $m[$y + 1][$x + 1] === $v) { $score += 3; }
            }
        }

        // Rule 3: finder-like patterns
        $pat1 = [true,false,true,true,true,false,true,false,false,false,false];
        $pat2 = array_reverse($pat1);
        for ($pass = 0; $pass < 2; ++$pass) {
            for ($a = 0; $a < $n; ++$a) {
                for ($b = 0; $b <= $n - 11; ++$b) {
                    $seq = [];
                    for ($k = 0; $k < 11; ++$k) { $seq[] = $pass ? $m[$b + $k][$a] : $m[$a][$b + $k]; }
                    if ($seq === $pat1 || $seq === $pat2) { $score += 40; }
                }
            }
        }

        // Rule 4: deviation from an even balance of dark and light
        $dark = 0;
        foreach ($m as $row) { foreach ($row as $v) { if ($v) { ++$dark; } } }
        $percent = ($dark * 100) / ($n * $n);
        $score += 10 * (int) (abs($percent - 50) / 5);

        return $score;
    }
}
