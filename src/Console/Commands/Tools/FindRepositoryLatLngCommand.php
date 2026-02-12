<?php

namespace AtomFramework\Console\Commands\Tools;

use AtomFramework\Console\BaseCommand;
use AtomFramework\Bridges\PropelBridge;

/**
 * Find and set latitude/longitude for repositories via geocoding.
 *
 * Ported from lib/task/tools/findRepositoryLatLngTask.class.php.
 * Uses Propel for contact information iteration and object saves.
 * Calls Google Maps geocoding API to resolve addresses.
 */
class FindRepositoryLatLngCommand extends BaseCommand
{
    protected string $name = 'tools:find-repository-latlng';
    protected string $description = 'Find and set latitude/longitude for repositories via geocoding';
    protected string $detailedDescription = <<<'EOF'
Search for the lat/lng values of your repository contacts via Google Maps
geocoding API and save them to the database.

This task won't overwrite existing values unless you use "--overwrite".

NOTE: Requires a valid Google Maps API key or access to the geocoding endpoint.
EOF;

    private int $errorCount = 0;

    protected function configure(): void
    {
        $this->addOption('overwrite', null, 'Overwrite existing lat/lng values');
    }

    protected function handle(): int
    {
        PropelBridge::boot($this->atomRoot);

        foreach (\QubitContactInformation::getAll() as $item) {
            if (!empty($item->latitude) || !empty($item->longitude)) {
                if (!$this->hasOption('overwrite')) {
                    $this->info(sprintf('Skipping entry (%s, %s)', $item->latitude, $item->longitude));
                    continue;
                }
            }

            $address = [];

            foreach (['streetAddress', 'city', 'region', 'postalCode', 'countryCode'] as $field) {
                if (isset($item->{$field}) && !empty($item->{$field})) {
                    $address[] = $item->{$field};
                }
            }

            if (count($address) > 0) {
                $result = $this->getLatLng(implode(', ', $address));

                if (null !== $result) {
                    list($lat, $lng) = $result;
                    $item->latitude = $lat;
                    $item->longitude = $lng;

                    $item->save();

                    $this->success('Saved!');
                    $this->newline();
                } else {
                    ++$this->errorCount;
                }
            }
        }

        $this->info(sprintf('Summary: %s errors.', $this->errorCount));

        return 0;
    }

    private function getLatLng(string $address): ?array
    {
        $url = sprintf(
            'http://maps.googleapis.com/maps/api/geocode/json?address=%s&sensor=false',
            urlencode($address)
        );

        $response = @file_get_contents($url);

        if (false === $response) {
            $this->warning(sprintf('Failed to locate address: %s.', $this->wordLimiter($address)));
            return null;
        }

        $data = json_decode($response);
        $data = array_pop($data->results);

        if (!$data || !isset($data->geometry->location->lat) || !isset($data->geometry->location->lng)) {
            $this->warning('Could not parse geocoding response.');
            return null;
        }

        $lat = $data->geometry->location->lat;
        $lng = $data->geometry->location->lng;

        $address = $this->wordLimiter($address);
        $address = preg_replace('/[\n\r\f]+/m', ', ', $address);

        $this->line(sprintf('Address: %s', $address));

        if (!is_float($lat) || !is_float($lng)) {
            $this->error('ERROR!');
            $this->newline();
            return null;
        }

        $this->line(sprintf('Latitude: %s. Longitude: %s.', $lat, $lng));

        return [$lat, $lng];
    }

    private function wordLimiter(string $str, int $limit = 100, string $endChar = '...'): string
    {
        if ('' == trim($str)) {
            return $str;
        }

        preg_match('/^\s*+(?:\S++\s*+){1,' . (int) $limit . '}/', $str, $matches);

        if (strlen($str) == strlen($matches[0])) {
            $endChar = '';
        }

        return rtrim($matches[0]) . $endChar;
    }
}
