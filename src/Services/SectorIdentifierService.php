<?php

/**
 * SectorIdentifierService - shared identifier auto-generator for the
 * sector-aware entity create() flows (AtoM-AHG port).
 *
 * Ported from Heratio's AhgCore\Services\SectorIdentifierService so the
 * AtoM-AHG sector creates (dam/create etc.) share ONE counter with the
 * /ahgSettings/sectorNumbering admin page. Both read and write the same
 * sector_<code>_identifier_counter setting in the setting/setting_i18n
 * table - no more disconnected numbering systems.
 *
 * Setting key name variants honoured (single-underscore preferred so an
 * operator save wins over a seed):
 *   sector_<code>_identifier_mask_enabled  / sector_<code>__identifier_mask_enabled
 *   sector_<code>_identifier_mask          / sector_<code>__identifier_mask
 *   sector_<code>_identifier_counter       / sector_<code>__identifier_counter
 * Falls back to a global trio (no sector_ prefix) when the sector mask is
 * disabled or empty.
 *
 * Mask renderer supports AtoM-style %Y% %y% %m% %d% %NNNi% and curly
 * {YYYY} {YY} {MM} {DD} {SECTOR} plus a #+ run; anything else passes
 * through literally.
 *
 * Counter increment uses optimistic CAS with retries so concurrent
 * creates can't collide on the same number.
 *
 * Copyright (C) 2026 Johan Pieterse / Plain Sailing Information Systems
 * Licensed under the GNU AGPL v3.
 */

namespace AtomExtensions\Services;

use Illuminate\Database\Capsule\Manager as DB;

class SectorIdentifierService
{
    /** Max retries when a concurrent counter increment beats us. */
    private const MAX_RETRIES = 5;

    /**
     * Resolve a free-form source_standard value to one of the five sector
     * codes the settings page knows about. Returns null when nothing matches.
     */
    public static function resolveSector(?string $sourceStandard): ?string
    {
        $s = strtolower(trim((string) $sourceStandard));
        if ($s === '') return null;
        if ($s === 'dam') return 'dam';
        if ($s === 'library') return 'library';
        if ($s === 'gallery') return 'gallery';
        if (str_contains($s, 'cco') || str_contains($s, 'museum')) return 'museum';
        if (str_contains($s, 'isad') || str_contains($s, 'dacs') || str_contains($s, 'rad') || $s === 'archive') return 'archive';
        return 'archive';
    }

    /**
     * Generate the next identifier for a sector, or null when the sector
     * mask is disabled / not configured (caller passes through the
     * user-supplied identifier on null).
     */
    public static function next(?string $sectorCode): ?string
    {
        if ($sectorCode === null || $sectorCode === '') return null;
        $sectorCode = strtolower($sectorCode);

        [$enabledName, $enabled] = self::settingBoolEither(
            "sector_{$sectorCode}_identifier_mask_enabled",
            "sector_{$sectorCode}__identifier_mask_enabled",
            false
        );
        [$maskName, $mask] = self::settingEither(
            "sector_{$sectorCode}_identifier_mask",
            "sector_{$sectorCode}__identifier_mask",
            ''
        );
        if ($enabled && $mask !== '') {
            $isDouble = str_contains((string) $maskName, '__identifier_mask');
            $counterName = $isDouble
                ? "sector_{$sectorCode}__identifier_counter"
                : "sector_{$sectorCode}_identifier_counter";
            $next = self::incrementCounter($counterName);
            return self::renderMask((string) $mask, $next, $sectorCode);
        }

        $globalEnabled = self::settingBool('identifier_mask_enabled', false);
        $globalMask = (string) self::setting('identifier_mask', '');
        if ($globalEnabled && $globalMask !== '') {
            $next = self::incrementCounter('identifier_counter');
            return self::renderMask($globalMask, $next, $sectorCode);
        }

        return null;
    }

    /**
     * Preview the next identifier for a sector WITHOUT consuming the
     * counter - the create form's identifierGenerator component uses this
     * so the preview it shows matches what next() produces at save time.
     * Returns ['next_reference','pattern','scope'] or null when no mask
     * applies (caller should fall through to the legacy NumberingService).
     */
    public static function previewInfo(?string $sectorCode): ?array
    {
        if ($sectorCode === null || $sectorCode === '') return null;
        $sectorCode = strtolower($sectorCode);

        [$enabledName, $enabled] = self::settingBoolEither(
            "sector_{$sectorCode}_identifier_mask_enabled",
            "sector_{$sectorCode}__identifier_mask_enabled",
            false
        );
        [$maskName, $mask] = self::settingEither(
            "sector_{$sectorCode}_identifier_mask",
            "sector_{$sectorCode}__identifier_mask",
            ''
        );
        if ($enabled && $mask !== '') {
            $isDouble = str_contains((string) $maskName, '__identifier_mask');
            $counterName = $isDouble
                ? "sector_{$sectorCode}__identifier_counter"
                : "sector_{$sectorCode}_identifier_counter";
            return [
                'next_reference' => self::renderMask((string) $mask, self::peekCounter($counterName), $sectorCode),
                'pattern' => (string) $mask,
                'scope' => 'sector',
            ];
        }

        $globalEnabled = self::settingBool('identifier_mask_enabled', false);
        $globalMask = (string) self::setting('identifier_mask', '');
        if ($globalEnabled && $globalMask !== '') {
            return [
                'next_reference' => self::renderMask($globalMask, self::peekCounter('identifier_counter'), $sectorCode),
                'pattern' => $globalMask,
                'scope' => 'global',
            ];
        }

        return null;
    }

    /**
     * Read the counter setting and return what the NEXT value would be
     * (current + 1) WITHOUT writing - the read-only twin of the read step
     * inside incrementCounter(). A missing row previews as 1, matching
     * bootstrapCounter()'s seed.
     */
    private static function peekCounter(string $name): int
    {
        return (int) self::setting($name, 0) + 1;
    }

    /**
     * Atomically increment the counter setting, return the NEW value.
     * Optimistic CAS: read current, UPDATE only when value still equals
     * what we read, retry on race.
     */
    private static function incrementCounter(string $name): int
    {
        for ($attempt = 0; $attempt < self::MAX_RETRIES; $attempt++) {
            $row = DB::table('setting as s')
                ->join('setting_i18n as si', 'si.id', '=', 's.id')
                ->whereNull('s.scope')
                ->where('s.name', $name)
                ->where('si.culture', 'en')
                ->select('s.id', 'si.value')
                ->first();

            if (!$row) {
                return self::bootstrapCounter($name, 1);
            }

            $current = (int) ($row->value ?? 0);
            $next = $current + 1;

            $affected = DB::table('setting_i18n')
                ->where('id', $row->id)
                ->where('culture', 'en')
                ->where('value', (string) $current)
                ->update(['value' => (string) $next]);

            if ($affected === 1) {
                return $next;
            }
            usleep(random_int(1000, 5000));
        }

        // Last resort: non-CAS bump.
        $current = (int) DB::table('setting as s')
            ->join('setting_i18n as si', 'si.id', '=', 's.id')
            ->whereNull('s.scope')
            ->where('s.name', $name)
            ->where('si.culture', 'en')
            ->value('si.value');
        $next = $current + 1;
        DB::table('setting_i18n as si')
            ->join('setting as s', 's.id', '=', 'si.id')
            ->whereNull('s.scope')
            ->where('s.name', $name)
            ->where('si.culture', 'en')
            ->update(['si.value' => (string) $next]);
        return $next;
    }

    /**
     * Insert the counter row when it doesn't exist yet.
     * CTI: object('QubitSetting') -> setting -> setting_i18n.
     */
    private static function bootstrapCounter(string $name, int $value): int
    {
        $nowStr = date('Y-m-d H:i:s');
        $objId = DB::table('object')->insertGetId([
            'class_name' => 'QubitSetting',
            'created_at' => $nowStr,
            'updated_at' => $nowStr,
            'serial_number' => 0,
        ]);
        DB::table('setting')->insert([
            'id' => $objId,
            'name' => $name,
            'scope' => null,
            'editable' => 1,
            'deleteable' => 1,
            'source_culture' => 'en',
        ]);
        DB::table('setting_i18n')->insert([
            'id' => $objId,
            'culture' => 'en',
            'value' => (string) $value,
        ]);
        return $value;
    }

    /**
     * Render a mask template into a final identifier.
     */
    private static function renderMask(string $mask, int $counter, string $sectorCode): string
    {
        $out = $mask;

        // AtoM-style counter: %04i% -> padded, %i% -> raw.
        $out = preg_replace_callback('/%(\d*)i%/', function ($m) use ($counter) {
            $width = (int) $m[1];
            return $width > 0
                ? str_pad((string) $counter, $width, '0', STR_PAD_LEFT)
                : (string) $counter;
        }, $out);
        // AtoM-style date placeholders.
        $out = strtr($out, [
            '%Y%' => date('Y'),
            '%y%' => date('y'),
            '%m%' => date('m'),
            '%d%' => date('d'),
        ]);
        // Curly-brace style.
        $out = strtr($out, [
            '{YYYY}' => date('Y'),
            '{YY}' => date('y'),
            '{MM}' => date('m'),
            '{DD}' => date('d'),
            '{SECTOR}' => strtoupper($sectorCode),
        ]);
        // Hash-run style: any contiguous '#' run -> counter padded.
        $out = preg_replace_callback('/#+/', function ($m) use ($counter) {
            return str_pad((string) $counter, strlen($m[0]), '0', STR_PAD_LEFT);
        }, $out);
        return $out;
    }

    /**
     * Read a setting trying two candidate names in order. Returns
     * [name_that_won, value].
     */
    private static function settingEither(string $primary, string $fallback, $default): array
    {
        $v = self::setting($primary, null);
        if ($v !== null && $v !== '') return [$primary, $v];
        $v = self::setting($fallback, null);
        if ($v !== null && $v !== '') return [$fallback, $v];
        return [$primary, $default];
    }

    /** Same as settingEither but boolean-shaped. */
    private static function settingBoolEither(string $primary, string $fallback, bool $default): array
    {
        $v = self::setting($primary, null);
        if ($v !== null && $v !== '') return [$primary, in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true)];
        $v = self::setting($fallback, null);
        if ($v !== null && $v !== '') return [$fallback, in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true)];
        return [$primary, $default];
    }

    /** Read a setting from the i18n setting table (global scope). */
    private static function setting(string $name, $default = null)
    {
        try {
            $v = DB::table('setting as s')
                ->join('setting_i18n as si', 'si.id', '=', 's.id')
                ->whereNull('s.scope')
                ->where('s.name', $name)
                ->where('si.culture', 'en')
                ->value('si.value');
        } catch (\Throwable $e) {
            return $default;
        }
        return ($v === null || $v === '') ? $default : $v;
    }

    private static function settingBool(string $name, bool $default): bool
    {
        $v = self::setting($name, null);
        if ($v === null) return $default;
        return in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
    }
}
