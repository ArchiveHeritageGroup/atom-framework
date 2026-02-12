<?php

namespace AtomFramework\Console\Commands\I18n;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Convert custom link format to Markdown syntax in various i18n table fields.
 *
 * Ported from lib/task/i18n/i18nCustomLinkToMarkdownTask.class.php
 * and lib/task/i18n/i18nTransformBaseTask.class.php.
 */
class CustomLinkToMarkdownCommand extends BaseCommand
{
    protected string $name = 'i18n:custom-link-to-markdown';
    protected string $description = 'Convert custom link format to Markdown syntax in i18n fields';
    protected string $detailedDescription = <<<'EOF'
Convert custom link format to Markdown syntax from inside information object,
actor, note, repository, and rights i18n fields.
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
            if (!isset($row[$column]) || null === $row[$column]) {
                continue;
            }

            $regex = '~
                (?:
                    (?:&quot;|\")(.*?)(?:\&quot;|\")\:            # Double quote and colon
                )
                (
                    (?:(?:https?|ftp)://)|                        # protocol spec, or
                    (?:www\.)|                                    # www.*
                    (?:mailto:)                                   # mailto:*
                )
                (
                    [-\w@]+                                       # subdomain or domain
                    (?:\.[-\w@]+)*                                # remaining subdomains or domain
                    (?::\d+)?                                     # port
                    (?:/(?:(?:[\~\w\+%-]|(?:[,.;:][^\s$]))+)?)*   # path
                    (?:\?[\w\+\/%&=.;-]+)?                        # query string
                    (?:\#[\w\-/\?!=]*)?                           # trailing anchor
                )
                ~x';

            $transformedValue = preg_replace_callback($regex, function ($matches) {
                if (!empty($matches[1])) {
                    return '[' . $matches[1] . '](' . ('www.' == $matches[2] ? 'http://www.' : $matches[2]) . trim($matches[3]) . ')';
                }

                return '[' . $matches[2] . trim($matches[3]) . '](' . ('www.' == $matches[2] ? 'http://www.' : $matches[2]) . trim($matches[3]) . ')';
            }, $row[$column]);

            if ($row[$column] != $transformedValue) {
                $columnValues[$column] = $transformedValue;
            }
        }

        $this->updateRow($tableName, $row['id'], $row['culture'], $columnValues);

        return count($columnValues);
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
