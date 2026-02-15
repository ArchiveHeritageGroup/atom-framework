<?php

namespace AtomFramework\Services\Write;

use AtomExtensions\Services\SlugService;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Standalone accession write service using Laravel Query Builder only.
 *
 * Clean implementation without Propel references or class_exists checks.
 * Unlike the Propel version, createRelatedInformationObject() is fully
 * implemented — it reads accession i18n data, creates an IO with copied
 * fields, creates the accession-IO relation, and generates a slug.
 */
class StandaloneAccessionWriteService implements AccessionWriteServiceInterface
{
    use EntityWriteTrait;

    private const I18N_FIELDS = [
        'appraisal', 'archival_history', 'location_information',
        'physical_characteristics', 'processing_notes', 'received_extent_units',
        'scope_and_content', 'source_of_acquisition', 'title',
    ];

    public function createAccession(array $attributes, string $culture = 'en'): object
    {
        [$core, $i18n] = $this->splitI18nFields($attributes, self::I18N_FIELDS);

        $objectId = $this->insertEntity(
            'QubitAccession',
            'accession',
            $core,
            'accession_i18n',
            $i18n,
            $culture
        );

        $result = new \stdClass();
        $result->id = $objectId;
        foreach ($attributes as $key => $value) {
            $result->{$key} = $value;
        }

        return $result;
    }

    public function updateAccession(int $id, array $attributes, string $culture = 'en'): void
    {
        [$core, $i18n] = $this->splitI18nFields($attributes, self::I18N_FIELDS);
        $this->updateEntity($id, 'accession', $core, 'accession_i18n', $i18n, $culture);
    }

    public function createRelatedInformationObject(object $accession): object
    {
        $accessionId = $accession->id;

        // Read accession source culture
        $culture = DB::table('accession')
            ->where('id', $accessionId)
            ->value('source_culture') ?? 'en';

        // Read accession i18n data from DB
        $accI18n = DB::table('accession_i18n')
            ->where('id', $accessionId)
            ->where('culture', $culture)
            ->first();

        // Build IO i18n data from accession fields
        $ioI18n = [];
        if ($accI18n) {
            if (!empty($accI18n->title)) {
                $ioI18n['title'] = $accI18n->title;
            }
            if (!empty($accI18n->scope_and_content)) {
                $ioI18n['scope_and_content'] = $accI18n->scope_and_content;
            }
            if (!empty($accI18n->physical_characteristics)) {
                $ioI18n['physical_characteristics'] = $accI18n->physical_characteristics;
            }
            if (!empty($accI18n->archival_history)) {
                $ioI18n['archival_history'] = $accI18n->archival_history;
            }
        }

        // Also check stdClass properties (from factory-created accessions)
        if (empty($ioI18n['title']) && !empty($accession->title)) {
            $ioI18n['title'] = $accession->title;
        }
        if (empty($ioI18n['scope_and_content']) && !empty($accession->scopeAndContent)) {
            $ioI18n['scope_and_content'] = $accession->scopeAndContent;
        }
        if (empty($ioI18n['physical_characteristics']) && !empty($accession->physicalCharacteristics)) {
            $ioI18n['physical_characteristics'] = $accession->physicalCharacteristics;
        }

        return DB::transaction(function () use ($accessionId, $ioI18n, $culture) {
            // Create IO via insertEntity
            $ioId = $this->insertEntity(
                'QubitInformationObject',
                'information_object',
                ['parent_id' => \QubitInformationObject::ROOT_ID],
                'information_object_i18n',
                $ioI18n,
                $culture
            );

            // Create relation: IO -> accession (type = ACCESSION)
            $this->createRelationRecord($ioId, $accessionId, \QubitTerm::ACCESSION_ID);

            // Generate slug for new IO
            $slug = null;
            if (!empty($ioI18n['title'])) {
                $slugObj = SlugService::createSlug($ioId, $ioI18n['title']);
                $slug = $slugObj->slug;
            }

            // Set publication status to draft
            try {
                $pubStatusDraft = defined('\\QubitTerm::PUBLICATION_STATUS_DRAFT_ID')
                    ? \QubitTerm::PUBLICATION_STATUS_DRAFT_ID
                    : 159;
                $statusTypePub = defined('\\QubitTerm::STATUS_TYPE_PUBLICATION_ID')
                    ? \QubitTerm::STATUS_TYPE_PUBLICATION_ID
                    : 158;

                DB::table('status')->insert([
                    'object_id' => $ioId,
                    'type_id' => $statusTypePub,
                    'status_id' => $pubStatusDraft,
                ]);
            } catch (\Exception $e) {
                // Non-fatal — status table may not have expected structure
            }

            $result = new \stdClass();
            $result->id = $ioId;
            $result->slug = $slug;

            return $result;
        });
    }

    public function createRelation(int $subjectId, int $objectId, int $typeId, bool $indexOnSave = true): object
    {
        $relId = $this->createRelationRecord($subjectId, $objectId, $typeId);

        $result = new \stdClass();
        $result->id = $relId;
        $result->subjectId = $subjectId;
        $result->objectId = $objectId;
        $result->typeId = $typeId;

        return $result;
    }

    public function linkDonor(int $accessionId, int $donorId): void
    {
        $this->createRelation($accessionId, $donorId, \QubitTerm::DONOR_ID);
    }

    public function createEvent(int $objectId, array $attributes, string $culture = 'en'): object
    {
        $eventId = $this->createEventRecord($objectId, $attributes, $culture);

        $result = new \stdClass();
        $result->id = $eventId;
        $result->objectId = $objectId;

        return $result;
    }

    public function newAccession(): object
    {
        return new \stdClass();
    }

    public function newRelation(): object
    {
        return new \stdClass();
    }

    public function newInformationObject(): object
    {
        return new \stdClass();
    }

    public function newEvent(): object
    {
        return new \stdClass();
    }
}
