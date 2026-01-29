<?php

declare(strict_types=1);

namespace AtomExtensions\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * IdentifierSchemeService
 *
 * Implements AtoM-compatible identifier masks:
 * - strftime tokens (e.g. %Y-%m-%d)
 * - incremental placeholder: #i
 *
 * Supports sector overrides stored as settings named:
 *   sector_<sector>__identifier_mask_enabled
 *   sector_<sector>__identifier_mask
 *   sector_<sector>__identifier_counter
 * (and the same for accession_*).
 */
class IdentifierSchemeService
{
    public const PLACEHOLDER_INCREMENT = '#i';

    public const TYPE_IDENTIFIER = 'identifier';
    public const TYPE_ACCESSION  = 'accession';

    /**
     * Generate identifier/accession value and increment the relevant counter.
     * Returns null if mask is disabled (effective).
     */
    public static function generateAndIncrement(string $type, ?string $sector = null, string $culture = 'en'): ?string
    {
        $type = strtolower($type);
        if (!in_array($type, [self::TYPE_IDENTIFIER, self::TYPE_ACCESSION], true)) {
            throw new \InvalidArgumentException('Invalid type: ' . $type);
        }

        $enabledKey = "{$type}_mask_enabled";
        $maskKey    = "{$type}_mask";
        $countKey   = "{$type}_counter";

        $enabled = self::getEffectiveValue($enabledKey, $sector, $culture);
        if ($enabled === null || $enabled === '') {
            // defaults: accession enabled = 1, identifier enabled = 0
            $enabled = ($type === self::TYPE_ACCESSION) ? '1' : '0';
        }

        if ((string) $enabled === '0') {
            return null;
        }

        $mask = self::getEffectiveValue($maskKey, $sector, $culture);
        if ($mask === null || $mask === '') {
            // AtoM default pattern
            $mask = '%Y-%m-%d/' . self::PLACEHOLDER_INCREMENT;
        }

        return DB::transaction(function () use ($mask, $countKey, $sector, $culture): string {
            $usesIncrement = (strpos($mask, self::PLACEHOLDER_INCREMENT) !== false);

            $counter = 1;
            if ($usesIncrement) {
                // Prefer sector counter if sector is provided; otherwise global
                $counterSettingName = self::settingName($countKey, $sector);
                $counter = (int) (self::getRawSettingValue($counterSettingName, $culture) ?? '1');
                if ($counter < 1) {
                    $counter = 1;
                }
            }

            // Apply increment then date formatting
            $renderedMask = $usesIncrement
                ? str_replace(self::PLACEHOLDER_INCREMENT, (string) $counter, $mask)
                : $mask;

            // AtoM uses strftime-style tokens
            $value = @strftime($renderedMask);

            // Increment counter if used
            if ($usesIncrement) {
                $counterSettingName = self::settingName($countKey, $sector);
                self::setRawSettingValue($counterSettingName, (string) ($counter + 1), $culture);
            }

            return $value;
        });
    }

    /**
     * Get effective value: sector override if present, else global.
     */
    public static function getEffectiveValue(string $key, ?string $sector = null, string $culture = 'en'): ?string
    {
        if ($sector) {
            $sectorName = self::settingName($key, $sector);
            $v = self::getRawSettingValue($sectorName, $culture);
            if ($v !== null && $v !== '') {
                return $v;
            }
        }

        return self::getRawSettingValue($key, $culture);
    }

    /**
     * Builds the setting name for a sector override.
     */
    public static function settingName(string $key, ?string $sector = null): string
    {
        if (!$sector) {
            return $key;
        }
        return 'sector_' . strtolower($sector) . '__' . $key;
    }

    /**
     * Read setting_i18n.value for a setting name (culture aware).
     */
    private static function getRawSettingValue(string $name, string $culture = 'en'): ?string
    {
        $row = DB::table('setting as s')
            ->leftJoin('setting_i18n as si', function ($j) use ($culture) {
                $j->on('s.id', '=', 'si.id')->where('si.culture', '=', $culture);
            })
            ->where('s.name', '=', $name)
            ->select('si.value')
            ->first();

        return $row ? ($row->value ?? null) : null;
    }

    /**
     * Create/update a setting value (creates setting + i18n row if missing).
     * Uses row locks for safe counter increments.
     */
    private static function setRawSettingValue(string $name, string $value, string $culture = 'en'): void
    {
        // Lock setting row if it exists
        $setting = DB::table('setting')
            ->where('name', '=', $name)
            ->lockForUpdate()
            ->first();

        if (!$setting) {
            $id = DB::table('setting')->insertGetId([
                'name' => $name,
                'scope' => null,
                'editable' => 1,
                'deleteable' => 1,
                'source_culture' => $culture,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            DB::table('setting_i18n')->insert([
                'id' => $id,
                'culture' => $culture,
                'value' => $value,
            ]);

            return;
        }

        $i18n = DB::table('setting_i18n')
            ->where('id', '=', $setting->id)
            ->where('culture', '=', $culture)
            ->lockForUpdate()
            ->first();

        if ($i18n) {
            DB::table('setting_i18n')
                ->where('id', '=', $setting->id)
                ->where('culture', '=', $culture)
                ->update(['value' => $value]);
        } else {
            DB::table('setting_i18n')->insert([
                'id' => $setting->id,
                'culture' => $culture,
                'value' => $value,
            ]);
        }
    }
}
