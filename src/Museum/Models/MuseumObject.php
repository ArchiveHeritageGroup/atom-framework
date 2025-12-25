<?php

namespace AtomFramework\Museum\Models;

class MuseumObject
{
    private ?int $id = null;
    private int $informationObjectId;
    private string $workType;
    private array $materials = [];
    private array $techniques = [];
    private array $measurements = [];
    private ?string $creationDateEarliest = null;
    private ?string $creationDateLatest = null;
    private ?string $inscription = null;
    private ?string $conditionNotes = null;
    private ?string $createdAt = null;
    private ?string $updatedAt = null;

    public function __construct(array $attributes = [])
    {
        if (!empty($attributes)) {
            $this->fill($attributes);
        }
    }

    public function fill(array $attributes): void
    {
        if (isset($attributes['id'])) {
            $this->id = (int) $attributes['id'];
        }

        if (isset($attributes['information_object_id'])) {
            $this->informationObjectId = (int) $attributes['information_object_id'];
        }

        if (isset($attributes['work_type'])) {
            $this->workType = (string) $attributes['work_type'];
        }

        if (isset($attributes['materials'])) {
            $this->materials = is_string($attributes['materials'])
                ? json_decode($attributes['materials'], true)
                : $attributes['materials'];
        }

        if (isset($attributes['techniques'])) {
            $this->techniques = is_string($attributes['techniques'])
                ? json_decode($attributes['techniques'], true)
                : $attributes['techniques'];
        }

        if (isset($attributes['measurements'])) {
            $this->measurements = is_string($attributes['measurements'])
                ? json_decode($attributes['measurements'], true)
                : $attributes['measurements'];
        }

        if (isset($attributes['creation_date_earliest'])) {
            $this->creationDateEarliest = $attributes['creation_date_earliest'];
        }

        if (isset($attributes['creation_date_latest'])) {
            $this->creationDateLatest = $attributes['creation_date_latest'];
        }

        if (isset($attributes['inscription'])) {
            $this->inscription = $attributes['inscription'];
        }

        if (isset($attributes['condition_notes'])) {
            $this->conditionNotes = $attributes['condition_notes'];
        }

        if (isset($attributes['created_at'])) {
            $this->createdAt = $attributes['created_at'];
        }

        if (isset($attributes['updated_at'])) {
            $this->updatedAt = $attributes['updated_at'];
        }
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'information_object_id' => $this->informationObjectId,
            'work_type' => $this->workType,
            'materials' => $this->materials,
            'techniques' => $this->techniques,
            'measurements' => $this->measurements,
            'creation_date_earliest' => $this->creationDateEarliest,
            'creation_date_latest' => $this->creationDateLatest,
            'inscription' => $this->inscription,
            'condition_notes' => $this->conditionNotes,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInformationObjectId(): int
    {
        return $this->informationObjectId;
    }

    public function getWorkType(): string
    {
        return $this->workType;
    }

    public function getMaterials(): array
    {
        return $this->materials;
    }

    public function getTechniques(): array
    {
        return $this->techniques;
    }

    public function getMeasurements(): array
    {
        return $this->measurements;
    }

    public function getCreationDateEarliest(): ?string
    {
        return $this->creationDateEarliest;
    }

    public function getCreationDateLatest(): ?string
    {
        return $this->creationDateLatest;
    }

    public function getInscription(): ?string
    {
        return $this->inscription;
    }

    public function getConditionNotes(): ?string
    {
        return $this->conditionNotes;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?string
    {
        return $this->updatedAt;
    }

    // Setters
    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function setInformationObjectId(int $informationObjectId): void
    {
        $this->informationObjectId = $informationObjectId;
    }

    public function setWorkType(string $workType): void
    {
        $this->workType = $workType;
    }

    public function setMaterials(array $materials): void
    {
        $this->materials = $materials;
    }

    public function setTechniques(array $techniques): void
    {
        $this->techniques = $techniques;
    }

    public function setMeasurements(array $measurements): void
    {
        $this->measurements = $measurements;
    }

    public function setCreationDateEarliest(?string $date): void
    {
        $this->creationDateEarliest = $date;
    }

    public function setCreationDateLatest(?string $date): void
    {
        $this->creationDateLatest = $date;
    }

    public function setInscription(?string $inscription): void
    {
        $this->inscription = $inscription;
    }

    public function setConditionNotes(?string $notes): void
    {
        $this->conditionNotes = $notes;
    }

    public function setCreatedAt(?string $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function setUpdatedAt(?string $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }
}
