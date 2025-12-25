<?php

declare(strict_types=1);

namespace AtomFramework\Extensions\Contact\Services;

use AtomFramework\Extensions\Contact\Repositories\ContactInformationRepository;
use Illuminate\Support\Collection;

/**
 * Service for contact information on authority records
 */
class ContactInformationService
{
    protected ContactInformationRepository $repository;

    public function __construct()
    {
        $this->repository = new ContactInformationRepository();
    }

    /**
     * Get contacts for actor
     */
    public function getForActor(int $actorId): Collection
    {
        return $this->repository->getByActorId($actorId);
    }

    /**
     * Get primary contact
     */
    public function getPrimary(int $actorId): ?object
    {
        return $this->repository->getPrimaryContact($actorId);
    }

    /**
     * Add contact to actor
     */
    public function add(int $actorId, array $data): int
    {
        $data['actor_id'] = $actorId;
        return $this->repository->create($data);
    }

    /**
     * Update contact
     */
    public function update(int $id, array $data): bool
    {
        return $this->repository->update($id, $data);
    }

    /**
     * Delete contact
     */
    public function delete(int $id): bool
    {
        return $this->repository->delete($id);
    }

    /**
     * Set as primary
     */
    public function setPrimary(int $actorId, int $contactId): bool
    {
        return $this->repository->update($contactId, [
            'actor_id' => $actorId,
            'primary_contact' => 1,
        ]);
    }

    /**
     * Format address for display
     */
    public function formatAddress(object $contact): string
    {
        $lines = [];

        if (!empty($contact->street_address)) {
            $lines[] = $contact->street_address;
        }

        $cityParts = array_filter([
            $contact->city ?? null,
            $contact->region ?? null,
            $contact->postal_code ?? null,
        ]);
        if (!empty($cityParts)) {
            $lines[] = implode(', ', $cityParts);
        }

        if (!empty($contact->country_code)) {
            $lines[] = $contact->country_code;
        }

        return implode("\n", $lines);
    }

    /**
     * Has contacts
     */
    public function hasContacts(int $actorId): bool
    {
        return $this->repository->hasContacts($actorId);
    }
}
