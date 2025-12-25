<?php

declare(strict_types=1);

namespace AtomExtensions\Helpers;

/**
 * Qubit Helper - Provides helper functions previously in Qubit class
 */
class QubitHelper
{
    /**
     * Render date with start/end range
     * 
     * Replaces: Qubit::renderDateStartEnd($date, $start, $end)
     */
    public static function renderDateStartEnd(?string $date, ?string $startDate, ?string $endDate): string
    {
        if (!empty($date)) {
            return $date;
        }
        
        if (empty($startDate) && empty($endDate)) {
            return '';
        }
        
        if ($startDate === $endDate || empty($endDate)) {
            return self::formatDate($startDate);
        }
        
        if (empty($startDate)) {
            return self::formatDate($endDate);
        }
        
        return self::formatDate($startDate) . ' - ' . self::formatDate($endDate);
    }
    
    /**
     * Format a date string
     */
    private static function formatDate(?string $date): string
    {
        if (empty($date)) {
            return '';
        }
        
        // Check if it's just a year
        if (preg_match('/^\d{4}$/', $date)) {
            return $date;
        }
        
        // Check if it's YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $timestamp = strtotime($date);
            if ($timestamp) {
                return date('Y-m-d', $timestamp);
            }
        }
        
        return $date;
    }
}
