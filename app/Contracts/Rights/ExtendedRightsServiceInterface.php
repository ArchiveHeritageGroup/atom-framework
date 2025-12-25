<?php

namespace App\Contracts\Rights;

use Illuminate\Support\Collection;

interface ExtendedRightsServiceInterface
{
    public function getRightsStatements(bool $activeOnly = true): Collection;
    public function getCreativeCommonsLicenses(bool $activeOnly = true): Collection;
    public function getTkLabels(bool $activeOnly = true): Collection;
    public function getTkLabelCategories(): Collection;
    public function getObjectRights(int $objectId): ?array;
    public function assignRights(int $objectId, array $data): array;
    public function removeRights(int $extendedRightsId): bool;
}
