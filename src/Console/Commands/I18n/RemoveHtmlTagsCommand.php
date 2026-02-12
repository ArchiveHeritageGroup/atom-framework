<?php

namespace AtomFramework\Console\Commands\I18n;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Remove HTML tags from i18n table fields and convert HTML entities.
 *
 * Ported from lib/task/i18n/i18nRemoveHtmlTagsTask.class.php
 * and lib/task/i18n/i18nTransformBaseTask.class.php.
 */
class RemoveHtmlTagsCommand extends BaseCommand
{
    protected string $name = 'i18n:remove-html-tags';
    protected string $description = 'Remove HTML tags from i18n fields and convert HTML entities';
    protected string $detailedDescription = <<<'EOF'
Remove HTML tags from inside information object, actor, note, repository,
and rights i18n fields. HTML character entities are also converted to their
non-HTML representations.
EOF;

    private static array $tables = [
        'information_object_i18n' => [
            'title', 'alternate_title', 'edition', 'extent_and_medium',
            'archival_history', 'acquisition', 'scope_and_content', 'appraisal',
            'accruals', 'arrangement', 'access_conditions', 'reproduction_conditions',
            'physical_characteristics', 'finding_aids', 'location_of_originals',
            'location_of_copies', 'related_units_of_description',
            'institution_responsible_identifier', 'rules', 'sources', 'revision_history',
        ],
        'actor_i18n' => [
            'authorized_form_of_name', 'dates_of_existence', 'history', 'places',
            'legal_status', 'functions', 'mandates', 'internal_structures',
            'general_context', 'institution_responsible_identifier', 'rules',
            'sources', 'revision_history',
        ],
        'note_i18n' => ['content'],
        'repository_i18n' => [
            'geocultural_context', 'collecting_policies', 'buildings', 'holdings',
            'finding_aids', 'opening_times', 'access_conditions', 'disabled_access',
            'research_services', 'reproduction_services', 'public_facilities',
            'desc_institution_identifier', 'desc_rules', 'desc_sources',
            'desc_revision_history',
        ],
        'rights_i18n' => [
            'rights_note', 'copyright_note', 'identifier_value', 'identifier_type',
            'identifier_role', 'license_terms', 'license_note',
            'statute_jurisdiction', 'statute_note',
        ],
    ];

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $rootIds = implode(', ', [
            \QubitInformationObject::ROOT_ID,
            \QubitActor::ROOT_ID,
            \QubitRepository::ROOT_ID,
        ]);

        $rowCount = 0;
        $changedCount = 0;
        $columnsChangedCount = 0;

        foreach (self::$tables as $tableName => $columns) {
            $query = 'SELECT * FROM ' . $tableName . ' WHERE id NOT IN (' . $rootIds . ')';
            $statement = \QubitPdo::prepareAndExecute($query);

            while ($row = $statement->fetch(\PDO::FETCH_ASSOC)) {
                $columnsChanged = $this->processRow($row, $tableName, $columns);

                if ($columnsChanged) {
                    ++$changedCount;
                    $columnsChangedCount += $columnsChanged;
                }

                $message = 'Processed ' . $tableName . ' row ' . $row['id'] . ' (' . $row['culture'] . ')';
                if ($columnsChanged) {
                    $message .= ' (' . $columnsChanged . ' changes)';
                }

                $this->line($message);
                ++$rowCount;
            }
        }

        $message = 'Processed ' . $rowCount . ' rows.';
        if ($changedCount) {
            $message .= ' Changed ' . $changedCount . ' rows';
            $message .= ' (' . $columnsChangedCount . ' field values changed).';
        }

        $this->success($message);

        return 0;
    }

    private function processRow(array $row, string $tableName, array $columns): int
    {
        $columnValues = [];

        foreach ($columns as $column) {
            if (
                isset($row[$column])
                && $row[$column]
                && (($row[$column] != strip_tags($row[$column])) || ($row[$column] != html_entity_decode($row[$column])))
            ) {
                $columnValues[$column] = $this->transformHtmlToText($row[$column]);
            }
        }

        $this->updateRow($tableName, $row['id'], $row['culture'], $columnValues);

        return count($columnValues);
    }

    private function transformHtmlToText(string $html): string
    {
        $doc = new \DOMDocument();
        $doc->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));

        $this->transformDocument($doc);

        return trim(htmlspecialchars_decode(strip_tags($doc->saveXml($doc->documentElement))));
    }

    private function transformDocument(\DOMDocument &$doc): void
    {
        $this->transformDocumentLinks($doc);
        $this->transformDocumentLists($doc);
        $this->transformDocumentDescriptionLists($doc);
        $this->transformDocumentBreaks($doc);
        $this->transformDocumentParasIntoNewlines($doc);
    }

    private function transformDocumentLinks(\DOMDocument &$doc): void
    {
        $linkList = $doc->getElementsByTagName('a');

        while ($linkList->length > 0) {
            $linkNode = $linkList->item(0);
            $linkText = $linkNode->textContent;
            $linkHref = $linkNode->getAttribute('href');

            if ($linkHref) {
                $linkText = sprintf('[%s](%s)', $linkText, $linkHref);
            }

            $newTextNode = $doc->createTextNode($linkText);
            $linkNode->parentNode->replaceChild($newTextNode, $linkNode);
        }
    }

    private function transformDocumentLists(\DOMDocument &$doc): void
    {
        $ulList = $doc->getElementsByTagName('ul');

        while ($ulList->length > 0) {
            $listNode = $ulList->item(0);
            $newParaNode = $doc->createElement('p');
            $paraText = '';

            foreach ($listNode->childNodes as $childNode) {
                $paraText .= '* ' . $childNode->textContent . "\n";
            }

            $newTextNode = $doc->createTextNode($paraText);
            $newParaNode->appendChild($newTextNode);
            $listNode->parentNode->replaceChild($newParaNode, $listNode);
        }
    }

    private function transformDocumentDescriptionLists(\DOMDocument &$doc): void
    {
        $termList = $doc->getElementsByTagName('dt');

        while ($termList->length > 0) {
            $termNode = $termList->item(0);
            $termNode->parentNode->removeChild($termNode);
        }

        $descriptionList = $doc->getElementsByTagName('dd');

        while ($descriptionList->length > 0) {
            $descriptionNode = $descriptionList->item(0);
            $newParaNode = $doc->createElement('p');
            $newTextNode = $doc->createTextNode($descriptionNode->textContent);
            $newParaNode->appendChild($newTextNode);
            $descriptionNode->parentNode->replaceChild($newParaNode, $descriptionNode);
        }
    }

    private function transformDocumentBreaks(\DOMDocument &$doc): void
    {
        $breakList = $doc->getElementsByTagName('br');

        while ($breakList->length) {
            $breakNode = $breakList->item(0);
            $newTextNode = $doc->createTextNode("\n");
            $breakNode->parentNode->replaceChild($newTextNode, $breakNode);
        }
    }

    private function transformDocumentParasIntoNewlines(\DOMDocument &$doc): void
    {
        $paraList = $doc->getElementsByTagName('p');

        while ($paraList->length) {
            $paraNode = $paraList->item(0);
            $paraText = "\n" . $paraNode->textContent . "\n";
            $newTextNode = $doc->createTextNode($paraText);
            $paraNode->parentNode->replaceChild($newTextNode, $paraNode);
        }
    }

    private function updateRow(string $table, $id, string $culture, array $columnValues): void
    {
        if (empty($columnValues)) {
            return;
        }

        $values = [];
        $query = 'UPDATE ' . $table . ' SET ';

        foreach ($columnValues as $column => $value) {
            $query .= (count($values)) ? ', ' : '';
            $query .= $column . '=?';
            $values[] = $value;
        }

        $query .= " WHERE id='" . $id . "' AND culture='" . $culture . "'";

        \QubitPdo::prepareAndExecute($query, $values);
    }
}
