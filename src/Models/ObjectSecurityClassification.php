<?php

declare(strict_types=1);

namespace AtomFramework\Models;

/**
 * Object Security Classification Model.
 *
 * Links information objects (archival descriptions) to security classifications
 * Maps to object_security_classification table
 */
class ObjectSecurityClassification
{
    public int $id;
    public int $objectId;
    public int $classificationId;
    public ?int $classifiedBy;
    public ?string $classifiedAt;
    public ?string $reviewDate;
    public ?string $declassifyDate;
    public ?int $declassifyToId;
    public ?string $reason;
    public ?string $handlingInstructions;
    public bool $inheritToChildren;
    public bool $active;
    public ?string $createdAt;
    public ?string $updatedAt;

    // Joined data
    public ?string $classificationCode;
    public ?string $classificationName;
    public ?int $classificationLevel;
    public ?string $classificationColor;
    public ?string $classificationIcon;
    public ?string $classifiedByUsername;
    public ?string $objectTitle;
    public ?string $objectIdentifier;

    /**
     * Create from database row.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->id = (int) ($data['id'] ?? 0);
        $instance->objectId = (int) ($data['object_id'] ?? 0);
        $instance->classificationId = (int) ($data['classification_id'] ?? 0);
        $instance->classifiedBy = isset($data['classified_by']) ? (int) $data['classified_by'] : null;
        $instance->classifiedAt = $data['classified_at'] ?? null;
        $instance->reviewDate = $data['review_date'] ?? null;
        $instance->declassifyDate = $data['declassify_date'] ?? null;
        $instance->declassifyToId = isset($data['declassify_to_id']) ? (int) $data['declassify_to_id'] : null;
        $instance->reason = $data['reason'] ?? null;
        $instance->handlingInstructions = $data['handling_instructions'] ?? null;
        $instance->inheritToChildren = (bool) ($data['inherit_to_children'] ?? true);
        $instance->active = (bool) ($data['active'] ?? true);
        $instance->createdAt = $data['created_at'] ?? null;
        $instance->updatedAt = $data['updated_at'] ?? null;

        // Joined classification data
        $instance->classificationCode = $data['classification_code'] ?? $data['code'] ?? null;
        $instance->classificationName = $data['classification_name'] ?? $data['sc_name'] ?? null;
        $instance->classificationLevel = isset($data['classification_level']) || isset($data['level'])
            ? (int) ($data['classification_level'] ?? $data['level'])
            : null;
        $instance->classificationColor = $data['classification_color'] ?? $data['color'] ?? null;
        $instance->classificationIcon = $data['classification_icon'] ?? $data['icon'] ?? null;

        // Joined user data
        $instance->classifiedByUsername = $data['classified_by_username'] ?? null;

        // Joined object data
        $instance->objectTitle = $data['object_title'] ?? $data['title'] ?? null;
        $instance->objectIdentifier = $data['object_identifier'] ?? $data['identifier'] ?? null;

        return $instance;
    }

    /**
     * Check if classification is due for review.
     */
    public function isDueForReview(): bool
    {
        if (null === $this->reviewDate) {
            return false;
        }

        return strtotime($this->reviewDate) <= time();
    }

    /**
     * Check if declassification is due.
     */
    public function isDueForDeclassification(): bool
    {
        if (null === $this->declassifyDate) {
            return false;
        }

        return strtotime($this->declassifyDate) <= time();
    }

    /**
     * Get Bootstrap badge class.
     */
    public function getBadgeClass(): string
    {
        return match ($this->classificationLevel) {
            0 => 'bg-success',
            1 => 'bg-info',
            2 => 'bg-warning text-dark',
            3 => 'bg-orange',
            4 => 'bg-danger',
            5 => 'bg-purple',
            default => 'bg-secondary',
        };
    }
}
