<?php

declare(strict_types=1);

namespace AtomFramework\Extensions\Spectrum\Services;

use AtomExtensions\Helpers\CultureHelper;

use Illuminate\Database\Capsule\Manager as DB;
use Monolog\Logger;
use Monolog\Handler\RotatingFileHandler;

/**
 * Provenance Service
 * 
 * Manages and displays provenance (ownership history) for objects
 * 
 * @package AtomFramework\Extensions\Spectrum
 * @author Johan Pieterse - The Archive and Heritage Group
 * @version 1.0.0
 */
class ProvenanceService
{
    private Logger $logger;
    
    public function __construct()
    {
        $this->logger = new Logger('spectrum-provenance');
        $logPath = '/var/log/atom/spectrum-provenance.log';
        
        if (is_writable(dirname($logPath))) {
            $this->logger->pushHandler(new RotatingFileHandler($logPath, 30, Logger::INFO));
        }
    }
    
    /**
     * Get provenance history for an object
     */
    public function getProvenance(int $objectId): array
    {
        // Get from museum_object_properties if available
        $properties = DB::table('museum_object_properties')
            ->where('information_object_id', $objectId)
            ->first();
            
        $provenanceText = $properties->provenance ?? null;
        
        // Get acquisition events
        $events = $this->getAcquisitionEvents($objectId);
        
        // Get custody/ownership events from Spectrum
        $custodyEvents = $this->getCustodyEvents($objectId);
        
        // Get loan history
        $loans = $this->getLoanHistory($objectId);
        
        return [
            'provenance_text' => $provenanceText,
            'acquisition_events' => $events,
            'custody_events' => $custodyEvents,
            'loan_history' => $loans,
            'timeline' => $this->buildTimeline($events, $custodyEvents, $loans)
        ];
    }
    
    /**
     * Get acquisition events
     */
    private function getAcquisitionEvents(int $objectId): array
    {
        $sql = "
            SELECT 
                e.id,
                e.start_date,
                e.end_date,
                e.description,
                e.type_id,
                ti18n.name as event_type,
                actor_i18n.authorized_form_of_name as actor_name,
                place_i18n.name as place_name
            FROM event e
            LEFT JOIN term_i18n ti18n ON e.type_id = ti18n.id AND ti18n.culture = '" . CultureHelper::getCulture() . "'
            LEFT JOIN actor_i18n ON e.actor_id = actor_i18n.id AND actor_i18n.culture = '" . CultureHelper::getCulture() . "'
            LEFT JOIN place ON e.place_id = place.id
            LEFT JOIN place_i18n ON place.id = place_i18n.id AND place_i18n.culture = '" . CultureHelper::getCulture() . "'
            WHERE e.object_id = :object_id
            AND ti18n.name IN ('Acquisition', 'Accumulation', 'Transfer', 'Gift', 'Purchase', 'Bequest')
            ORDER BY e.start_date ASC
        ";
        
        return DB::select($sql, ['object_id' => $objectId]);
    }
    
    /**
     * Get custody/ownership events from Spectrum
     */
    private function getCustodyEvents(int $objectId): array
    {
        return DB::table('spectrum_event')
            ->where('object_id', $objectId)
            ->whereIn('procedure_id', ['object_entry', 'acquisition', 'deaccession', 'transfer_of_custody'])
            ->orderBy('created_at', 'asc')
            ->get()
            ->toArray();
    }
    
    /**
     * Get loan history
     */
    private function getLoanHistory(int $objectId): array
    {
        return DB::table('spectrum_loan as l')
            ->join('spectrum_loan_item as li', 'l.id', '=', 'li.loan_id')
            ->where('li.object_id', $objectId)
            ->select('l.*', 'li.condition_on_departure', 'li.condition_on_return')
            ->orderBy('l.loan_start_date', 'desc')
            ->get()
            ->toArray();
    }
    
    /**
     * Build combined timeline
     */
    private function buildTimeline(array $acquisitions, array $custody, array $loans): array
    {
        $timeline = [];
        
        // Add acquisition events
        foreach ($acquisitions as $event) {
            $event = (object) $event;
            $timeline[] = [
                'date' => $event->start_date ?? 'Unknown',
                'type' => 'acquisition',
                'title' => $event->event_type ?? 'Acquisition',
                'description' => trim(($event->actor_name ?? '') . ' ' . ($event->description ?? '')),
                'location' => $event->place_name ?? null
            ];
        }
        
        // Add custody events
        foreach ($custody as $event) {
            $event = (object) $event;
            $metadata = json_decode($event->metadata ?? '{}', true);
            $timeline[] = [
                'date' => $event->created_at ?? 'Unknown',
                'type' => 'custody',
                'title' => ucwords(str_replace('_', ' ', $event->procedure_id)),
                'description' => $event->notes ?? '',
                'status' => $event->status_to ?? null
            ];
        }
        
        // Add loans
        foreach ($loans as $loan) {
            $loan = (object) $loan;
            
            // Loan out
            if ($loan->loan_start_date) {
                $timeline[] = [
                    'date' => $loan->loan_start_date,
                    'type' => 'loan_out',
                    'title' => 'Loaned - ' . $loan->loan_number,
                    'description' => 'Purpose: ' . ($loan->purpose ?? 'Not specified'),
                    'borrower' => $loan->contact_name ?? null
                ];
            }
            
            // Loan return
            if ($loan->actual_return_date) {
                $timeline[] = [
                    'date' => $loan->actual_return_date,
                    'type' => 'loan_return',
                    'title' => 'Returned - ' . $loan->loan_number,
                    'description' => 'Condition: ' . ($loan->condition_on_return ?? 'Not recorded')
                ];
            }
        }
        
        // Sort by date
        usort($timeline, function($a, $b) {
            return strcmp($a['date'] ?? '', $b['date'] ?? '');
        });
        
        return $timeline;
    }
    
    /**
     * Update provenance text
     */
    public function updateProvenance(int $objectId, string $provenanceText): bool
    {
        $this->logger->info("Updating provenance for object {$objectId}");
        
        // Check if museum_object_properties exists
        $exists = DB::table('museum_object_properties')
            ->where('information_object_id', $objectId)
            ->exists();
            
        if ($exists) {
            return DB::table('museum_object_properties')
                ->where('information_object_id', $objectId)
                ->update([
                    'provenance' => $provenanceText,
                    'updated_at' => now()
                ]) > 0;
        } else {
            return DB::table('museum_object_properties')->insert([
                'information_object_id' => $objectId,
                'provenance' => $provenanceText,
                'created_at' => now(),
                'updated_at' => now()
            ]) > 0;
        }
    }
    
    /**
     * Generate provenance report HTML
     */
    public function generateProvenanceReport(int $objectId): string
    {
        $provenance = $this->getProvenance($objectId);
        
        // Get object details
        $object = DB::table('information_object as io')
            ->leftJoin('information_object_i18n as i18n', function($join) {
                $join->on('io.id', '=', 'i18n.id')
                     ->on('io.source_culture', '=', 'i18n.culture');
            })
            ->where('io.id', $objectId)
            ->select('io.id', 'io.identifier', 'i18n.title')
            ->first();
            
        $html = "<!DOCTYPE html><html><head><meta charset='utf-8'>";
        $html .= "<style>
            body { font-family: Arial, sans-serif; margin: 20mm; }
            h1 { font-size: 18pt; border-bottom: 2px solid #333; padding-bottom: 5px; }
            h2 { font-size: 14pt; color: #333; margin-top: 20px; }
            .object-info { background: #f5f5f5; padding: 10px; margin-bottom: 20px; }
            .timeline { border-left: 3px solid #007bff; padding-left: 20px; margin-left: 10px; }
            .timeline-item { margin-bottom: 15px; position: relative; }
            .timeline-item::before { content: ''; position: absolute; left: -26px; top: 5px; width: 12px; height: 12px; background: #007bff; border-radius: 50%; }
            .timeline-date { font-weight: bold; color: #007bff; }
            .timeline-title { font-weight: bold; }
            .provenance-text { white-space: pre-wrap; background: #fff; border: 1px solid #ddd; padding: 15px; }
        </style></head><body>";
        
        $html .= "<h1>Provenance Report</h1>";
        
        $html .= "<div class='object-info'>";
        $html .= "<strong>Object:</strong> " . htmlspecialchars($object->title ?? 'Untitled') . "<br>";
        $html .= "<strong>Identifier:</strong> " . htmlspecialchars($object->identifier ?? '') . "<br>";
        $html .= "<strong>Report Date:</strong> " . date('Y-m-d');
        $html .= "</div>";
        
        // Provenance text
        if (!empty($provenance['provenance_text'])) {
            $html .= "<h2>Provenance Statement</h2>";
            $html .= "<div class='provenance-text'>" . nl2br(htmlspecialchars($provenance['provenance_text'])) . "</div>";
        }
        
        // Timeline
        if (!empty($provenance['timeline'])) {
            $html .= "<h2>Ownership/Custody Timeline</h2>";
            $html .= "<div class='timeline'>";
            
            foreach ($provenance['timeline'] as $event) {
                $html .= "<div class='timeline-item'>";
                $html .= "<div class='timeline-date'>" . htmlspecialchars($event['date']) . "</div>";
                $html .= "<div class='timeline-title'>" . htmlspecialchars($event['title']) . "</div>";
                if (!empty($event['description'])) {
                    $html .= "<div class='timeline-desc'>" . htmlspecialchars($event['description']) . "</div>";
                }
                $html .= "</div>";
            }
            
            $html .= "</div>";
        }
        
        $html .= "</body></html>";
        
        return $html;
    }
}
