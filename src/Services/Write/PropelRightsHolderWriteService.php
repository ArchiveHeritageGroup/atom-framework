<?php

namespace AtomFramework\Services\Write;

/**
 * Propel adapter: RightsHolder write operations.
 *
 * Delegates to QubitRightsHolder (extends QubitActor) when Propel is available.
 * RightsHolder inherits from Actor: rights_holder.id -> actor.id -> object.id.
 */
class PropelRightsHolderWriteService implements RightsHolderWriteServiceInterface
{
    public function createRightsHolder(array $data, string $culture = 'en'): int
    {
        $rh = new \QubitRightsHolder();
        $rh->parentId = \QubitActor::ROOT_ID;
        $rh->sourceCulture = $culture;

        if (isset($data['authorized_form_of_name']) || isset($data['authorizedFormOfName'])) {
            $name = $data['authorized_form_of_name'] ?? $data['authorizedFormOfName'];
            $rh->setAuthorizedFormOfName($name, ['culture' => $culture]);
        }

        foreach ($data as $key => $value) {
            if (!in_array($key, ['authorized_form_of_name', 'authorizedFormOfName'])) {
                $rh->{$key} = $value;
            }
        }

        $rh->save();

        return $rh->id;
    }

    public function updateRightsHolder(int $id, array $data, string $culture = 'en'): void
    {
        $rh = \QubitRightsHolder::getById($id);
        if (null === $rh) {
            return;
        }

        if (isset($data['authorized_form_of_name']) || isset($data['authorizedFormOfName'])) {
            $name = $data['authorized_form_of_name'] ?? $data['authorizedFormOfName'];
            $rh->setAuthorizedFormOfName($name, ['culture' => $culture]);
        }

        foreach ($data as $key => $value) {
            if (!in_array($key, ['authorized_form_of_name', 'authorizedFormOfName'])) {
                $rh->{$key} = $value;
            }
        }

        $rh->save();
    }

    public function deleteRightsHolder(int $id): void
    {
        $rh = \QubitRightsHolder::getById($id);
        if (null !== $rh) {
            $rh->delete();
        }
    }

    public function newRightsHolder(): object
    {
        return new \QubitRightsHolder();
    }
}
