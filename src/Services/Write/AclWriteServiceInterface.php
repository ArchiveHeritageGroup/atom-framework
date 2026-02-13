<?php

namespace AtomFramework\Services\Write;

/**
 * Contract for ACL / permission-related persistence.
 *
 * The permissions handler in ahgSettingsPlugin stores permission
 * settings (PREMIS rights, copyright statements, access statements)
 * as QubitSetting objects. This interface wraps those writes.
 *
 * Future expansion: when true ACL management (QubitAclPermission,
 * QubitAclGroup) moves to Heratio, additional methods can be added.
 */
interface AclWriteServiceInterface
{
    /**
     * Save PREMIS access-right and access-right-values settings.
     *
     * @param array $rights      Serialized rights array
     * @param array $rightValues Serialized right-values array
     */
    public function savePremisRights(array $rights, array $rightValues): void;

    /**
     * Save or delete access statement settings.
     *
     * @param array $statements Array of ['name' => string, 'value' => string|null]
     *                          If value is null/empty, the setting is deleted.
     */
    public function saveAccessStatements(array $statements): void;

    /**
     * Save copyright statement configuration.
     *
     * @param bool        $enabled       Whether copyright statement is enabled
     * @param string|null $text          Copyright statement text (localized)
     * @param bool        $applyGlobally Whether to apply globally
     * @param string      $culture       Culture for localized text
     */
    public function saveCopyrightStatement(
        bool $enabled,
        ?string $text,
        bool $applyGlobally,
        string $culture = 'en'
    ): void;

    /**
     * Save preservation system access statement.
     *
     * @param bool        $enabled Whether preservation access statement is enabled
     * @param string|null $text    Statement text (localized)
     * @param string      $culture Culture for localized text
     */
    public function savePreservationStatement(
        bool $enabled,
        ?string $text,
        string $culture = 'en'
    ): void;
}
