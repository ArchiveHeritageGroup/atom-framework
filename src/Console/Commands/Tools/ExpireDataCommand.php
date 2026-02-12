<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Expire and remove data past retention dates.
 *
 * Ported from lib/task/tools/expireDataTask.class.php.
 * Uses Propel for complex data expiry logic involving multiple related
 * tables and cascade operations.
 */
class ExpireDataCommand extends BaseCommand
{
    protected string $name = 'tools:expire-data';
    protected string $description = 'Expire and remove data past retention dates';
    protected string $detailedDescription = <<<'EOF'
Delete expired data (in entirety or by age).

Supported data types: "access_log", "clipboard", "job"
Multiple types can be comma-separated.

Examples:
    php bin/atom tools:expire-data access_log
    php bin/atom tools:expire-data clipboard --older-than=2024-01-01
    php bin/atom tools:expire-data access_log,clipboard,job --force
EOF;

    public const ACCESS_LOG_MAX_AGE_DAYS = 7;

    public static array $TYPE_SPECIFICATIONS = [
        'access_log' => [
            'name' => 'access log',
            'plural_name' => 'access logs',
            'method_name' => 'accessLogExpireData',
            'default_max_age' => self::ACCESS_LOG_MAX_AGE_DAYS,
        ],
        'clipboard' => [
            'name' => 'saved clipboard',
            'plural_name' => 'saved clipboards',
            'method_name' => 'clipboardExpireData',
            'age_setting_name' => 'app_clipboard_save_max_age',
        ],
        'job' => [
            'name' => 'job (and any related file)',
            'plural_name' => 'jobs (and any related files)',
            'method_name' => 'jobExpireData',
        ],
    ];

    protected function configure(): void
    {
        $this->addArgument('data-type', 'Data type(s), comma-separated (access_log, clipboard, job)', true);
        $this->addOption('older-than', null, 'Expiry date expressed as YYYY-MM-DD');
        $this->addOption('force', 'f', 'Delete without confirmation');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        $dataTypes = explode(',', $this->argument('data-type'));
        $this->validateDataTypes($dataTypes);

        foreach ($dataTypes as $dataType) {
            $typeSpec = self::$TYPE_SPECIFICATIONS[$dataType];

            $expiryDate = $this->getOlderThanDate($typeSpec);

            // Abort if not forced or confirmed
            if (
                !$this->hasOption('force')
                && !$this->getExpireConfirmation($expiryDate, $typeSpec['plural_name'])
            ) {
                $this->info('Aborted.');
                return 0;
            }

            // Expire data and report results
            $methodName = $typeSpec['method_name'];
            $deletedCount = $this->{$methodName}($expiryDate);

            $this->info(sprintf(
                '%d %s deleted.',
                $deletedCount,
                $typeSpec['plural_name']
            ));
        }

        $this->success('Done!');

        return 0;
    }

    private function validateDataTypes(array $dataTypes): void
    {
        foreach ($dataTypes as $dataType) {
            if (!in_array($dataType, array_keys(self::$TYPE_SPECIFICATIONS))) {
                throw new \RuntimeException(
                    sprintf('Aborted: unsupported data type: "%s".', $dataType)
                );
            }
        }
    }

    private function calculateExpiryDate(int $maximumAgeInDays): string
    {
        $date = new \DateTime();
        $interval = new \DateInterval(sprintf('P%dD', $maximumAgeInDays));
        $date->sub($interval);

        return $date->format('Y-m-d');
    }

    private function getDateFromAgeSetting(string $name): string
    {
        $value = \sfConfig::get($name);

        if (!is_numeric($value) || intval($value) < 0) {
            throw new \RuntimeException(
                sprintf(
                    'Error: setting %s value "%s" is not a valid integer.',
                    $name,
                    $value
                )
            );
        }

        $date = $this->calculateExpiryDate(intval($value));
        $this->info(sprintf('Used %s setting to set expiry date of %s.', $name, $date));

        return $date;
    }

    private function getDateFromMaxAge(int $maxAge): string
    {
        if (!is_numeric($maxAge) || intval($maxAge) < 0) {
            throw new \RuntimeException(
                sprintf(
                    'Error: "default_max_age" of "%s" is not a valid integer.',
                    $maxAge
                )
            );
        }

        $date = $this->calculateExpiryDate(intval($maxAge));
        $this->info(sprintf('Used "default_max_age" setting to set expiry date of %s.', $date));

        return $date;
    }

    private function getOlderThanDate(array $typeSpec): ?string
    {
        // If an explicit older-than value is passed, use that
        $olderThan = $this->option('older-than');
        if (null !== $olderThan) {
            return $olderThan;
        }

        // Calculate expiry date from max. age application setting (sfConfig)
        if (isset($typeSpec['age_setting_name'])) {
            return $this->getDateFromAgeSetting($typeSpec['age_setting_name']);
        }

        // Calculate expiry date from local 'default_max_age' value
        if (isset($typeSpec['default_max_age'])) {
            return $this->getDateFromMaxAge($typeSpec['default_max_age']);
        }

        return null;
    }

    private function getExpireConfirmation(?string $expiryDate, string $typeNamePlural): bool
    {
        $message = 'Are you sure you want to delete';

        if (null !== $expiryDate) {
            $message .= sprintf(' %s older than %s', $typeNamePlural, $expiryDate);
        } else {
            $message .= sprintf(' all %s', $typeNamePlural);
        }

        $message .= '?';

        return $this->confirm($message);
    }

    private function accessLogExpireData(?string $expiryDate): int
    {
        if (null !== $expiryDate) {
            return \QubitAccessLog::expire($expiryDate);
        }

        return 0;
    }

    private function clipboardExpireData(?string $expiryDate): int
    {
        $criteria = new \Criteria();

        if (null !== $expiryDate) {
            $criteria->add(
                \QubitClipboardSave::CREATED_AT,
                $expiryDate,
                \Criteria::LESS_THAN
            );
        }

        $deletedCount = 0;

        foreach (\QubitClipboardSave::get($criteria) as $save) {
            $save->delete();
            ++$deletedCount;
        }

        return $deletedCount;
    }

    private function jobExpireData(?string $expiryDate): int
    {
        $criteria = new \Criteria();

        if (null !== $expiryDate) {
            $criteria->add(
                \QubitJob::CREATED_AT,
                $expiryDate,
                \Criteria::LESS_THAN
            );
        }

        $deletedCount = 0;

        foreach (\QubitJob::get($criteria) as $job) {
            if (!empty($job->downloadPath) && file_exists($job->downloadPath)) {
                unlink($job->downloadPath);
            }

            $job->delete();
            ++$deletedCount;
        }

        return $deletedCount;
    }
}
