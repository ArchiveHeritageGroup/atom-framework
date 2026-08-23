<?php

namespace AtomFramework\Console\Commands\DigitalObject;

use AtomFramework\Console\BaseCommand;
use AtomExtensions\Services\ImageMetadataScrubber;
use Illuminate\Database\Capsule\Manager as DB;

/**
 * Remove embedded location metadata from digital object derivatives.
 *
 * The scrub applied at derivative-generation time only protects files made from
 * then on. Everything already uploaded still carries the coordinate its camera
 * wrote, and those are the files being served today - so a sweep is the half of
 * the fix that actually closes the existing exposure.
 *
 * Derivatives only, by design. Masters are preservation copies and keep their
 * metadata; they need access control, not redaction.
 */
class DigitalObjectScrubLocationCommand extends BaseCommand
{
    protected string $name = 'digitalobject:scrub-location';
    protected string $description = 'Strip embedded GPS metadata from digital object derivatives';
    protected string $detailedDescription = <<<'EOF'
Strips GPS/EXIF location metadata from reference and thumbnail derivatives.

A photograph carries its own coordinate. Because a derivative is generated with
`convert`, which copies EXIF into the output, a served derivative can publish the
exact position of a site whose record only shows a coarsened one - defeating the
locality gate without going anywhere near it.

Masters are deliberately left alone: they are the preservation copy, and the
answer for them is access control rather than stripping provenance.

Run --dry-run first. It reports which files still declare a position without
changing anything.
EOF;

    protected function configure(): void
    {
        $this->addOption('dry-run', 'd', 'Report what would be scrubbed, change nothing');
        $this->addOption('limit', null, 'Stop after this many derivatives');
        $this->addOption('object-id', null, 'Restrict to one information object');
    }

    protected function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $limit = (int) ($this->option('limit') ?? 0);
        $objectId = (int) ($this->option('object-id') ?? 0);
        $rootDir = rtrim($this->atomRoot, '/');

        $query = DB::table('digital_object')
            ->whereIn('usage_id', [141, 142])   // reference, thumbnail
            ->orderBy('id');

        if ($objectId > 0) {
            // Derivatives hang off the master, so resolve through the parent.
            $masterIds = DB::table('digital_object')
                ->where('object_id', $objectId)->where('usage_id', 140)->pluck('id')->all();
            $query->whereIn('parent_id', $masterIds ?: [0]);
        }

        if ($limit > 0) {
            $query->limit($limit);
        }

        $rows = $query->get(['id', 'path', 'name']);

        $checked = $carrying = $scrubbed = $failed = $missing = 0;

        foreach ($rows as $row) {
            $path = $rootDir.$row->path.$row->name;
            ++$checked;

            if (!is_file($path)) {
                ++$missing;

                continue;
            }

            $declares = ImageMetadataScrubber::hasGps($path);

            if (false === $declares) {
                continue;
            }

            // null means we could not inspect it - a scrubbable format this host
            // cannot read (HEIC and WebP without exiftool, for instance). Treat
            // unknown as "scrub it": skipping is how the files most likely to be
            // carrying a coordinate would survive the sweep.
            ++$carrying;

            if ($dryRun) {
                $this->line(sprintf('  would scrub: %s', $path));

                continue;
            }

            $result = ImageMetadataScrubber::scrub($path);

            if ($result['ok'] && true !== ImageMetadataScrubber::hasGps($path)) {
                ++$scrubbed;
            } else {
                ++$failed;
                // Named individually: a file that still carries a position after a
                // sweep that reported success is exactly the thing an operator must
                // not have to go looking for.
                $this->line(sprintf(
                    '  FAILED: %s (%s: %s)',
                    $path,
                    $result['method'],
                    $result['reason'] ?? 'still reports GPS after scrub'
                ));
            }
        }

        $this->line('');
        $this->line(sprintf('  derivatives checked      : %d', $checked));
        $this->line(sprintf('  files missing on disk    : %d', $missing));
        $this->line(sprintf('  carrying, or uninspectable: %d', $carrying));

        if ($dryRun) {
            $this->line('  dry run - nothing changed');

            return 0;
        }

        $this->line(sprintf('  scrubbed                 : %d', $scrubbed));
        $this->line(sprintf('  FAILED                   : %d', $failed));

        // A partial sweep must not report success: the remaining files are still
        // publishing coordinates.
        return $failed > 0 ? 1 : 0;
    }
}
