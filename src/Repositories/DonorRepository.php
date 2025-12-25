<?php

declare(strict_types=1);

namespace AtomExtensions\Repositories;

use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Support\Collection;

/**
 * Donor Repository - Complete CRUD operations.
 *
 * Donors extend the actor class hierarchy (object → actor → donor).
 *
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class DonorRepository
{
    protected string $culture;

    public function __construct(string $culture = 'en')
    {
        $this->culture = $culture;
    }

    /**
     * Set the culture for queries.
     */
    public function setCulture(string $culture): self
    {
        $this->culture = $culture;

        return $this;
    }

    /**
     * Get a single donor by ID with all related data.
     */
    public function findById(int $id): ?object
    {
        $donor = DB::table('donor as d')
            ->join('actor as a', 'd.id', '=', 'a.id')
            ->join('object as o', 'a.id', '=', 'o.id')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('a.id', '=', 'ai.id')
                     ->where('ai.culture', '=', $this->culture);
            })
            ->leftJoin('actor_i18n as ai_fallback', function ($join) {
                $join->on('a.id', '=', 'ai_fallback.id')
                     ->on('ai_fallback.culture', '=', 'a.source_culture');
            })
            ->where('d.id', $id)
            ->where('o.class_name', 'QubitDonor')
            ->select(
                'd.id',
                DB::raw('COALESCE(ai.authorized_form_of_name, ai_fallback.authorized_form_of_name) as authorizedFormOfName'),
                DB::raw('COALESCE(ai.dates_of_existence, ai_fallback.dates_of_existence) as datesOfExistence'),
                DB::raw('COALESCE(ai.history, ai_fallback.history) as history'),
                'a.description_identifier as descriptionIdentifier',
                'a.entity_type_id as entityTypeId',
                'a.source_culture as sourceCulture',
                'a.parent_id as parentId',
                'o.class_name as className',
                'o.serial_number as serialNumber',
                'o.created_at as createdAt',
                'o.updated_at as updatedAt',
                DB::raw('COALESCE(ai.culture, ai_fallback.culture) as culture')
            )
            ->first();

        if (!$donor) {
            return null;
        }

        // Get contact information with extended data
        $donor->contactInformations = $this->getContactInformations($id);

        // Get related accessions
        $donor->relatedAccessions = $this->getRelatedAccessions($id);

        // Get related donor agreements
        $donor->donorAgreements = $this->getDonorAgreements($id);

        // Generate slug
        $donor->slug = $this->getSlug($id);

        return $donor;
    }

    /**
     * Get donor by slug.
     */
    public function findBySlug(string $slug): ?object
    {
        $slugData = DB::table('slug')
            ->where('slug', $slug)
            ->first();

        if (!$slugData) {
            return null;
        }

        return $this->findById($slugData->object_id);
    }

    /**
     * Get contact informations for a donor/actor with extended data.
     */
    public function getContactInformations(int $actorId): Collection
    {
        return DB::table('contact_information as ci')
            ->leftJoin('contact_information_i18n as cii', function ($join) {
                $join->on('ci.id', '=', 'cii.id')
                     ->where('cii.culture', '=', $this->culture);
            })
            ->leftJoin('contact_information_i18n as cii_fallback', function ($join) {
                $join->on('ci.id', '=', 'cii_fallback.id')
                     ->on('cii_fallback.culture', '=', 'ci.source_culture');
            })
            ->leftJoin('contact_information_extended as cie', 'ci.id', '=', 'cie.contact_information_id')
            ->where('ci.actor_id', $actorId)
            ->select(
                // Standard AtoM fields
                'ci.id',
                'ci.actor_id as actorId',
                'ci.primary_contact as primaryContact',
                'ci.contact_person as contactPerson',
                'ci.street_address as streetAddress',
                'ci.website',
                'ci.email',
                'ci.telephone',
                'ci.fax',
                'ci.postal_code as postalCode',
                'ci.country_code as countryCode',
                'ci.longitude',
                'ci.latitude',
                'ci.created_at as createdAt',
                'ci.updated_at as updatedAt',
                'ci.serial_number as serialNumber',
                DB::raw('COALESCE(cii.contact_type, cii_fallback.contact_type) as contactType'),
                DB::raw('COALESCE(cii.city, cii_fallback.city) as city'),
                DB::raw('COALESCE(cii.region, cii_fallback.region) as region'),
                DB::raw('COALESCE(cii.note, cii_fallback.note) as note'),
                // Extended fields
                'cie.title',
                'cie.role',
                'cie.department',
                'cie.cell',
                'cie.id_number as idNumber',
                'cie.alternative_email as alternativeEmail',
                'cie.alternative_phone as alternativePhone',
                'cie.preferred_contact_method as preferredContactMethod',
                'cie.language_preference as languagePreference',
                'cie.notes as extendedNotes'
            )
            ->orderByDesc('ci.primary_contact')
            ->orderBy('ci.id')
            ->get();
    }

    /**
     * Get related accessions for a donor.
     */
    public function getRelatedAccessions(int $donorId): Collection
    {
        $donorTermId = DB::table('term')
            ->join('term_i18n as ti', 'term.id', '=', 'ti.id')
            ->where('ti.name', 'Donor')
            ->value('term.id');

        if (!$donorTermId) {
            $donorTermId = 114;
        }

        return DB::table('relation as r')
            ->join('accession as acc', 'r.subject_id', '=', 'acc.id')
            ->join('object as o', 'acc.id', '=', 'o.id')
            ->leftJoin('slug as s', 'acc.id', '=', 's.object_id')
            ->where('r.object_id', $donorId)
            ->where('r.type_id', $donorTermId)
            ->where('o.class_name', 'QubitAccession')
            ->select(
                'acc.id',
                'acc.identifier',
                'acc.date as accessionDate',
                's.slug',
                'o.created_at as createdAt',
                'o.updated_at as updatedAt'
            )
            ->orderByDesc('acc.date')
            ->get();
    }

    /**
     * Get donor agreements for a donor.
     */
    public function getDonorAgreements(int $donorId): Collection
    {
        return DB::table('donor_agreement as da')
            ->leftJoin('term_i18n as ti', function ($join) {
                $join->on('da.agreement_type_id', '=', 'ti.id')
                     ->where('ti.culture', '=', $this->culture);
            })
            ->where('da.donor_id', $donorId)
            ->select(
                'da.id',
                'da.agreement_number as agreementNumber',
                'da.title',
                'ti.name as agreementType',
                'da.status',
                'da.effective_date as startDate',
                'da.expiry_date as endDate',
                'da.created_at as createdAt',
                'da.updated_at as updatedAt'
            )
            ->orderByDesc('da.created_at')
            ->get();
    }

    /**
     * Get slug for an object.
     */
    public function getSlug(int $objectId): ?string
    {
        return DB::table('slug')
            ->where('object_id', $objectId)
            ->value('slug');
    }

    /**
     * Browse donors with pagination and sorting.
     */
    public function browse(array $options = []): array
    {
        $page = $options['page'] ?? 1;
        $limit = $options['limit'] ?? 10;
        $sort = $options['sort'] ?? 'alphabetic';
        $sortDir = $options['sortDir'] ?? 'asc';
        $subquery = $options['subquery'] ?? null;

        $query = DB::table('donor as d')
            ->join('actor as a', 'd.id', '=', 'a.id')
            ->join('object as o', 'a.id', '=', 'o.id')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('a.id', '=', 'ai.id')
                     ->where('ai.culture', '=', $this->culture);
            })
            ->leftJoin('actor_i18n as ai_fallback', function ($join) {
                $join->on('a.id', '=', 'ai_fallback.id')
                     ->on('ai_fallback.culture', '=', 'a.source_culture');
            })
            ->leftJoin('slug as s', 'd.id', '=', 's.object_id')
            ->where('o.class_name', 'QubitDonor');

        if ($subquery) {
            $query->where(function ($q) use ($subquery) {
                $q->where('ai.authorized_form_of_name', 'LIKE', "%{$subquery}%")
                  ->orWhere('ai_fallback.authorized_form_of_name', 'LIKE', "%{$subquery}%");
            });
        }

        $direction = ('desc' === $sortDir) ? 'desc' : 'asc';
        switch ($sort) {
            case 'identifier':
                $query->orderBy('a.description_identifier', $direction);
                $query->orderByRaw('COALESCE(ai.authorized_form_of_name, ai_fallback.authorized_form_of_name) '.$direction);
                break;

            case 'lastUpdated':
                $query->orderBy('o.updated_at', 'desc');
                break;

            case 'alphabetic':
            default:
                $query->orderByRaw('COALESCE(ai.authorized_form_of_name, ai_fallback.authorized_form_of_name) '.$direction);
                break;
        }

        $countQuery = clone $query;
        $total = $countQuery->count();

        $offset = ($page - 1) * $limit;
        $results = $query
            ->select(
                'd.id',
                DB::raw('COALESCE(ai.authorized_form_of_name, ai_fallback.authorized_form_of_name) as authorizedFormOfName'),
                'a.description_identifier as descriptionIdentifier',
                's.slug',
                'o.created_at as createdAt',
                'o.updated_at as updatedAt'
            )
            ->offset($offset)
            ->limit($limit)
            ->get();

        return [
            'results' => $results,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'pages' => (int) ceil($total / $limit),
        ];
    }

    /**
     * Autocomplete search for donors.
     */
    public function autocomplete(string $query, int $limit = 10): Collection
    {
        return DB::table('donor as d')
            ->join('actor as a', 'd.id', '=', 'a.id')
            ->join('object as o', 'a.id', '=', 'o.id')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('a.id', '=', 'ai.id')
                     ->where('ai.culture', '=', $this->culture);
            })
            ->leftJoin('actor_i18n as ai_fallback', function ($join) {
                $join->on('a.id', '=', 'ai_fallback.id')
                     ->on('ai_fallback.culture', '=', 'a.source_culture');
            })
            ->leftJoin('slug as s', 'd.id', '=', 's.object_id')
            ->where('o.class_name', 'QubitDonor')
            ->where(function ($q) use ($query) {
                $q->where('ai.authorized_form_of_name', 'LIKE', "%{$query}%")
                  ->orWhere('ai_fallback.authorized_form_of_name', 'LIKE', "%{$query}%");
            })
            ->select(
                'd.id',
                DB::raw('COALESCE(ai.authorized_form_of_name, ai_fallback.authorized_form_of_name) as authorizedFormOfName'),
                's.slug'
            )
            ->orderByRaw('COALESCE(ai.authorized_form_of_name, ai_fallback.authorized_form_of_name) ASC')
            ->limit($limit)
            ->get();
    }

    /**
     * Create a new donor.
     */
    public function create(array $data): int
    {
        $now = date('Y-m-d H:i:s');
        $culture = $data['culture'] ?? $this->culture;

        // Create object record
        $objectId = DB::table('object')->insertGetId([
            'class_name' => 'QubitDonor',
            'created_at' => $now,
            'updated_at' => $now,
            'serial_number' => 0,
        ]);

        // Create actor record
        DB::table('actor')->insert([
            'id' => $objectId,
            'entity_type_id' => $data['entityTypeId'] ?? null,
            'description_status_id' => $data['descriptionStatusId'] ?? null,
            'description_detail_id' => $data['descriptionDetailId'] ?? null,
            'description_identifier' => $data['descriptionIdentifier'] ?? null,
            'corporate_body_identifiers' => $data['corporateBodyIdentifiers'] ?? null,
            'source_standard' => $data['sourceStandard'] ?? null,
            'parent_id' => $data['parentId'] ?? null,
            'source_culture' => $culture,
        ]);

        // Create actor_i18n record
        DB::table('actor_i18n')->insert([
            'id' => $objectId,
            'culture' => $culture,
            'authorized_form_of_name' => $data['authorizedFormOfName'] ?? null,
            'dates_of_existence' => $data['datesOfExistence'] ?? null,
            'history' => $data['history'] ?? null,
            'places' => $data['places'] ?? null,
            'legal_status' => $data['legalStatus'] ?? null,
            'functions' => $data['functions'] ?? null,
            'mandates' => $data['mandates'] ?? null,
            'internal_structures' => $data['internalStructures'] ?? null,
            'general_context' => $data['generalContext'] ?? null,
        ]);

        // Create donor record
        DB::table('donor')->insert([
            'id' => $objectId,
        ]);

        // Create slug
        $this->createSlug($objectId, $data['authorizedFormOfName'] ?? 'donor');

        return $objectId;
    }

    /**
     * Update an existing donor.
     */
    public function update(int $id, array $data): bool
    {
        $now = date('Y-m-d H:i:s');
        $culture = $data['culture'] ?? $this->culture;

        // Update object
        DB::table('object')
            ->where('id', $id)
            ->update([
                'updated_at' => $now,
                'serial_number' => DB::raw('serial_number + 1'),
            ]);

        // Update actor_i18n
        $existingI18n = DB::table('actor_i18n')
            ->where('id', $id)
            ->where('culture', $culture)
            ->exists();

        $i18nData = [
            'authorized_form_of_name' => $data['authorizedFormOfName'] ?? null,
        ];

        if ($existingI18n) {
            DB::table('actor_i18n')
                ->where('id', $id)
                ->where('culture', $culture)
                ->update($i18nData);
        } else {
            $i18nData['id'] = $id;
            $i18nData['culture'] = $culture;
            DB::table('actor_i18n')->insert($i18nData);
        }

        return true;
    }

    /**
     * Delete a donor and all related records.
     */
    public function delete(int $id): bool
    {
        // Delete relations
        DB::table('relation')
            ->where('subject_id', $id)
            ->orWhere('object_id', $id)
            ->delete();

        // Delete extended contact information
        $contactIds = DB::table('contact_information')
            ->where('actor_id', $id)
            ->pluck('id');

        if ($contactIds->isNotEmpty()) {
            DB::table('contact_information_extended')
                ->whereIn('contact_information_id', $contactIds)
                ->delete();

            DB::table('contact_information_i18n')
                ->whereIn('id', $contactIds)
                ->delete();

            DB::table('contact_information')
                ->where('actor_id', $id)
                ->delete();
        }

        // Delete slug
        DB::table('slug')
            ->where('object_id', $id)
            ->delete();

        // Delete actor_i18n
        DB::table('actor_i18n')
            ->where('id', $id)
            ->delete();

        // Delete donor
        DB::table('donor')
            ->where('id', $id)
            ->delete();

        // Delete actor
        DB::table('actor')
            ->where('id', $id)
            ->delete();

        // Delete object
        DB::table('object')
            ->where('id', $id)
            ->delete();

        return true;
    }

    /**
     * Create a slug for an object.
     */
    protected function createSlug(int $objectId, string $name): string
    {
        $baseSlug = $this->generateSlug($name);
        $slug = $baseSlug;
        $counter = 1;

        while (DB::table('slug')->where('slug', $slug)->exists()) {
            $slug = $baseSlug.'-'.$counter;
            ++$counter;
        }

        DB::table('slug')->insert([
            'object_id' => $objectId,
            'slug' => $slug,
        ]);

        return $slug;
    }

    /**
     * Generate a URL-safe slug from a string.
     */
    protected function generateSlug(string $string): string
    {
        $slug = strtolower($string);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        $slug = trim($slug, '-');

        if (strlen($slug) > 250) {
            $slug = substr($slug, 0, 250);
        }

        return $slug ?: 'donor';
    }

    /**
     * Count total donors.
     */
    public function count(): int
    {
        return DB::table('donor as d')
            ->join('object as o', 'd.id', '=', 'o.id')
            ->where('o.class_name', 'QubitDonor')
            ->count();
    }

    /**
     * Get list of all donors for dropdowns.
     */
    public function getList(): Collection
    {
        return DB::table('donor as d')
            ->join('actor as a', 'd.id', '=', 'a.id')
            ->join('object as o', 'a.id', '=', 'o.id')
            ->leftJoin('actor_i18n as ai', function ($join) {
                $join->on('a.id', '=', 'ai.id')
                     ->where('ai.culture', '=', $this->culture);
            })
            ->leftJoin('actor_i18n as ai_fallback', function ($join) {
                $join->on('a.id', '=', 'ai_fallback.id')
                     ->on('ai_fallback.culture', '=', 'a.source_culture');
            })
            ->leftJoin('slug as s', 'd.id', '=', 's.object_id')
            ->where('o.class_name', 'QubitDonor')
            ->select(
                'd.id',
                DB::raw('COALESCE(ai.authorized_form_of_name, ai_fallback.authorized_form_of_name) as authorizedFormOfName'),
                's.slug'
            )
            ->orderByRaw('COALESCE(ai.authorized_form_of_name, ai_fallback.authorized_form_of_name) ASC')
            ->get();
    }

    /**
     * Check if a donor exists.
     */
    public function exists(int $id): bool
    {
        return DB::table('donor')
            ->where('id', $id)
            ->exists();
    }

    /**
     * Save contact information for a donor (standard + extended fields).
     * Supports creating new contacts or updating existing ones.
     *
     * @param int $actorId The donor/actor ID
     * @param array $data Contact data
     * @param string $culture Culture code
     * @param int|null $contactId Existing contact ID to update, or null to create/find
     * @return int The contact ID
     */
    public function saveContactInformation(int $actorId, array $data, string $culture, ?int $contactId = null): int
    {
        $now = date('Y-m-d H:i:s');

        // Handle primary contact flag
        $isPrimary = !empty($data['primaryContact']) ? 1 : 0;
        if ($isPrimary) {
            // Clear other primary contacts for this actor
            DB::table('contact_information')
                ->where('actor_id', $actorId)
                ->when($contactId, function ($query) use ($contactId) {
                    return $query->where('id', '!=', $contactId);
                })
                ->update(['primary_contact' => 0]);
        }

        if ($contactId) {
            // Update existing contact
            DB::table('contact_information')
                ->where('id', $contactId)
                ->update([
                    'primary_contact' => $isPrimary,
                    'contact_person' => $data['contactPerson'] ?: null,
                    'street_address' => $data['streetAddress'] ?: null,
                    'website' => $data['website'] ?: null,
                    'email' => $data['email'] ?: null,
                    'telephone' => $data['telephone'] ?: null,
                    'fax' => $data['fax'] ?: null,
                    'postal_code' => $data['postalCode'] ?: null,
                    'country_code' => $data['countryCode'] ?: null,
                    'latitude' => !empty($data['latitude']) ? (float) $data['latitude'] : null,
                    'longitude' => !empty($data['longitude']) ? (float) $data['longitude'] : null,
                    'updated_at' => $now,
                    'serial_number' => DB::raw('serial_number + 1'),
                ]);
        } else {
            // Create new contact
            $contactId = DB::table('contact_information')->insertGetId([
                'actor_id' => $actorId,
                'primary_contact' => $isPrimary,
                'contact_person' => $data['contactPerson'] ?: null,
                'street_address' => $data['streetAddress'] ?: null,
                'website' => $data['website'] ?: null,
                'email' => $data['email'] ?: null,
                'telephone' => $data['telephone'] ?: null,
                'fax' => $data['fax'] ?: null,
                'postal_code' => $data['postalCode'] ?: null,
                'country_code' => $data['countryCode'] ?: null,
                'latitude' => !empty($data['latitude']) ? (float) $data['latitude'] : null,
                'longitude' => !empty($data['longitude']) ? (float) $data['longitude'] : null,
                'source_culture' => $culture,
                'created_at' => $now,
                'updated_at' => $now,
                'serial_number' => 0,
            ]);
        }

        // Update or insert i18n record
        $existingI18n = DB::table('contact_information_i18n')
            ->where('id', $contactId)
            ->where('culture', $culture)
            ->exists();

        $i18nData = [
            'contact_type' => $data['contactType'] ?: null,
            'city' => $data['city'] ?: null,
            'region' => $data['region'] ?: null,
            'note' => $data['note'] ?: null,
        ];

        if ($existingI18n) {
            DB::table('contact_information_i18n')
                ->where('id', $contactId)
                ->where('culture', $culture)
                ->update($i18nData);
        } else {
            $i18nData['id'] = $contactId;
            $i18nData['culture'] = $culture;
            DB::table('contact_information_i18n')->insert($i18nData);
        }

        // Save extended contact information
        $this->saveExtendedContactInfo($contactId, $data);

        return $contactId;
    }

    /**
     * Delete a contact and its related records.
     *
     * @param int $contactId The contact ID to delete
     * @return bool True if deleted
     */
    public function deleteContact(int $contactId): bool
    {
        // Delete extended info first
        DB::table('contact_information_extended')
            ->where('contact_information_id', $contactId)
            ->delete();

        // Delete i18n records
        DB::table('contact_information_i18n')
            ->where('id', $contactId)
            ->delete();

        // Delete main contact record
        return DB::table('contact_information')
            ->where('id', $contactId)
            ->delete() > 0;
    }

    /**
     * Save extended contact information (linked table).
     */
    protected function saveExtendedContactInfo(int $contactId, array $data): void
    {
        $extendedData = [
            'title' => $data['title'] ?? null,
            'role' => $data['role'] ?? null,
            'department' => $data['department'] ?? null,
            'cell' => $data['cell'] ?? null,
            'id_number' => $data['idNumber'] ?? null,
            'alternative_email' => $data['alternativeEmail'] ?? null,
            'alternative_phone' => $data['alternativePhone'] ?? null,
            'preferred_contact_method' => !empty($data['preferredContactMethod']) ? $data['preferredContactMethod'] : null,
            'language_preference' => $data['languagePreference'] ?? null,
            'notes' => $data['extendedNotes'] ?? null,
        ];

        // Check if extended record exists
        $existing = DB::table('contact_information_extended')
            ->where('contact_information_id', $contactId)
            ->exists();

        if ($existing) {
            DB::table('contact_information_extended')
                ->where('contact_information_id', $contactId)
                ->update($extendedData);
        } else {
            // Only insert if there's actual data
            $hasData = false;
            foreach ($extendedData as $value) {
                if (!empty($value)) {
                    $hasData = true;
                    break;
                }
            }

            if ($hasData) {
                $extendedData['contact_information_id'] = $contactId;
                DB::table('contact_information_extended')->insert($extendedData);
            }
        }
    }
}