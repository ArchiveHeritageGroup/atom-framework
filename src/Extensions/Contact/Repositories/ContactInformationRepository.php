<?php

declare(strict_types=1);

namespace AtomFramework\Extensions\Contact\Repositories;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Repository for contact information - extends to all actors (authority records + repositories)
 * Handles three tables:
 *   - contact_information (main fields)
 *   - contact_information_i18n (translatable: city, region, contact_type, note)
 *   - contact_information_extended (custom AHG fields)
 */
class ContactInformationRepository
{
    protected string $table = 'contact_information';
    protected string $i18nTable = 'contact_information_i18n';
    protected string $extendedTable = 'contact_information_extended';

    /**
     * Get all contacts for an actor with i18n and extended fields.
     */
    public function getByActorId(int $actorId, string $culture = 'en'): Collection
    {
        return DB::table($this->table . ' as ci')
            ->leftJoin($this->i18nTable . ' as cii', function ($join) use ($culture) {
                $join->on('ci.id', '=', 'cii.id')
                     ->where('cii.culture', '=', $culture);
            })
            ->leftJoin($this->extendedTable . ' as cie', 'ci.id', '=', 'cie.contact_information_id')
            ->where('ci.actor_id', $actorId)
            ->orderBy('ci.primary_contact', 'desc')
            ->select([
                'ci.*',
                // i18n fields
                'cii.city',
                'cii.region',
                'cii.contact_type',
                'cii.note',
                // Extended fields
                'cie.title',
                'cie.role',
                'cie.department',
                'cie.cell',
                'cie.id_number',
                'cie.alternative_email',
                'cie.alternative_phone',
                'cie.preferred_contact_method',
                'cie.language_preference',
                'cie.notes as extended_notes',
            ])
            ->get();
    }

    /**
     * Get primary contact.
     */
    public function getPrimaryContact(int $actorId, string $culture = 'en'): ?object
    {
        return DB::table($this->table . ' as ci')
            ->leftJoin($this->i18nTable . ' as cii', function ($join) use ($culture) {
                $join->on('ci.id', '=', 'cii.id')
                     ->where('cii.culture', '=', $culture);
            })
            ->leftJoin($this->extendedTable . ' as cie', 'ci.id', '=', 'cie.contact_information_id')
            ->where('ci.actor_id', $actorId)
            ->where('ci.primary_contact', 1)
            ->select([
                'ci.*',
                'cii.city',
                'cii.region',
                'cii.contact_type',
                'cii.note',
                'cie.title',
                'cie.role',
                'cie.department',
                'cie.cell',
                'cie.id_number',
                'cie.alternative_email',
                'cie.alternative_phone',
                'cie.preferred_contact_method',
                'cie.language_preference',
                'cie.notes as extended_notes',
            ])
            ->first();
    }

    /**
     * Get by ID.
     */
    public function getById(int $id, string $culture = 'en'): ?object
    {
        return DB::table($this->table . ' as ci')
            ->leftJoin($this->i18nTable . ' as cii', function ($join) use ($culture) {
                $join->on('ci.id', '=', 'cii.id')
                     ->where('cii.culture', '=', $culture);
            })
            ->leftJoin($this->extendedTable . ' as cie', 'ci.id', '=', 'cie.contact_information_id')
            ->where('ci.id', $id)
            ->select([
                'ci.*',
                'cii.city',
                'cii.region',
                'cii.contact_type',
                'cii.note',
                'cie.title',
                'cie.role',
                'cie.department',
                'cie.cell',
                'cie.id_number',
                'cie.alternative_email',
                'cie.alternative_phone',
                'cie.preferred_contact_method',
                'cie.language_preference',
                'cie.notes as extended_notes',
            ])
            ->first();
    }

    /**
     * Create contact with i18n and extended data.
     */
    public function create(array $data): int
    {
        $culture = $data['source_culture'] ?? 'en';

        if (!empty($data['primary_contact'])) {
            $this->clearPrimaryContact((int) $data['actor_id']);
        }

        // Insert main contact
        $contactId = DB::table($this->table)->insertGetId([
            'actor_id' => $data['actor_id'],
            'primary_contact' => $data['primary_contact'] ?? 0,
            'contact_person' => $data['contact_person'] ?? null,
            'street_address' => $data['street_address'] ?? null,
            'country_code' => $data['country_code'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'telephone' => $data['telephone'] ?? null,
            'fax' => $data['fax'] ?? null,
            'email' => $data['email'] ?? null,
            'website' => $data['website'] ?? null,
            'latitude' => !empty($data['latitude']) ? (float) $data['latitude'] : null,
            'longitude' => !empty($data['longitude']) ? (float) $data['longitude'] : null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'source_culture' => $culture,
        ]);

        // Insert i18n data
        $this->createI18n($contactId, $data, $culture);

        // Insert extended data
        $this->createExtended($contactId, $data);

        return $contactId;
    }

    /**
     * Create i18n record.
     */
    protected function createI18n(int $contactId, array $data, string $culture): void
    {
        DB::table($this->i18nTable)->insert([
            'id' => $contactId,
            'culture' => $culture,
            'city' => $data['city'] ?? null,
            'region' => $data['region'] ?? null,
            'contact_type' => $data['contact_type'] ?? null,
            'note' => $data['note'] ?? null,
        ]);
    }

    /**
     * Create extended contact record.
     */
    protected function createExtended(int $contactId, array $data): void
    {
        DB::table($this->extendedTable)->insert([
            'contact_information_id' => $contactId,
            'title' => $data['title'] ?? null,
            'role' => $data['role'] ?? null,
            'department' => $data['department'] ?? null,
            'cell' => $data['cell'] ?? null,
            'id_number' => $data['id_number'] ?? null,
            'alternative_email' => $data['alternative_email'] ?? null,
            'alternative_phone' => $data['alternative_phone'] ?? null,
            'preferred_contact_method' => !empty($data['preferred_contact_method']) ? $data['preferred_contact_method'] : null,
            'language_preference' => $data['language_preference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Update contact with i18n and extended data.
     */
    public function update(int $id, array $data): bool
    {
        $contact = DB::table($this->table)->where('id', $id)->first();
        if (!$contact) {
            return false;
        }

        $culture = $data['source_culture'] ?? 'en';

        if (!empty($data['primary_contact'])) {
            $this->clearPrimaryContact((int) $contact->actor_id, $id);
        }

        // Update main contact
        $mainData = [
            'primary_contact' => $data['primary_contact'] ?? 0,
            'contact_person' => $data['contact_person'] ?? null,
            'street_address' => $data['street_address'] ?? null,
            'country_code' => $data['country_code'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'telephone' => $data['telephone'] ?? null,
            'fax' => $data['fax'] ?? null,
            'email' => $data['email'] ?? null,
            'website' => $data['website'] ?? null,
            'latitude' => !empty($data['latitude']) ? (float) $data['latitude'] : null,
            'longitude' => !empty($data['longitude']) ? (float) $data['longitude'] : null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        DB::table($this->table)
            ->where('id', $id)
            ->update($mainData);

        // Update i18n data
        $this->updateI18n($id, $data, $culture);

        // Update extended data
        $this->updateExtended($id, $data);

        return true;
    }

    /**
     * Update i18n record.
     */
    protected function updateI18n(int $contactId, array $data, string $culture): void
    {
        $i18nData = [
            'city' => $data['city'] ?? null,
            'region' => $data['region'] ?? null,
            'contact_type' => $data['contact_type'] ?? null,
            'note' => $data['note'] ?? null,
        ];

        // Check if i18n record exists for this culture
        $exists = DB::table($this->i18nTable)
            ->where('id', $contactId)
            ->where('culture', $culture)
            ->exists();

        if ($exists) {
            DB::table($this->i18nTable)
                ->where('id', $contactId)
                ->where('culture', $culture)
                ->update($i18nData);
        } else {
            $i18nData['id'] = $contactId;
            $i18nData['culture'] = $culture;
            DB::table($this->i18nTable)->insert($i18nData);
        }
    }

    /**
     * Update extended contact record.
     */
    protected function updateExtended(int $contactId, array $data): void
    {
        $extendedData = [
            'title' => $data['title'] ?? null,
            'role' => $data['role'] ?? null,
            'department' => $data['department'] ?? null,
            'cell' => $data['cell'] ?? null,
            'id_number' => $data['id_number'] ?? null,
            'alternative_email' => $data['alternative_email'] ?? null,
            'alternative_phone' => $data['alternative_phone'] ?? null,
            'preferred_contact_method' => !empty($data['preferred_contact_method']) ? $data['preferred_contact_method'] : null,
            'language_preference' => $data['language_preference'] ?? null,
            'notes' => $data['notes'] ?? null,
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        // Check if extended record exists
        $exists = DB::table($this->extendedTable)
            ->where('contact_information_id', $contactId)
            ->exists();

        if ($exists) {
            DB::table($this->extendedTable)
                ->where('contact_information_id', $contactId)
                ->update($extendedData);
        } else {
            $extendedData['contact_information_id'] = $contactId;
            $extendedData['created_at'] = date('Y-m-d H:i:s');
            DB::table($this->extendedTable)->insert($extendedData);
        }
    }

    /**
     * Delete contact.
     */
    public function delete(int $id): bool
    {
        // Delete extended first
        DB::table($this->extendedTable)
            ->where('contact_information_id', $id)
            ->delete();

        // Delete i18n records
        DB::table($this->i18nTable)
            ->where('id', $id)
            ->delete();

        // Delete main record
        return DB::table($this->table)
            ->where('id', $id)
            ->delete() > 0;
    }

    /**
     * Clear primary contact flag for actor.
     */
    protected function clearPrimaryContact(int $actorId, ?int $exceptId = null): void
    {
        $query = DB::table($this->table)
            ->where('actor_id', $actorId);

        if ($exceptId) {
            $query->where('id', '!=', $exceptId);
        }

        $query->update(['primary_contact' => 0]);
    }

    /**
     * Check if actor has contacts.
     */
    public function hasContacts(int $actorId): bool
    {
        return DB::table($this->table)
            ->where('actor_id', $actorId)
            ->exists();
    }

    /**
     * Count contacts for actor.
     */
    public function countByActorId(int $actorId): int
    {
        return DB::table($this->table)
            ->where('actor_id', $actorId)
            ->count();
    }

    /**
     * Save contact from form data (create or update).
     */
    public function saveFromForm(array $data): int
    {
        $contactId = !empty($data['id']) ? (int) $data['id'] : 0;

        if ($contactId > 0) {
            $this->update($contactId, $data);
            return $contactId;
        } else {
            return $this->create($data);
        }
    }
}
