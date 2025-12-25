<?php

declare(strict_types=1);

namespace TheAHG\Archive\Models;

/**
 * User Security Clearance Model.
 *
 * Links users to their assigned security clearance level
 * Maps to user_security_clearance table
 */
class UserSecurityClearance
{
    public int $id;
    public int $userId;
    public int $classificationId;
    public ?int $grantedBy;
    public ?string $grantedAt;
    public ?string $expiresAt;
    public ?string $notes;

    // Joined data
    public ?string $classificationCode;
    public ?string $classificationName;
    public ?int $classificationLevel;
    public ?string $classificationColor;
    public ?string $classificationIcon;
    public ?string $grantedByUsername;
    public ?string $username;
    public ?string $userEmail;

    /**
     * Create from database row.
     */
    public static function fromArray(array $data): self
    {
        $instance = new self();
        $instance->id = (int) ($data['id'] ?? 0);
        $instance->userId = (int) ($data['user_id'] ?? 0);
        $instance->classificationId = (int) ($data['classification_id'] ?? 0);
        $instance->grantedBy = isset($data['granted_by']) ? (int) $data['granted_by'] : null;
        $instance->grantedAt = $data['granted_at'] ?? null;
        $instance->expiresAt = $data['expires_at'] ?? null;
        $instance->notes = $data['notes'] ?? null;

        // Joined classification data
        $instance->classificationCode = $data['classification_code'] ?? $data['code'] ?? null;
        $instance->classificationName = $data['classification_name'] ?? $data['sc_name'] ?? null;
        $instance->classificationLevel = isset($data['classification_level']) || isset($data['level'])
            ? (int) ($data['classification_level'] ?? $data['level'])
            : null;
        $instance->classificationColor = $data['classification_color'] ?? $data['color'] ?? null;
        $instance->classificationIcon = $data['classification_icon'] ?? $data['icon'] ?? null;

        // Joined user data
        $instance->grantedByUsername = $data['granted_by_username'] ?? null;
        $instance->username = $data['username'] ?? null;
        $instance->userEmail = $data['email'] ?? null;

        return $instance;
    }

    /**
     * Check if clearance is expired.
     */
    public function isExpired(): bool
    {
        if (null === $this->expiresAt) {
            return false;
        }

        return strtotime($this->expiresAt) < time();
    }

    /**
     * Check if clearance expires within given days.
     */
    public function expiresWithinDays(int $days): bool
    {
        if (null === $this->expiresAt) {
            return false;
        }

        $expiryTime = strtotime($this->expiresAt);
        $warningTime = strtotime("+{$days} days");

        return $expiryTime <= $warningTime && $expiryTime > time();
    }

    /**
     * Get Bootstrap badge class.
     */
    public function getBadgeClass(): string
    {
        if ($this->isExpired()) {
            return 'bg-secondary';
        }

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
