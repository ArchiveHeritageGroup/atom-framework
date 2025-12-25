<?php

declare(strict_types=1);

namespace AtomExtensions\Services;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * OAI Service - Replaces QubitOai.
 *
 * Provides OAI-PMH functionality using Laravel Query Builder.
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class OaiService
{
    /**
     * Get repository identifier.
     *
     * Replaces: QubitOai::getRepositoryIdentifier()
     */
    public static function getRepositoryIdentifier(): string
    {
        $code = SettingService::getValue('oai_repository_code');

        if (!empty($code)) {
            return $code;
        }

        // Generate from site URL
        $siteUrl = SettingService::getValue('siteBaseUrl');
        if ($siteUrl) {
            $parsed = parse_url($siteUrl);

            return $parsed['host'] ?? 'localhost';
        }

        return 'localhost';
    }

    /**
     * Get sample OAI identifier.
     *
     * Replaces: QubitOai::getOaiSampleIdentifier()
     */
    public static function getOaiSampleIdentifier(): string
    {
        $repositoryId = self::getRepositoryIdentifier();

        // Get first information object slug
        $sample = DB::table('information_object as io')
            ->join('slug as s', 'io.id', '=', 's.object_id')
            ->where('io.id', '!=', 1) // Exclude root
            ->orderBy('io.id')
            ->select('s.slug')
            ->first();

        $slug = $sample?->slug ?? 'example-record';

        return 'oai:' . $repositoryId . ':' . $slug;
    }

    /**
     * Get OAI admin emails.
     */
    public static function getAdminEmails(): array
    {
        $emails = SettingService::getValue('oai_admin_emails');

        if (!$emails) {
            return [];
        }

        return array_map('trim', explode(',', $emails));
    }

    /**
     * Check if OAI authentication is enabled.
     */
    public static function isAuthenticationEnabled(): bool
    {
        return SettingService::getValue('oai_authentication_enabled') === '1';
    }

    /**
     * Get resumption token limit.
     */
    public static function getResumptionTokenLimit(): int
    {
        $limit = SettingService::getValue('resumption_token_limit');

        return (int) ($limit ?: 100);
    }

    /**
     * Check if additional sets are enabled.
     */
    public static function areAdditionalSetsEnabled(): bool
    {
        return SettingService::getValue('oai_additional_sets_enabled') === '1';
    }

    /**
     * Create resumption token.
     */
    public static function createResumptionToken(array $params): string
    {
        $token = base64_encode(json_encode([
            'params' => $params,
            'created' => time(),
            'expires' => time() + 86400, // 24 hours
        ]));

        return $token;
    }

    /**
     * Parse resumption token.
     */
    public static function parseResumptionToken(string $token): ?array
    {
        try {
            $data = json_decode(base64_decode($token), true);

            if (!$data || $data['expires'] < time()) {
                return null;
            }

            return $data['params'];
        } catch (\Exception $e) {
            return null;
        }
    }
}
