<?php

declare(strict_types=1);

namespace AtoM\Framework\Extensions\Condition\Services;

use AtomExtensions\Helpers\CultureHelper;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;

/**
 * Condition Event Service
 * 
 * Manages structured condition events with full assessor tracking,
 * location mapping, and conservation history integration.
 * Implements Spectrum 5.0 Condition Checking procedure.
 * 
 * @author Johan Pieterse <johan@theahg.co.za>
 */
class ConditionEventService
{
    private static ?Logger $logger = null;

    private static function getLogger(): Logger
    {
        if (null === self::$logger) {
            self::$logger = new Logger('condition_event');
            $logPath = '/var/log/atom/condition_event.log';
            $logDir = dirname($logPath);
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            if (is_writable($logDir)) {
                self::$logger->pushHandler(new RotatingFileHandler($logPath, 30, Logger::DEBUG));
            }
        }
        return self::$logger;
    }

    /**
     * Create a structured condition event from an annotation
     */
    public static function createConditionEvent(
        int $photoId,
        array $annotationData,
        int $assessorId,
        ?string $assessmentDate = null
    ): ?int {
        try {
            // Get photo and condition check info
            $photo = DB::table('spectrum_condition_photo as p')
                ->join('spectrum_condition_check as c', 'p.condition_check_id', '=', 'c.id')
                ->where('p.id', $photoId)
                ->select(['p.*', 'c.object_id', 'c.check_date'])
                ->first();

            if (!$photo) {
                throw new \Exception('Photo not found');
            }

            // Get assessor info
            $assessor = DB::table('user')->where('id', $assessorId)->first();

            // Map annotation to condition event structure
            $eventId = DB::table('condition_event')->insertGetId([
                'condition_check_id' => $photo->condition_check_id,
                'photo_id' => $photoId,
                'object_id' => $photo->object_id,
                'event_type' => $annotationData['type'] ?? 'observation',
                'damage_type' => $annotationData['category'] ?? null,
                'severity' => $annotationData['severity'] ?? 'moderate',
                'location_on_object' => json_encode([
                    'zone' => $annotationData['zone'] ?? null,
                    'position' => $annotationData['position'] ?? null,
                    'coordinates' => [
                        'left' => $annotationData['left'] ?? 0,
                        'top' => $annotationData['top'] ?? 0,
                        'width' => $annotationData['width'] ?? 0,
                        'height' => $annotationData['height'] ?? 0,
                    ],
                ]),
                'description' => $annotationData['notes'] ?? $annotationData['label'] ?? '',
                'materials_affected' => json_encode($annotationData['materials'] ?? []),
                'treatment_priority' => self::calculatePriority($annotationData),
                'assessor_id' => $assessorId,
                'assessor_name' => $assessor->username ?? 'Unknown',
                'assessment_date' => $assessmentDate ?? date('Y-m-d'),
                'annotation_id' => $annotationData['id'] ?? null,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

            self::getLogger()->info('Condition event created', [
                'event_id' => $eventId,
                'photo_id' => $photoId,
                'damage_type' => $annotationData['category'] ?? 'unknown',
            ]);

            return $eventId;

        } catch (\Exception $e) {
            self::getLogger()->error('Failed to create condition event', [
                'photo_id' => $photoId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Get condition events for an object
     */
    public static function getEventsForObject(int $objectId, ?string $eventType = null): array
    {
        $query = DB::table('condition_event as ce')
            ->leftJoin('user as u', 'ce.assessor_id', '=', 'u.id')
            ->leftJoin('spectrum_condition_photo as p', 'ce.photo_id', '=', 'p.id')
            ->where('ce.object_id', $objectId)
            ->select([
                'ce.*',
                'u.username as assessor_username',
                'p.filename as photo_filename',
                'p.thumbnail as photo_thumbnail',
            ])
            ->orderByDesc('ce.assessment_date');

        if ($eventType) {
            $query->where('ce.event_type', $eventType);
        }

        return $query->get()->toArray();
    }

    /**
     * Get condition events for a condition check
     */
    public static function getEventsForCheck(int $checkId): array
    {
        return DB::table('condition_event as ce')
            ->leftJoin('user as u', 'ce.assessor_id', '=', 'u.id')
            ->leftJoin('spectrum_condition_photo as p', 'ce.photo_id', '=', 'p.id')
            ->where('ce.condition_check_id', $checkId)
            ->select([
                'ce.*',
                'u.username as assessor_username',
                'p.filename as photo_filename',
                'p.thumbnail as photo_thumbnail',
            ])
            ->orderBy('ce.treatment_priority', 'desc')
            ->orderByDesc('ce.created_at')
            ->get()
            ->toArray();
    }

    /**
     * Link condition event to conservation treatment
     */
    public static function linkToConservation(
        int $eventId,
        int $treatmentId,
        ?string $notes = null
    ): bool {
        try {
            DB::table('condition_conservation_link')->insert([
                'condition_event_id' => $eventId,
                'conservation_treatment_id' => $treatmentId,
                'link_type' => 'treatment',
                'notes' => $notes,
                'created_at' => date('Y-m-d H:i:s'),
            ]);

            // Update event status
            DB::table('condition_event')
                ->where('id', $eventId)
                ->update([
                    'treatment_status' => 'linked',
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);

            return true;

        } catch (\Exception $e) {
            self::getLogger()->error('Failed to link to conservation', [
                'event_id' => $eventId,
                'treatment_id' => $treatmentId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get conservation history for an object including condition events
     */
    public static function getConservationHistory(int $objectId): array
    {
        $history = [];

        // Get condition events
        $events = DB::table('condition_event')
            ->where('object_id', $objectId)
            ->orderByDesc('assessment_date')
            ->get();

        foreach ($events as $event) {
            $history[] = [
                'type' => 'condition_assessment',
                'date' => $event->assessment_date,
                'summary' => "{$event->damage_type}: {$event->description}",
                'severity' => $event->severity,
                'assessor' => $event->assessor_name,
                'source_id' => $event->id,
                'source_table' => 'condition_event',
            ];
        }

        // Get conservation treatments (if table exists)
        try {
            $treatments = DB::table('spectrum_conservation_treatment')
                ->where('object_id', $objectId)
                ->orderByDesc('treatment_date')
                ->get();

            foreach ($treatments as $treatment) {
                $history[] = [
                    'type' => 'conservation_treatment',
                    'date' => $treatment->treatment_date,
                    'summary' => $treatment->treatment_type . ': ' . $treatment->description,
                    'conservator' => $treatment->conservator_name ?? null,
                    'source_id' => $treatment->id,
                    'source_table' => 'spectrum_conservation_treatment',
                ];
            }
        } catch (\Exception $e) {
            // Table may not exist yet
        }

        // Sort by date descending
        usort($history, fn($a, $b) => strtotime($b['date']) - strtotime($a['date']));

        return $history;
    }

    /**
     * Update condition event
     */
    public static function updateEvent(int $eventId, array $data): bool
    {
        try {
            $data['updated_at'] = date('Y-m-d H:i:s');

            if (isset($data['location_on_object']) && is_array($data['location_on_object'])) {
                $data['location_on_object'] = json_encode($data['location_on_object']);
            }

            if (isset($data['materials_affected']) && is_array($data['materials_affected'])) {
                $data['materials_affected'] = json_encode($data['materials_affected']);
            }

            return DB::table('condition_event')
                ->where('id', $eventId)
                ->update($data) !== false;

        } catch (\Exception $e) {
            self::getLogger()->error('Failed to update event', [
                'event_id' => $eventId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Calculate treatment priority based on severity and damage type
     */
    private static function calculatePriority(array $annotationData): int
    {
        $severityScores = [
            'critical' => 100,
            'severe' => 80,
            'moderate' => 50,
            'minor' => 20,
            'stable' => 5,
        ];

        $damageTypeMultipliers = [
            'mould' => 1.5,
            'pest_damage' => 1.5,
            'water_damage' => 1.3,
            'structural_damage' => 1.3,
            'crack' => 1.2,
            'tear' => 1.1,
            'loss' => 1.0,
            'stain' => 0.8,
            'abrasion' => 0.7,
            'foxing' => 0.6,
        ];

        $severity = $annotationData['severity'] ?? 'moderate';
        $damageType = $annotationData['category'] ?? 'unknown';

        $baseScore = $severityScores[$severity] ?? 50;
        $multiplier = $damageTypeMultipliers[$damageType] ?? 1.0;

        return (int) round($baseScore * $multiplier);
    }

    /**
     * Get event statistics for an object
     */
    public static function getEventStats(int $objectId): array
    {
        return [
            'total_events' => DB::table('condition_event')
                ->where('object_id', $objectId)
                ->count(),
            'by_severity' => DB::table('condition_event')
                ->where('object_id', $objectId)
                ->selectRaw('severity, COUNT(*) as count')
                ->groupBy('severity')
                ->pluck('count', 'severity')
                ->toArray(),
            'by_damage_type' => DB::table('condition_event')
                ->where('object_id', $objectId)
                ->whereNotNull('damage_type')
                ->selectRaw('damage_type, COUNT(*) as count')
                ->groupBy('damage_type')
                ->pluck('count', 'damage_type')
                ->toArray(),
            'requiring_treatment' => DB::table('condition_event')
                ->where('object_id', $objectId)
                ->where('treatment_priority', '>=', 70)
                ->whereNull('treatment_status')
                ->count(),
            'last_assessment' => DB::table('condition_event')
                ->where('object_id', $objectId)
                ->max('assessment_date'),
        ];
    }

    /**
     * Bulk create events from annotations
     */
    public static function createEventsFromAnnotations(
        int $photoId,
        array $annotations,
        int $assessorId,
        ?string $assessmentDate = null
    ): array {
        $results = [];

        foreach ($annotations as $annotation) {
            $eventId = self::createConditionEvent(
                $photoId,
                $annotation,
                $assessorId,
                $assessmentDate
            );

            $results[] = [
                'annotation_id' => $annotation['id'] ?? null,
                'event_id' => $eventId,
                'success' => $eventId !== null,
            ];
        }

        return $results;
    }

    /**
     * Get recent condition events
     */
    public static function getRecentConditionEvents(int $limit = 20): array
    {
        try {
            return DB::table('condition_event as ce')
                ->leftJoin('condition_check_photo as ccp', 'ce.photo_id', '=', 'ccp.id')
                ->leftJoin('condition_check as cc', 'ccp.condition_check_id', '=', 'cc.id')
                ->leftJoin('information_object_i18n as io', function($join) {
                    $join->on('cc.object_id', '=', 'io.id')
                         ->where('io.culture', '=', CultureHelper::getCulture());
                })
                ->leftJoin('user as u', 'ce.assessor_id', '=', 'u.id')
                ->select(
                    'ce.id',
                    'ce.damage_type',
                    'ce.severity',
                    'ce.created_at',
                    'io.title as object_title',
                    'u.username as assessor'
                )
                ->orderBy('ce.created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn($row) => (array)$row)
                ->toArray();
        } catch (\Exception $e) {
            self::getLogger()->error('Failed to get recent events: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get condition statistics
     */
    public static function getConditionStats(): array
    {
        try {
            $total = DB::table('condition_check')->count();
            $byCondition = DB::table('condition_check')
                ->select('overall_condition', DB::raw('COUNT(*) as count'))
                ->groupBy('overall_condition')
                ->pluck('count', 'overall_condition')
                ->toArray();
            
            $byPriority = DB::table('condition_check')
                ->select('priority', DB::raw('COUNT(*) as count'))
                ->whereNotNull('priority')
                ->groupBy('priority')
                ->pluck('count', 'priority')
                ->toArray();
            
            $urgent = DB::table('condition_check')
                ->where('priority', 'urgent')
                ->count();
            
            $thisMonth = DB::table('condition_check')
                ->where('check_date', '>=', date('Y-m-01'))
                ->count();
            
            return [
                'total' => $total,
                'by_condition' => $byCondition,
                'by_priority' => $byPriority,
                'urgent' => $urgent,
                'this_month' => $thisMonth
            ];
        } catch (\Exception $e) {
            self::getLogger()->error('Failed to get stats: ' . $e->getMessage());
            return [
                'total' => 0,
                'by_condition' => [],
                'by_priority' => [],
                'urgent' => 0,
                'this_month' => 0
            ];
        }
    }

    /**
     * Get pending condition checks
     */
    public static function getPendingChecks(int $limit = 10): array
    {
        try {
            return DB::table('condition_check as cc')
                ->leftJoin('information_object_i18n as io', function($join) {
                    $join->on('cc.object_id', '=', 'io.id')
                         ->where('io.culture', '=', CultureHelper::getCulture());
                })
                ->select(
                    'cc.id',
                    'cc.object_id',
                    'io.title',
                    'cc.check_date',
                    'cc.overall_condition',
                    'cc.priority'
                )
                ->where('cc.status', 'pending')
                ->orWhereNull('cc.status')
                ->orderBy('cc.check_date', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn($row) => (array)$row)
                ->toArray();
        } catch (\Exception $e) {
            self::getLogger()->error('Failed to get pending checks: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get condition checks requiring action
     */
    public static function getRequiringAction(int $limit = 10): array
    {
        try {
            return DB::table('condition_check as cc')
                ->leftJoin('information_object_i18n as io', function($join) {
                    $join->on('cc.object_id', '=', 'io.id')
                         ->where('io.culture', '=', CultureHelper::getCulture());
                })
                ->select(
                    'cc.id',
                    'cc.object_id',
                    'io.title',
                    'cc.overall_condition',
                    'cc.priority',
                    'cc.recommended_action'
                )
                ->whereIn('cc.priority', ['urgent', 'high'])
                ->orderByRaw("FIELD(cc.priority, 'urgent', 'high')")
                ->orderBy('cc.check_date', 'desc')
                ->limit($limit)
                ->get()
                ->map(fn($row) => (array)$row)
                ->toArray();
        } catch (\Exception $e) {
            self::getLogger()->error('Failed to get requiring action: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get pending scheduled assessments
     */
    public static function getPendingScheduledAssessments(int $limit = 10): array
    {
        try {
            // Check if scheduled_assessment table exists
            if (!DB::getSchemaBuilder()->hasTable('scheduled_assessment')) {
                return [];
            }
            
            return DB::table('scheduled_assessment as sa')
                ->leftJoin('information_object_i18n as io', function($join) {
                    $join->on('sa.object_id', '=', 'io.id')
                         ->where('io.culture', '=', CultureHelper::getCulture());
                })
                ->select(
                    'sa.id',
                    'sa.object_id',
                    'io.title',
                    'sa.scheduled_date',
                    'sa.assessment_type',
                    'sa.status'
                )
                ->where('sa.scheduled_date', '>=', date('Y-m-d'))
                ->where(function($q) {
                    $q->where('sa.status', 'pending')
                      ->orWhereNull('sa.status');
                })
                ->orderBy('sa.scheduled_date', 'asc')
                ->limit($limit)
                ->get()
                ->map(fn($row) => (array)$row)
                ->toArray();
        } catch (\Exception $e) {
            self::getLogger()->error('Failed to get pending scheduled: ' . $e->getMessage());
            return [];
        }
    }
}