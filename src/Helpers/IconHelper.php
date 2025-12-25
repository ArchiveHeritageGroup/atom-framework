<?php

namespace AtomFramework\Helpers;

class IconHelper
{
    protected static array $iconMap = [
        // Navigation
        'home' => 'bi-house',
        'search' => 'bi-search',
        'browse' => 'bi-folder',
        'menu' => 'bi-list',
        'back' => 'bi-arrow-left',
        'forward' => 'bi-arrow-right',
        
        // Actions
        'add' => 'bi-plus-lg',
        'edit' => 'bi-pencil',
        'delete' => 'bi-trash',
        'save' => 'bi-check-lg',
        'cancel' => 'bi-x-lg',
        'download' => 'bi-download',
        'upload' => 'bi-upload',
        'print' => 'bi-printer',
        'copy' => 'bi-clipboard',
        'link' => 'bi-link-45deg',
        'refresh' => 'bi-arrow-clockwise',
        'settings' => 'bi-gear',
        'configure' => 'bi-sliders',
        
        // Content types
        'archive' => 'bi-archive',
        'folder' => 'bi-folder',
        'folder-open' => 'bi-folder2-open',
        'file' => 'bi-file-earmark',
        'file-text' => 'bi-file-earmark-text',
        'file-pdf' => 'bi-file-earmark-pdf',
        'file-image' => 'bi-file-earmark-image',
        'image' => 'bi-image',
        'images' => 'bi-images',
        'video' => 'bi-camera-video',
        'audio' => 'bi-music-note',
        'document' => 'bi-file-earmark-text',
        
        // AtoM specific
        'description' => 'bi-card-text',
        'authority' => 'bi-person-badge',
        'repository' => 'bi-building',
        'function' => 'bi-diagram-3',
        'place' => 'bi-geo-alt',
        'subject' => 'bi-tag',
        'actor' => 'bi-person',
        'event' => 'bi-calendar-event',
        'rights' => 'bi-shield-check',
        'accession' => 'bi-box-arrow-in-down',
        'deaccession' => 'bi-box-arrow-up',
        
        // GLAM sectors
        'museum' => 'bi-bank',
        'library' => 'bi-book',
        'gallery' => 'bi-easel',
        'archive-sector' => 'bi-archive',
        
        // Users & Security
        'user' => 'bi-person',
        'users' => 'bi-people',
        'group' => 'bi-people-fill',
        'login' => 'bi-box-arrow-in-right',
        'logout' => 'bi-box-arrow-right',
        'lock' => 'bi-lock',
        'unlock' => 'bi-unlock',
        'key' => 'bi-key',
        'shield' => 'bi-shield',
        'security' => 'bi-shield-lock',
        
        // Status
        'check' => 'bi-check-lg',
        'success' => 'bi-check-circle',
        'error' => 'bi-x-circle',
        'warning' => 'bi-exclamation-triangle',
        'info' => 'bi-info-circle',
        'question' => 'bi-question-circle',
        'pending' => 'bi-hourglass-split',
        'processing' => 'bi-arrow-repeat',
        'draft' => 'bi-pencil-square',
        'published' => 'bi-globe',
        
        // UI elements
        'expand' => 'bi-chevron-down',
        'collapse' => 'bi-chevron-up',
        'chevron-right' => 'bi-chevron-right',
        'chevron-left' => 'bi-chevron-left',
        'more' => 'bi-three-dots',
        'more-vertical' => 'bi-three-dots-vertical',
        'drag' => 'bi-grip-vertical',
        'sort' => 'bi-sort-down',
        'filter' => 'bi-funnel',
        'calendar' => 'bi-calendar',
        'clock' => 'bi-clock',
        'star' => 'bi-star',
        'star-fill' => 'bi-star-fill',
        
        // Admin
        'dashboard' => 'bi-speedometer2',
        'admin' => 'bi-gear-wide-connected',
        'report' => 'bi-file-earmark-bar-graph',
        'chart' => 'bi-bar-chart',
        'database' => 'bi-database',
        'server' => 'bi-hdd-stack',
        'plugin' => 'bi-plug',
        'extension' => 'bi-puzzle',
        'theme' => 'bi-palette',
        'backup' => 'bi-cloud-download',
        'restore' => 'bi-cloud-upload',
        'import' => 'bi-box-arrow-in-down',
        'export' => 'bi-box-arrow-up',
        'log' => 'bi-journal-text',
        'audit' => 'bi-clipboard-check',
        
        // Communication
        'email' => 'bi-envelope',
        'phone' => 'bi-telephone',
        'chat' => 'bi-chat',
        'comment' => 'bi-chat-dots',
        'notification' => 'bi-bell',
        
        // Misc
        'help' => 'bi-question-circle',
        'external' => 'bi-box-arrow-up-right',
        'code' => 'bi-code',
        'terminal' => 'bi-terminal',
        'globe' => 'bi-globe',
        'map' => 'bi-map',
        'location' => 'bi-geo-alt',
        'tag' => 'bi-tag',
        'tags' => 'bi-tags',
    ];

    public static function get(string $key, string $fallback = 'bi-circle'): string
    {
        if (str_starts_with($key, 'bi-')) {
            return $key;
        }
        if (str_starts_with($key, 'fa-')) {
            return $key;
        }
        return self::$iconMap[$key] ?? $fallback;
    }

    public static function render(string $key, array $attributes = []): string
    {
        $class = self::get($key);
        $attrClass = $attributes['class'] ?? '';
        unset($attributes['class']);
        
        $fullClass = trim("bi {$class} {$attrClass}");
        
        $attrString = "class=\"{$fullClass}\"";
        foreach ($attributes as $attr => $value) {
            $attrString .= " {$attr}=\"" . htmlspecialchars($value) . "\"";
        }

        return "<i {$attrString}></i>";
    }

    public static function all(): array
    {
        return self::$iconMap;
    }

    public static function register(string $key, string $iconClass): void
    {
        self::$iconMap[$key] = $iconClass;
    }

    public static function registerMany(array $icons): void
    {
        self::$iconMap = array_merge(self::$iconMap, $icons);
    }
}
