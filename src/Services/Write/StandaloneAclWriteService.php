<?php

namespace AtomFramework\Services\Write;

/**
 * Standalone ACL write service.
 *
 * Delegates entirely to SettingsWriteServiceInterface for permission
 * persistence (PREMIS rights, copyright statements, access statements).
 * No Propel references or class_exists checks.
 */
class StandaloneAclWriteService implements AclWriteServiceInterface
{
    private SettingsWriteServiceInterface $settings;

    public function __construct(?SettingsWriteServiceInterface $settings = null)
    {
        $this->settings = $settings ?? new StandaloneSettingsWriteService();
    }

    public function savePremisRights(array $rights, array $rightValues): void
    {
        $this->settings->saveSerialized('premisAccessRight', $rights);
        $this->settings->saveSerialized('premisAccessRightValues', $rightValues);
    }

    public function saveAccessStatements(array $statements): void
    {
        foreach ($statements as $statement) {
            $name = $statement['name'] ?? null;
            if (null === $name) {
                continue;
            }

            $value = $statement['value'] ?? null;
            if (empty($value)) {
                $this->settings->delete($name);
            } else {
                $this->settings->save($name, $value);
            }
        }
    }

    public function saveCopyrightStatement(
        bool $enabled,
        ?string $text,
        bool $applyGlobally,
        string $culture = 'en'
    ): void {
        $this->settings->save('copyrightStatementEnabled', $enabled ? '1' : '0');

        if (null !== $text) {
            $this->settings->saveLocalized('copyrightStatement', $text, $culture);
        }

        $this->settings->save('copyrightStatementApplyGlobally', $applyGlobally ? '1' : '0');
    }

    public function savePreservationStatement(
        bool $enabled,
        ?string $text,
        string $culture = 'en'
    ): void {
        $this->settings->save('preservationSystemAccessStatementEnabled', $enabled ? '1' : '0');

        if (null !== $text) {
            $this->settings->saveLocalized('preservationSystemAccessStatement', $text, $culture);
        }
    }
}
