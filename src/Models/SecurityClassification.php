<?php

declare(strict_types=1);

namespace TheAHG\Archive\Models;

/**
 * Security Classification Level Model.
 *
 * Represents the classification levels (PUBLIC, RESTRICTED, CONFIDENTIAL, SECRET, TOP_SECRET)
 * Maps to security_classification table
 */
class SecurityClassification
{
    public int $id;
    public string $code;
    public string $name;
    public int $level;
    public ?string $description;
    public string $color;
    public string $icon;
    public bool $requiresJustification;
    public bool $requiresApproval;
    public bool $requires2fa;
    public bool $watermarkRequired;
    public bool $downloadAllowed;
    public bool $printAllowed;
    public bool $copyAllowed;
    public bool $active;
    public ?string $createdAt;
    public ?string $updatedAt;

    /**
     * Classification level constants.
     */
    public const LEVEL_PUBLIC = 0;
    public const LEVEL_INTERNAL = 1;
    public const LEVEL_RESTRICTED = 2;
    public const LEVEL_CONFIDENTIAL = 3;
    public const LEVEL_SECRET = 4;
    public const LEVEL_TOP_SECRET = 5;

    /**
     * Code constants.
     */
    public const CODE_PUBLIC = 'PUBLIC';
    public const CODE_INTERNAL = 'INTERNAL';
    public const CODE_RESTRICTED = 'RESTRICTED';
    public const CODE_CONFIDENTIAL = 'CONFIDENTIAL';
    public const CODE_SECRET = 'SECRET';
    public const CODE_TOP_SECRET = 'TOP_SECRET';

    /**
     * Create from database row.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->id = (int) ($data['id'] ?? 0);
        $instance->code = $data['code'] ?? '';
        $instance->name = $data['name'] ?? '';
        $instance->level = (int) ($data['level'] ?? 0);
        $instance->description = $data['description'] ?? null;
        $instance->color = $data['color'] ?? '#666666';
        $instance->icon = $data['icon'] ?? 'fa-lock';
        $instance->requiresJustification = (bool) ($data['requires_justification'] ?? false);
        $instance->requiresApproval = (bool) ($data['requires_approval'] ?? false);
        $instance->requires2fa = (bool) ($data['requires_2fa'] ?? false);
        $instance->watermarkRequired = (bool) ($data['watermark_required'] ?? false);
        $instance->downloadAllowed = (bool) ($data['download_allowed'] ?? true);
        $instance->printAllowed = (bool) ($data['print_allowed'] ?? true);
        $instance->copyAllowed = (bool) ($data['copy_allowed'] ?? true);
        $instance->active = (bool) ($data['active'] ?? true);
        $instance->createdAt = $data['created_at'] ?? null;
        $instance->updatedAt = $data['updated_at'] ?? null;

        return $instance;
    }

    /**
     * Get Bootstrap badge class based on level.
     */
    public function getBadgeClass(): string
    {
        return match ($this->level) {
            self::LEVEL_PUBLIC => 'bg-success',
            self::LEVEL_INTERNAL => 'bg-info',
            self::LEVEL_RESTRICTED => 'bg-warning text-dark',
            self::LEVEL_CONFIDENTIAL => 'bg-orange',
            self::LEVEL_SECRET => 'bg-danger',
            self::LEVEL_TOP_SECRET => 'bg-purple',
            default => 'bg-secondary',
        };
    }

    /**
     * Check if this level can access another level.
     */
    public function canAccess(int $targetLevel): bool
    {
        return $this->level >= $targetLevel;
    }
}
