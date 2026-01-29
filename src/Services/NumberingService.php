<?php

namespace AtomExtensions\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * Numbering Service for GLAM/DAM Sectors
 *
 * Generates unique identifiers based on configurable patterns per sector.
 *
 * Supported Tokens:
 * - {SEQ:n}     Sequential number, n digits zero-padded
 * - {YEAR}      Current year (4 digit)
 * - {YY}        Current year (2 digit)
 * - {MONTH}     Current month (2 digit)
 * - {DAY}       Current day (2 digit)
 * - {PREFIX}    Configured prefix for sector
 * - {REPO}      Repository code
 * - {FONDS}     Parent fonds code
 * - {SERIES}    Parent series code
 * - {COLLECTION} Collection identifier
 * - {DEPT}      Department code
 * - {TYPE}      Material/media type
 * - {UUID}      Short UUID (8 chars)
 * - {RANDOM:n}  Random alphanumeric, n chars
 * - {ITEM}      Item number within lot
 * - {PROJECT}   Project code
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class NumberingService
{
    private static ?NumberingService $instance = null;

    /** @var array Cached schemes by sector */
    private array $schemeCache = [];

    /** @var array Context data for token replacement */
    private array $context = [];

    private function __construct()
    {
        if (\AtomExtensions\Database\DatabaseBootstrap::getCapsule() === null) {
            \AtomExtensions\Database\DatabaseBootstrap::initializeFromAtom();
        }
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Get next reference for a sector.
     *
     * @param string $sector Sector code (archive, library, museum, gallery, dam)
     * @param array  $context Additional context for token replacement
     * @param int|null $repositoryId Optional repository for scheme override
     *
     * @return string Generated reference
     */
    public function getNextReference(string $sector, array $context = [], ?int $repositoryId = null): string
    {
        $scheme = $this->getDefaultScheme($sector, $repositoryId);
        if (!$scheme) {
            // Fallback to simple sequential
            return strtoupper($sector) . '-' . str_pad($this->getSimpleSequence($sector), 5, '0', STR_PAD_LEFT);
        }

        $this->context = $context;

        // Check for sequence reset
        $this->checkSequenceReset($scheme);

        // Get next sequence number
        $nextSeq = $this->getNextSequenceNumber($scheme);

        // Generate reference
        $reference = $this->applyPattern($scheme->pattern, $scheme, $nextSeq);

        // Reserve the reference
        $this->reserveReference($scheme->id, $nextSeq, $reference);

        // Update scheme counter
        $this->updateSchemeCounter($scheme->id, $nextSeq);

        return $reference;
    }

    /**
     * Preview next reference without consuming sequence.
     */
    public function previewNextReference(string $sector, array $context = [], ?int $repositoryId = null): string
    {
        $scheme = $this->getDefaultScheme($sector, $repositoryId);
        if (!$scheme) {
            $seq = $this->getSimpleSequence($sector) + 1;

            return strtoupper($sector) . '-' . str_pad($seq, 5, '0', STR_PAD_LEFT);
        }

        $this->context = $context;
        $nextSeq = $this->peekNextSequenceNumber($scheme);

        return $this->applyPattern($scheme->pattern, $scheme, $nextSeq);
    }

    /**
     * Preview multiple references for UI display.
     */
    public function previewMultiple(string $sector, int $count = 3, array $context = []): array
    {
        $previews = [];
        $scheme = $this->getDefaultScheme($sector);

        if (!$scheme) {
            $seq = $this->getSimpleSequence($sector);
            for ($i = 1; $i <= $count; $i++) {
                $previews[] = strtoupper($sector) . '-' . str_pad($seq + $i, 5, '0', STR_PAD_LEFT);
            }

            return $previews;
        }

        $this->context = $context;
        $nextSeq = $this->peekNextSequenceNumber($scheme);

        for ($i = 0; $i < $count; $i++) {
            $previews[] = $this->applyPattern($scheme->pattern, $scheme, $nextSeq + $i);
        }

        return $previews;
    }

    /**
     * Validate a manual reference against the scheme.
     *
     * @param string   $reference    The identifier to validate
     * @param string   $sector       Sector code
     * @param int|null $excludeId    Object ID to exclude from duplicate check (for edits)
     * @param int|null $repositoryId Repository ID for scheme lookup
     */
    public function validateReference(string $reference, string $sector, ?int $excludeId = null, ?int $repositoryId = null): array
    {
        $result = [
            'valid' => true,
            'errors' => [],
            'warnings' => [],
            'identifier' => $reference,
        ];

        if (empty(trim($reference))) {
            return $result; // Empty is valid (will be auto-generated)
        }

        // Check for duplicates
        if ($this->isDuplicate($reference, $excludeId)) {
            $result['valid'] = false;
            $result['errors'][] = 'Identifier already exists in the system';
        }

        // Check format against scheme regex
        $scheme = $this->getDefaultScheme($sector, $repositoryId);
        if ($scheme) {
            // If scheme has explicit validation regex, use it
            if ($scheme->validation_regex) {
                if (!preg_match('/' . $scheme->validation_regex . '/', $reference)) {
                    $result['warnings'][] = 'Identifier does not match expected format: ' . $scheme->pattern;
                    $result['expected_format'] = $scheme->pattern;
                }
            } else {
                // Generate a regex from the pattern for basic validation
                $patternRegex = $this->patternToRegex($scheme->pattern);
                if ($patternRegex && !preg_match($patternRegex, $reference)) {
                    $result['warnings'][] = 'Identifier format differs from scheme pattern: ' . $scheme->pattern;
                    $result['expected_format'] = $scheme->pattern;
                }
            }
        }

        return $result;
    }

    /**
     * Convert a numbering pattern to a validation regex.
     */
    private function patternToRegex(string $pattern): ?string
    {
        // Replace tokens with regex patterns
        $regex = preg_quote($pattern, '/');

        // {SEQ:n} - n digits
        $regex = preg_replace_callback('/\\\\{SEQ:(\d+)\\\\}/', function ($m) {
            return '\d{' . $m[1] . '}';
        }, $regex);

        // {YEAR} - 4 digits
        $regex = str_replace('\{YEAR\}', '\d{4}', $regex);

        // {YY} - 2 digits
        $regex = str_replace('\{YY\}', '\d{2}', $regex);

        // {MONTH} - 2 digits
        $regex = str_replace('\{MONTH\}', '\d{2}', $regex);

        // {DAY} - 2 digits
        $regex = str_replace('\{DAY\}', '\d{2}', $regex);

        // {REPO} - alphanumeric
        $regex = str_replace('\{REPO\}', '[A-Za-z0-9\-]+', $regex);

        // {FONDS} - alphanumeric
        $regex = str_replace('\{FONDS\}', '[A-Za-z0-9\-]*', $regex);

        // {SERIES} - alphanumeric
        $regex = str_replace('\{SERIES\}', '[A-Za-z0-9\-]*', $regex);

        // {COLLECTION} - alphanumeric
        $regex = str_replace('\{COLLECTION\}', '[A-Za-z0-9\-]*', $regex);

        // {PREFIX} - alphanumeric
        $regex = str_replace('\{PREFIX\}', '[A-Za-z0-9\-]+', $regex);

        // {DEPT} - alphanumeric
        $regex = str_replace('\{DEPT\}', '[A-Za-z0-9\-]+', $regex);

        // {TYPE} - alphanumeric
        $regex = str_replace('\{TYPE\}', '[A-Za-z0-9\-]+', $regex);

        // {UUID} - 8 hex chars
        $regex = str_replace('\{UUID\}', '[a-f0-9]{8}', $regex);

        // {RANDOM:n} - n alphanumeric
        $regex = preg_replace_callback('/\\\\{RANDOM:(\d+)\\\\}/', function ($m) {
            return '[A-Za-z0-9]{' . $m[1] . '}';
        }, $regex);

        // {ITEM} - digits
        $regex = str_replace('\{ITEM\}', '\d+', $regex);

        // {PROJECT} - alphanumeric
        $regex = str_replace('\{PROJECT\}', '[A-Za-z0-9\-]+', $regex);

        return '/^' . $regex . '$/i';
    }

    /**
     * Check if a reference already exists.
     *
     * @param string   $reference The identifier to check
     * @param int|null $excludeId Object ID to exclude (for edit scenarios)
     */
    public function isDuplicate(string $reference, ?int $excludeId = null): bool
    {
        // Check in used sequences
        $query = DB::table('numbering_sequence_used')
            ->where('generated_reference', $reference);

        if ($excludeId) {
            $query->where('object_id', '!=', $excludeId);
        }

        if ($query->exists()) {
            return true;
        }

        // Check in information_object identifiers
        $query = DB::table('information_object')
            ->where('identifier', $reference);

        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }

    /**
     * Reserve a reference when creating a record.
     */
    public function reserveReference(int $schemeId, int $sequenceNumber, string $reference, ?int $objectId = null): void
    {
        DB::table('numbering_sequence_used')->insert([
            'scheme_id' => $schemeId,
            'sequence_number' => $sequenceNumber,
            'generated_reference' => $reference,
            'object_id' => $objectId,
            'year_context' => date('Y'),
            'month_context' => date('n'),
            'created_at' => now(),
        ]);
    }

    /**
     * Release a reference (for fill_gaps feature).
     */
    public function releaseReference(string $reference): void
    {
        DB::table('numbering_sequence_used')
            ->where('generated_reference', $reference)
            ->delete();
    }

    /**
     * Link a reference to an object after creation.
     */
    public function linkReferenceToObject(string $reference, int $objectId, string $objectType = 'information_object'): void
    {
        DB::table('numbering_sequence_used')
            ->where('generated_reference', $reference)
            ->update([
                'object_id' => $objectId,
                'object_type' => $objectType,
            ]);
    }

    /**
     * Get all schemes for a sector.
     */
    public function getSchemesForSector(string $sector): array
    {
        return DB::table('numbering_scheme')
            ->where('sector', $sector)
            ->orWhere('sector', 'all')
            ->where('is_active', 1)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * Get all schemes.
     */
    public function getAllSchemes(): array
    {
        return DB::table('numbering_scheme')
            ->orderBy('sector')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get()
            ->all();
    }

    /**
     * Get scheme by ID.
     */
    public function getSchemeById(int $id): ?object
    {
        return DB::table('numbering_scheme')->where('id', $id)->first();
    }

    /**
     * Create a new scheme.
     */
    public function createScheme(array $data): int
    {
        // If setting as default, unset other defaults for this sector
        if (!empty($data['is_default'])) {
            DB::table('numbering_scheme')
                ->where('sector', $data['sector'])
                ->update(['is_default' => 0]);
        }

        return DB::table('numbering_scheme')->insertGetId([
            'name' => $data['name'],
            'sector' => $data['sector'],
            'pattern' => $data['pattern'],
            'description' => $data['description'] ?? null,
            'current_sequence' => $data['current_sequence'] ?? 0,
            'sequence_reset' => $data['sequence_reset'] ?? 'never',
            'fill_gaps' => $data['fill_gaps'] ?? 0,
            'validation_regex' => $data['validation_regex'] ?? null,
            'allow_manual_override' => $data['allow_manual_override'] ?? 1,
            'is_active' => $data['is_active'] ?? 1,
            'is_default' => $data['is_default'] ?? 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Update a scheme.
     */
    public function updateScheme(int $id, array $data): void
    {
        $scheme = $this->getSchemeById($id);
        if (!$scheme) {
            return;
        }

        // If setting as default, unset other defaults for this sector
        if (!empty($data['is_default'])) {
            DB::table('numbering_scheme')
                ->where('sector', $scheme->sector)
                ->where('id', '!=', $id)
                ->update(['is_default' => 0]);
        }

        $data['updated_at'] = now();
        DB::table('numbering_scheme')->where('id', $id)->update($data);
    }

    /**
     * Delete a scheme.
     */
    public function deleteScheme(int $id): void
    {
        DB::table('numbering_scheme')->where('id', $id)->delete();
    }

    /**
     * Set a scheme as default for its sector.
     */
    public function setAsDefault(int $id): void
    {
        $scheme = $this->getSchemeById($id);
        if (!$scheme) {
            return;
        }

        // Unset other defaults
        DB::table('numbering_scheme')
            ->where('sector', $scheme->sector)
            ->update(['is_default' => 0]);

        // Set this as default
        DB::table('numbering_scheme')
            ->where('id', $id)
            ->update(['is_default' => 1]);
    }

    /**
     * Reset sequence for a scheme.
     */
    public function resetSequence(int $schemeId, int $newValue = 0): void
    {
        DB::table('numbering_scheme')
            ->where('id', $schemeId)
            ->update([
                'current_sequence' => $newValue,
                'last_reset_date' => date('Y-m-d'),
            ]);
    }

    /**
     * Get available tokens with descriptions.
     */
    public function getAvailableTokens(): array
    {
        return [
            '{SEQ:n}' => 'Sequential number, n digits zero-padded (e.g., {SEQ:4} = 0001)',
            '{YEAR}' => 'Current year, 4 digits (e.g., 2026)',
            '{YY}' => 'Current year, 2 digits (e.g., 26)',
            '{MONTH}' => 'Current month, 2 digits (e.g., 01)',
            '{DAY}' => 'Current day, 2 digits (e.g., 28)',
            '{PREFIX}' => 'Sector prefix (ARCH, LIB, MUS, GAL, DAM)',
            '{REPO}' => 'Repository code',
            '{FONDS}' => 'Parent fonds code',
            '{SERIES}' => 'Parent series code',
            '{COLLECTION}' => 'Collection identifier',
            '{DEPT}' => 'Department code',
            '{TYPE}' => 'Material/media type (IMG, VID, DOC, etc.)',
            '{UUID}' => 'Short unique identifier (8 chars)',
            '{RANDOM:n}' => 'Random alphanumeric, n chars',
            '{ITEM}' => 'Item number within lot',
            '{PROJECT}' => 'Project code',
        ];
    }

    /**
     * Get sector prefixes.
     */
    public function getSectorPrefixes(): array
    {
        return [
            'archive' => 'ARCH',
            'library' => 'LIB',
            'museum' => 'MUS',
            'gallery' => 'GAL',
            'dam' => 'DAM',
        ];
    }

    /**
     * Check if auto-generation is enabled for a sector.
     */
    public function isAutoGenerateEnabled(string $sector, ?int $repositoryId = null): bool
    {
        $scheme = $this->getDefaultScheme($sector, $repositoryId);
        if (!$scheme) {
            return false;
        }

        return (bool) ($scheme->auto_generate ?? true);
    }

    /**
     * Check if manual override is allowed for a sector.
     */
    public function isManualOverrideAllowed(string $sector, ?int $repositoryId = null): bool
    {
        $scheme = $this->getDefaultScheme($sector, $repositoryId);
        if (!$scheme) {
            return true;
        }

        return (bool) ($scheme->allow_manual_override ?? true);
    }

    /**
     * Get numbering info for a sector (for forms).
     */
    public function getNumberingInfo(string $sector, array $context = [], ?int $repositoryId = null): array
    {
        $scheme = $this->getDefaultScheme($sector, $repositoryId);

        if (!$scheme) {
            return [
                'enabled' => false,
                'auto_generate' => false,
                'allow_override' => true,
                'next_reference' => null,
                'pattern' => null,
                'scheme_name' => null,
            ];
        }

        $this->context = $context;

        return [
            'enabled' => (bool) $scheme->is_active,
            'auto_generate' => (bool) ($scheme->auto_generate ?? true),
            'allow_override' => (bool) ($scheme->allow_manual_override ?? true),
            'next_reference' => $this->previewNextReference($sector, $context, $repositoryId),
            'pattern' => $scheme->pattern,
            'scheme_name' => $scheme->name,
        ];
    }

    /**
     * Generate and reserve identifier for new record.
     * Call this when saving a new record.
     */
    public function generateForNewRecord(string $sector, array $context = [], ?int $repositoryId = null): ?string
    {
        if (!$this->isAutoGenerateEnabled($sector, $repositoryId)) {
            return null;
        }

        return $this->getNextReference($sector, $context, $repositoryId);
    }

    /**
     * Map display standard to sector code.
     */
    public function getSectorFromDisplayStandard(?int $displayStandardId): ?string
    {
        if (!$displayStandardId) {
            return null;
        }

        // Get the term name for this display standard
        $term = DB::table('term_i18n')
            ->where('id', $displayStandardId)
            ->where('culture', 'en')
            ->value('name');

        if (!$term) {
            return null;
        }

        $term = strtolower($term);

        // Map term to sector
        $mapping = [
            'isad' => 'archive',
            'isad(g)' => 'archive',
            'rad' => 'archive',
            'dacs' => 'archive',
            'dc' => 'archive',
            'mods' => 'archive',
            'museum' => 'museum',
            'cco' => 'museum',
            'spectrum' => 'museum',
            'library' => 'library',
            'marc' => 'library',
            'gallery' => 'gallery',
            'dam' => 'dam',
            'photo' => 'dam',
        ];

        return $mapping[$term] ?? null;
    }

    // === Private Methods ===

    private function getDefaultScheme(string $sector, ?int $repositoryId = null): ?object
    {
        // Check repository-specific override first
        if ($repositoryId) {
            $override = DB::table('numbering_scheme_repository as nsr')
                ->join('numbering_scheme as ns', 'nsr.scheme_id', '=', 'ns.id')
                ->where('nsr.repository_id', $repositoryId)
                ->where('nsr.is_active', 1)
                ->where('ns.sector', $sector)
                ->where('ns.is_active', 1)
                ->select('ns.*')
                ->first();

            if ($override) {
                return $override;
            }
        }

        // Get default for sector
        return DB::table('numbering_scheme')
            ->where('sector', $sector)
            ->where('is_default', 1)
            ->where('is_active', 1)
            ->first();
    }

    private function checkSequenceReset(object $scheme): void
    {
        if ($scheme->sequence_reset === 'never') {
            return;
        }

        $lastReset = $scheme->last_reset_date ? new \DateTime($scheme->last_reset_date) : null;
        $now = new \DateTime();

        $shouldReset = false;

        if ($scheme->sequence_reset === 'yearly') {
            if (!$lastReset || $lastReset->format('Y') !== $now->format('Y')) {
                $shouldReset = true;
            }
        } elseif ($scheme->sequence_reset === 'monthly') {
            if (!$lastReset || $lastReset->format('Y-m') !== $now->format('Y-m')) {
                $shouldReset = true;
            }
        }

        if ($shouldReset) {
            $this->resetSequence($scheme->id);
        }
    }

    private function getNextSequenceNumber(object $scheme): int
    {
        // If fill_gaps is enabled, find first available gap
        if ($scheme->fill_gaps) {
            $gap = $this->findFirstGap($scheme);
            if ($gap !== null) {
                return $gap;
            }
        }

        return $scheme->current_sequence + 1;
    }

    private function peekNextSequenceNumber(object $scheme): int
    {
        if ($scheme->fill_gaps) {
            $gap = $this->findFirstGap($scheme);
            if ($gap !== null) {
                return $gap;
            }
        }

        return $scheme->current_sequence + 1;
    }

    private function findFirstGap(object $scheme): ?int
    {
        $yearContext = null;
        if ($scheme->sequence_reset === 'yearly') {
            $yearContext = date('Y');
        }

        // Get all used sequences for this scheme/year
        $query = DB::table('numbering_sequence_used')
            ->where('scheme_id', $scheme->id)
            ->orderBy('sequence_number');

        if ($yearContext) {
            $query->where('year_context', $yearContext);
        }

        $used = $query->pluck('sequence_number')->toArray();

        if (empty($used)) {
            return null;
        }

        // Find first gap
        $expected = 1;
        foreach ($used as $seq) {
            if ($seq > $expected) {
                return $expected;
            }
            $expected = $seq + 1;
        }

        return null;
    }

    private function updateSchemeCounter(int $schemeId, int $sequence): void
    {
        DB::table('numbering_scheme')
            ->where('id', $schemeId)
            ->where('current_sequence', '<', $sequence)
            ->update(['current_sequence' => $sequence]);
    }

    private function applyPattern(string $pattern, object $scheme, int $sequence): string
    {
        $result = $pattern;

        // {SEQ:n} - Sequential number with padding
        $result = preg_replace_callback('/\{SEQ:(\d+)\}/', function ($matches) use ($sequence) {
            return str_pad($sequence, (int) $matches[1], '0', STR_PAD_LEFT);
        }, $result);

        // {SEQ} without padding
        $result = str_replace('{SEQ}', (string) $sequence, $result);

        // Date tokens
        $result = str_replace('{YEAR}', date('Y'), $result);
        $result = str_replace('{YY}', date('y'), $result);
        $result = str_replace('{MONTH}', date('m'), $result);
        $result = str_replace('{DAY}', date('d'), $result);

        // Prefix
        $prefixes = $this->getSectorPrefixes();
        $prefix = $prefixes[$scheme->sector] ?? strtoupper($scheme->sector);
        $result = str_replace('{PREFIX}', $prefix, $result);

        // Context-based tokens
        $result = str_replace('{REPO}', $this->context['repo'] ?? 'REPO', $result);
        $result = str_replace('{FONDS}', $this->context['fonds'] ?? '', $result);
        $result = str_replace('{SERIES}', $this->context['series'] ?? '', $result);
        $result = str_replace('{COLLECTION}', $this->context['collection'] ?? '', $result);
        $result = str_replace('{DEPT}', $this->context['dept'] ?? '', $result);
        $result = str_replace('{TYPE}', $this->context['type'] ?? 'DOC', $result);
        $result = str_replace('{PROJECT}', $this->context['project'] ?? 'PROJECT', $result);
        $result = str_replace('{ITEM}', $this->context['item'] ?? '1', $result);

        // UUID (short)
        $result = str_replace('{UUID}', substr(bin2hex(random_bytes(4)), 0, 8), $result);

        // {RANDOM:n}
        $result = preg_replace_callback('/\{RANDOM:(\d+)\}/', function ($matches) {
            $length = (int) $matches[1];
            $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
            $random = '';
            for ($i = 0; $i < $length; $i++) {
                $random .= $chars[random_int(0, strlen($chars) - 1)];
            }

            return $random;
        }, $result);

        // Clean up empty segments (e.g., // or ..)
        $result = preg_replace('/\/+/', '/', $result);
        $result = preg_replace('/\.+/', '.', $result);
        $result = preg_replace('/-+/', '-', $result);
        $result = trim($result, '/-.');

        return $result;
    }

    private function getSimpleSequence(string $sector): int
    {
        $key = 'numbering_simple_' . $sector;
        $setting = SettingService::getByName($key);

        return $setting ? (int) $setting->getValue(['sourceCulture' => true]) : 0;
    }
}
