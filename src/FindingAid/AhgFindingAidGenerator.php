<?php

namespace AtomFramework\FindingAid;

/**
 * Base AtoM's finding aid generator, with custom fields added to the EAD it
 * builds the PDF from (#202). Everything else is base behaviour.
 */
class AhgFindingAidGenerator extends \QubitFindingAidGenerator
{
    public function generateEadFile(): string
    {
        $path = parent::generateEadFile();

        $options = ['public' => ('public' === $this->getAuthLevel()), 'findingAidVisibility' => true];
        $xml = file_get_contents($path);
        $augmented = CustomFieldEad::augment((string) $xml, $this->getResource(), $options);
        if ($augmented !== $xml) {
            file_put_contents($path, $augmented);
        }

        return $path;
    }
}
