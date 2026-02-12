<?php

namespace AtomFramework\Console\Commands\DigitalObject;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Load a CSV list of digital objects.
 *
 * Ported from lib/task/digitalobject/digitalObjectLoadTask.class.php.
 */
class DigitalObjectLoadCommand extends BaseCommand
{
    protected string $name = 'digitalobject:load';
    protected string $description = 'Load a CSV list of digital objects';
    protected string $detailedDescription = <<<'EOF'
Load a CSV list of digital objects.

Valid CSV columns are 'filename' and one of: 'slug', 'identifier', 'information_object_id'.
EOF;

    private const IO_SLUG_COLUMN = 'slug';
    private const IO_IDENTIFIER_COLUMN = 'identifier';
    private const IO_ID_COLUMN = 'information_object_id';
    private const PATH_COLUMN = 'filename';
    private const IO_SPECIFIER_COLUMNS = [self::IO_SLUG_COLUMN, self::IO_IDENTIFIER_COLUMN, self::IO_ID_COLUMN];

    private static int $count = 0;
    private int $curObjNum = 0;
    private int $totalObjCount = 0;
    private int $skippedCount = 0;
    private int $deletedCount = 0;
    private int $importedCount = 0;
    private bool $disableNestedSetUpdating = false;

    protected function configure(): void
    {
        $this->addArgument('filename', 'The input file (CSV format)', true);
        $this->addOption('link-source', 's', 'Link source (if importing a file)');
        $this->addOption('path', 'p', 'Path or URL prefix for all digital objects');
        $this->addOption('limit', 'l', 'Limit number of digital objects imported to n');
        $this->addOption('attach-only', 'a', 'Always attach digital objects to a new child description');
        $this->addOption('replace', 'r', 'Delete and replace digital objects');
        $this->addOption('index', 'i', 'Update search index (defaults to false)');
        $this->addOption('skip-nested-set-build', null, 'Do not build the nested set upon import completion');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $filename = $this->argument('filename');

        $databaseManager = new \sfDatabaseManager(\sfProjectConfiguration::getActive());
        $conn = $databaseManager->getDatabase('propel')->getConnection();

        $uploadDir = $this->getUploadDir($conn);
        \sfConfig::set('app_upload_dir', $uploadDir);

        if (false === $fh = fopen($filename, 'rb')) {
            $this->error('You must specify a valid filename');

            return 1;
        }

        $limit = $this->option('limit');
        if ($limit && !is_numeric($limit)) {
            $this->error('Limit must be a number');

            return 1;
        }

        $doReplace = $this->hasOption('replace');
        $attachOnly = $this->hasOption('attach-only');

        if ($doReplace && $attachOnly) {
            $this->error('Cannot use option "--attach-only" with "--replace".');

            return 1;
        }

        if ($this->hasOption('index')) {
            \QubitSearch::enable();
        } else {
            \QubitSearch::disable();
        }

        $this->disableNestedSetUpdating = $this->hasOption('skip-nested-set-build');

        $operation = $doReplace ? 'Replace' : 'Load';
        $this->info(sprintf('%s digital objects from %s...', $operation, $filename));

        // Get header (first) row
        $header = fgetcsv($fh, 1000);
        $this->validateColumns($header);

        $fileKey = array_search(self::PATH_COLUMN, $header);
        $idType = '';

        if (false !== $idKey = array_search(self::IO_ID_COLUMN, $header)) {
            $idType = 'id';
        } elseif (false !== $idKey = array_search(self::IO_IDENTIFIER_COLUMN, $header)) {
            $idType = 'identifier';
        } elseif (false !== $idKey = array_search(self::IO_SLUG_COLUMN, $header)) {
            $idType = 'slug';
        }

        $digitalObjects = [];

        while ($item = fgetcsv($fh, 1000)) {
            $id = $item[$idKey];
            $fname = $item[$fileKey];

            if (0 == strlen($id)) {
                $this->line("Row {$this->totalObjCount}: missing {$idType}");
                continue;
            }

            if (0 == strlen($fname)) {
                $this->line("Row {$this->totalObjCount}: missing filename");
                continue;
            }

            if (!isset($digitalObjects[$id])) {
                $digitalObjects[$id] = $fname;
            } elseif (!is_array($digitalObjects[$id])) {
                $digitalObjects[$id] = [$digitalObjects[$id], $fname];
            } else {
                $digitalObjects[$id][] = $fname;
            }

            ++$this->totalObjCount;
        }

        $this->curObjNum = 0;

        // Set up prepared query based on identifier type
        $sql = 'SELECT io.id, do.id FROM ' . \QubitInformationObject::TABLE_NAME . ' io ';
        if ('slug' == $idType) {
            $sql .= 'JOIN ' . \QubitSlug::TABLE_NAME . ' slug ON slug.object_id = io.id ';
        }
        $sql .= 'LEFT JOIN ' . \QubitDigitalObject::TABLE_NAME . ' do ON io.id = do.object_id';

        if ('id' == $idType) {
            $sql .= ' WHERE io.id = ?';
        } elseif ('identifier' == $idType) {
            $sql .= ' WHERE io.identifier = ?';
        } else {
            $sql .= ' WHERE slug.slug = ?';
        }

        $ioQuery = \QubitPdo::prepare($sql);
        $this->importedCount = 0;

        foreach ($digitalObjects as $key => $item) {
            if ($limit && ($this->importedCount >= (int) $limit)) {
                break;
            }

            $ioQuery->execute([$key]);
            $results = $ioQuery->fetch();
            if (!$results) {
                $this->line("Couldn't find information object with {$idType}: {$key}");
                continue;
            }

            $path = $this->option('path');
            $linkSource = $this->hasOption('link-source');

            if ($doReplace) {
                $digitalObjectName = !is_array($item) ? $item : end($item);

                if (null !== $results[1]) {
                    if ($this->validUrlOrFilePath($digitalObjectName, $path)) {
                        if (null !== $do = \QubitDigitalObject::getById($results[1])) {
                            $do->delete();
                            ++$this->deletedCount;
                        }
                    } else {
                        $this->line(sprintf("Couldn't read file or URL '%s'", $digitalObjectName));
                        ++$this->skippedCount;
                        continue;
                    }
                }
                $this->addDigitalObject($results[0], $digitalObjectName, $conn, $path, $linkSource, $doReplace, $limit);
            } elseif (!is_array($item) && !$attachOnly) {
                if (null !== $results[1]) {
                    $this->line(sprintf("Information object %s: %s already has a digital object. Skipping.", $idType, $key));
                    ++$this->skippedCount;
                    continue;
                }

                if (!$this->validUrlOrFilePath($item, $path)) {
                    $this->line(sprintf("Couldn't read file or URL '%s'", $item));
                    ++$this->skippedCount;
                    continue;
                }

                $this->addDigitalObject($results[0], $item, $conn, $path, $linkSource, $doReplace, $limit);
            } else {
                if (!is_array($item)) {
                    if (!$this->validUrlOrFilePath($item, $path)) {
                        $this->line(sprintf("Couldn't read file or URL '%s'", $item));
                        ++$this->skippedCount;
                        continue;
                    }

                    $this->attachDigitalObject($item, $results[0], $conn, $path, $linkSource, $doReplace, $limit);
                } else {
                    for ($i = 0; $i < count($item); ++$i) {
                        if (!$this->validUrlOrFilePath($item[$i], $path)) {
                            $this->line(sprintf("Couldn't read file or URL '%s'", $item[$i]));
                            ++$this->skippedCount;
                            continue;
                        }

                        $this->attachDigitalObject($item[$i], $results[0], $conn, $path, $linkSource, $doReplace, $limit);
                    }
                }
            }

            ++$this->importedCount;
            \Qubit::clearClassCaches();
        }

        $this->success(sprintf('Successfully loaded %d digital objects.', self::$count));

        if (!$this->hasOption('index')) {
            $this->warning('Please update the search index manually to reflect any changes');
        }

        return 0;
    }

    private function attachDigitalObject(string $item, int $informationObjectId, $conn, ?string $pathPrefix, bool $linkSource, bool $doReplace, ?string $limit): void
    {
        $informationObject = new \QubitInformationObject();
        $informationObject->parent = \QubitInformationObject::getById($informationObjectId);
        $informationObject->title = basename($item);
        $informationObject->disableNestedSetUpdating = $this->disableNestedSetUpdating;
        $informationObject->save($conn);

        $this->addDigitalObject($informationObject->id, $item, $conn, $pathPrefix, $linkSource, $doReplace, $limit);
    }

    private function validateColumns(array $columns): void
    {
        $valid = in_array(self::PATH_COLUMN, $columns);

        if ($valid) {
            $valid = count(array_intersect(self::IO_SPECIFIER_COLUMNS, $columns)) > 0;
        }

        if (!$valid) {
            throw new \RuntimeException(
                "Import file must contain a '" . self::PATH_COLUMN . "' column and one of the following: '"
                . implode("', '", self::IO_SPECIFIER_COLUMNS) . "'"
            );
        }
    }

    private function getPath(string $path, ?string $prefix): string
    {
        if ($prefix) {
            $path = $prefix . $path;
        }

        return $path;
    }

    private function validUrlOrFilePath(string $url_or_path, ?string $pathPrefix): bool
    {
        $candidate = trim($this->getPath($url_or_path, $pathPrefix));

        if (is_file($candidate) && is_readable($candidate)) {
            return true;
        }

        if (!filter_var($candidate, FILTER_VALIDATE_URL)) {
            return false;
        }

        $scheme = parse_url($candidate, PHP_URL_SCHEME);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }

        return $this->checkUrlExistsWithCurl($candidate);
    }

    private function checkUrlExistsWithCurl(string $url): bool
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_NOBODY => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
            CURLOPT_USERAGENT => 'AtoM URL Validator/1.0',
        ]);

        curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $ch = null;

        return ($httpCode >= 200 && $httpCode < 400);
    }

    private function addDigitalObject(int $objectId, string $path, $conn, ?string $pathPrefix, bool $linkSource, bool $doReplace, ?string $limit): void
    {
        ++$this->curObjNum;

        if (!$this->validUrlOrFilePath($path, $pathPrefix)) {
            $this->line("Couldn't read file or URL '{$path}'");

            return;
        }

        $fullPath = $this->getPath($path, $pathPrefix);
        $fname = basename($fullPath);

        $remainingImportCount = $this->totalObjCount - $this->skippedCount - $this->importedCount;
        $operation = $doReplace ? 'Replacing with' : 'Loading';
        $message = sprintf("%s '%s' (%d of %d remaining", $operation, $fname, $this->curObjNum, $remainingImportCount);

        if ($limit) {
            $message .= sprintf(': limited to %d imports', (int) $limit);
        }
        $message .= ')';

        $this->line(sprintf('(%s) %s', date('M d, g:i:s A'), $message));

        $do = new \QubitDigitalObject();
        $do->objectId = $objectId;

        if (file_exists($fullPath)) {
            if ($linkSource) {
                if (false === $do->importFromFile($fullPath)) {
                    return;
                }
            } else {
                $do->usageId = \QubitTerm::MASTER_ID;
                $do->assets[] = new \QubitAsset($fullPath);
            }
        } else {
            if (false === $do->importFromURI($fullPath)) {
                return;
            }
        }

        $do->save($conn);

        ++self::$count;
    }

    private function getUploadDir($conn): string
    {
        $uploadDir = 'uploads';

        $sql = "SELECT i18n.value
            FROM setting stg JOIN setting_i18n i18n ON stg.id = i18n.id
            WHERE stg.source_culture = i18n.culture
            AND stg.name = 'upload_dir'";

        if ($sth = $conn->query($sql)) {
            $result = $sth->fetch();
            if ($result) {
                $uploadDir = $result[0];
            }
        }

        return $uploadDir;
    }
}
