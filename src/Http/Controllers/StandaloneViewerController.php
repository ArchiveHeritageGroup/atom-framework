<?php

namespace AtomFramework\Http\Controllers;

use AtomFramework\Http\Compatibility\SfContextAdapter;
use AtomFramework\Services\ConfigService;
use AtomFramework\Views\BladeRenderer;
use Illuminate\Database\Capsule\Manager as DB;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Universal entity viewer for standalone mode.
 *
 * Loads any AtoM entity by slug using Laravel Query Builder and renders
 * a generic detail view showing all i18n fields. Used when the original
 * action classes (sfIsadPlugin, sfIsaarPlugin, etc.) cannot be loaded
 * because they depend on Propel/Symfony.
 *
 * Supports: InformationObject, Actor, Repository, Term, Accession,
 *           Function, RightsHolder, Donor, PhysicalObject, StaticPage.
 */
class StandaloneViewerController
{
    /**
     * Map Propel class names → entity table config.
     *
     * Each entry has:
     *   'table'      → main table name
     *   'i18n'       → i18n table name (nullable)
     *   'label'      → human-readable entity type name
     *   'title_col'  → column used for page title (in i18n or main table)
     */
    private const ENTITY_MAP = [
        'QubitInformationObject' => [
            'table' => 'information_object',
            'i18n' => 'information_object_i18n',
            'label' => 'Archival Description',
            'title_col' => 'title',
        ],
        'QubitActor' => [
            'table' => 'actor',
            'i18n' => 'actor_i18n',
            'label' => 'Authority Record',
            'title_col' => 'authorized_form_of_name',
        ],
        'QubitRepository' => [
            'table' => 'repository',
            'i18n' => 'repository_i18n',
            'label' => 'Archival Institution',
            'title_col' => 'authorized_form_of_name',
        ],
        'QubitTerm' => [
            'table' => 'term',
            'i18n' => 'term_i18n',
            'label' => 'Term',
            'title_col' => 'name',
        ],
        'QubitAccession' => [
            'table' => 'accession',
            'i18n' => 'accession_i18n',
            'label' => 'Accession',
            'title_col' => 'title',
        ],
        'QubitFunction' => [
            'table' => 'function_object',
            'i18n' => 'function_object_i18n',
            'label' => 'Function',
            'title_col' => 'authorized_form_of_name',
        ],
        'QubitRightsHolder' => [
            'table' => 'rights_holder',
            'i18n' => 'rights_holder_i18n',
            'label' => 'Rights Holder',
            'title_col' => 'authorized_form_of_name',
        ],
        'QubitDonor' => [
            'table' => 'donor',
            'i18n' => null,
            'label' => 'Donor',
            'title_col' => null,
        ],
        'QubitPhysicalObject' => [
            'table' => 'physical_object',
            'i18n' => 'physical_object_i18n',
            'label' => 'Physical Storage',
            'title_col' => 'name',
        ],
        'QubitStaticPage' => [
            'table' => 'static_page',
            'i18n' => 'static_page_i18n',
            'label' => 'Static Page',
            'title_col' => 'title',
        ],
    ];

    /**
     * Columns to exclude from the detail display.
     */
    private const HIDDEN_COLUMNS = [
        'id', 'serial_number', 'lft', 'rgt', 'source_culture',
        'parent_id', 'class_name', 'created_at', 'updated_at',
        'source_standard', 'display_standard', 'display_standard_id',
        'level_of_description_id', 'description_status_id',
        'description_detail_id', 'repository_id', 'taxonomy_id',
    ];

    /**
     * Show an entity detail page by slug.
     *
     * @param Request $request The HTTP request (must have 'slug' route parameter)
     * @param string  $module  The resolved module name (e.g., 'sfIsadPlugin')
     */
    public function show(Request $request, string $module = ''): Response
    {
        $slug = $request->route()->parameter('slug')
            ?? $request->query('slug');

        if (!$slug) {
            return new Response($this->wrap($this->errorHtml('No slug provided.')), 400, [
                'Content-Type' => 'text/html',
            ]);
        }

        // Look up slug → object_id + class_name
        try {
            $slugRow = DB::table('slug')
                ->join('object', 'slug.object_id', '=', 'object.id')
                ->where('slug.slug', $slug)
                ->select('object.class_name', 'slug.object_id', 'object.created_at', 'object.updated_at')
                ->first();
        } catch (\Throwable $e) {
            return new Response($this->wrap($this->errorHtml('Database error.')), 500, [
                'Content-Type' => 'text/html',
            ]);
        }

        if (!$slugRow) {
            return new Response($this->wrap($this->errorHtml('Record not found: ' . htmlspecialchars($slug))), 404, [
                'Content-Type' => 'text/html',
            ]);
        }

        $className = $slugRow->class_name;
        $objectId = $slugRow->object_id;

        if (!isset(self::ENTITY_MAP[$className])) {
            return new Response($this->wrap($this->errorHtml(
                'Unsupported entity type: ' . htmlspecialchars($className)
            )), 404, ['Content-Type' => 'text/html']);
        }

        $config = self::ENTITY_MAP[$className];
        $culture = $this->getCulture();

        // Load main table data
        $mainData = [];
        try {
            $mainRow = DB::table($config['table'])->where('id', $objectId)->first();
            if ($mainRow) {
                $mainData = (array) $mainRow;
            }
        } catch (\Throwable $e) {
            // table might not exist in all installs
        }

        // Load i18n data
        $i18nData = [];
        if ($config['i18n']) {
            try {
                $i18nRow = DB::table($config['i18n'])
                    ->where('id', $objectId)
                    ->where('culture', $culture)
                    ->first();
                if ($i18nRow) {
                    $i18nData = (array) $i18nRow;
                }

                // Fallback to source culture if no translation
                if (empty($i18nData) && 'en' !== $culture) {
                    $i18nRow = DB::table($config['i18n'])
                        ->where('id', $objectId)
                        ->where('culture', 'en')
                        ->first();
                    if ($i18nRow) {
                        $i18nData = (array) $i18nRow;
                    }
                }
            } catch (\Throwable $e) {
                // table might not exist
            }
        }

        // Determine page title
        $title = '';
        if ($config['title_col'] && isset($i18nData[$config['title_col']])) {
            $title = $i18nData[$config['title_col']];
        } elseif ($config['title_col'] && isset($mainData[$config['title_col']])) {
            $title = $mainData[$config['title_col']];
        }
        if (!$title) {
            $title = $slug;
        }

        // Load related notes (for IO, actors)
        $notes = [];
        try {
            $notes = DB::table('note')
                ->join('note_i18n', 'note.id', '=', 'note_i18n.id')
                ->where('note.object_id', $objectId)
                ->where('note_i18n.culture', $culture)
                ->select('note_i18n.content', 'note.type_id')
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            // notes table may not be accessible
        }

        // Load events (for IO — dates, creators)
        $events = [];
        if ('QubitInformationObject' === $className) {
            try {
                $events = DB::table('event')
                    ->leftJoin('event_i18n', function ($join) use ($culture) {
                        $join->on('event.id', '=', 'event_i18n.id')
                            ->where('event_i18n.culture', '=', $culture);
                    })
                    ->where('event.object_id', $objectId)
                    ->select(
                        'event_i18n.date',
                        'event_i18n.name as event_name',
                        'event.start_date',
                        'event.end_date',
                        'event.type_id',
                        'event.actor_id'
                    )
                    ->get()
                    ->toArray();
            } catch (\Throwable $e) {
                // events may not load
            }
        }

        // Load digital objects
        $digitalObjects = [];
        try {
            $digitalObjects = DB::table('digital_object')
                ->where('object_id', $objectId)
                ->select('id', 'name', 'path', 'mime_type', 'usage_id')
                ->get()
                ->toArray();
        } catch (\Throwable $e) {
            // digital objects may not load
        }

        // Load parent (for hierarchical entities)
        $parent = null;
        if (isset($mainData['parent_id']) && $mainData['parent_id']) {
            try {
                $parentSlug = DB::table('slug')
                    ->where('object_id', $mainData['parent_id'])
                    ->value('slug');
                if ($parentSlug) {
                    $parentTitle = null;
                    if ($config['i18n']) {
                        $parentTitle = DB::table($config['i18n'])
                            ->where('id', $mainData['parent_id'])
                            ->where('culture', $culture)
                            ->value($config['title_col'] ?? 'title');
                    }
                    $parent = [
                        'slug' => $parentSlug,
                        'title' => $parentTitle ?: $parentSlug,
                    ];
                }
            } catch (\Throwable $e) {
                // parent lookup may fail
            }
        }

        // Load children count (for hierarchical entities)
        $childCount = 0;
        if (isset($mainData['lft'], $mainData['rgt'])) {
            $childCount = (int) (($mainData['rgt'] - $mainData['lft'] - 1) / 2);
        }

        // Load repository name (for IO)
        $repositoryName = null;
        if (isset($mainData['repository_id']) && $mainData['repository_id']) {
            try {
                $repositoryName = DB::table('actor_i18n')
                    ->where('id', $mainData['repository_id'])
                    ->where('culture', $culture)
                    ->value('authorized_form_of_name');
            } catch (\Throwable $e) {
                // skip
            }
        }

        // Resolve term names for *_id columns
        $termNames = [];
        foreach ($mainData as $col => $val) {
            if ($val && str_ends_with($col, '_id') && is_numeric($val) && !in_array($col, ['id', 'parent_id', 'repository_id', 'taxonomy_id'])) {
                try {
                    $termName = DB::table('term_i18n')
                        ->where('id', $val)
                        ->where('culture', $culture)
                        ->value('name');
                    if ($termName) {
                        $termNames[$col] = $termName;
                    }
                } catch (\Throwable $e) {
                    // skip
                }
            }
        }

        // Build the content
        $content = $this->renderEntityView([
            'title' => $title,
            'entityType' => $config['label'],
            'slug' => $slug,
            'className' => $className,
            'mainData' => $mainData,
            'i18nData' => $i18nData,
            'notes' => $notes,
            'events' => $events,
            'digitalObjects' => $digitalObjects,
            'parent' => $parent,
            'childCount' => $childCount,
            'repositoryName' => $repositoryName,
            'termNames' => $termNames,
            'objectId' => $objectId,
            'createdAt' => $slugRow->created_at ?? null,
            'updatedAt' => $slugRow->updated_at ?? null,
        ]);

        return new Response($this->wrap($content, $title . ' - ' . $config['label']), 200, [
            'Content-Type' => 'text/html',
        ]);
    }

    /**
     * Render the home page (static page with slug='home').
     */
    public function home(Request $request): Response
    {
        $culture = $this->getCulture();

        try {
            $homePage = DB::table('static_page')
                ->join('slug', 'static_page.id', '=', 'slug.object_id')
                ->join('static_page_i18n', 'static_page.id', '=', 'static_page_i18n.id')
                ->where('slug.slug', 'home')
                ->where('static_page_i18n.culture', $culture)
                ->select('static_page_i18n.title', 'static_page_i18n.content')
                ->first();
        } catch (\Throwable $e) {
            $homePage = null;
        }

        if (!$homePage) {
            // Fallback — render a simple welcome page
            $siteTitle = ConfigService::get('siteTitle', ConfigService::get('app_siteTitle', 'AtoM'));
            $content = '<div id="content"><div class="container py-5">'
                . '<h1>' . htmlspecialchars($siteTitle) . '</h1>'
                . '<p class="lead">Welcome to the archival catalogue.</p>'
                . '<div class="row mt-4">'
                . '<div class="col-md-4 mb-3"><a href="/informationobject/browse" class="btn btn-outline-primary btn-lg w-100">'
                . '<i class="fas fa-archive me-2"></i>Browse Descriptions</a></div>'
                . '<div class="col-md-4 mb-3"><a href="/actor/browse" class="btn btn-outline-primary btn-lg w-100">'
                . '<i class="fas fa-users me-2"></i>Browse Authorities</a></div>'
                . '<div class="col-md-4 mb-3"><a href="/repository/browse" class="btn btn-outline-primary btn-lg w-100">'
                . '<i class="fas fa-university me-2"></i>Browse Institutions</a></div>'
                . '</div></div></div>';

            return new Response($this->wrap($content, $siteTitle), 200, [
                'Content-Type' => 'text/html',
            ]);
        }

        $content = '<div id="content"><div class="container py-4">'
            . '<h1>' . htmlspecialchars($homePage->title ?? '') . '</h1>'
            . '<div class="home-content">' . ($homePage->content ?? '') . '</div>'
            . '</div></div>';

        return new Response($this->wrap($content, $homePage->title ?? 'Home'), 200, [
            'Content-Type' => 'text/html',
        ]);
    }

    /**
     * Render the entity detail view as HTML.
     */
    private function renderEntityView(array $data): string
    {
        $html = '<div id="content"><div class="container py-4">';

        // Breadcrumb
        $html .= '<nav aria-label="breadcrumb"><ol class="breadcrumb">';
        $html .= '<li class="breadcrumb-item"><a href="/">Home</a></li>';
        if ($data['parent']) {
            $html .= '<li class="breadcrumb-item"><a href="/' . htmlspecialchars($data['parent']['slug']) . '">'
                . htmlspecialchars($data['parent']['title']) . '</a></li>';
        }
        $html .= '<li class="breadcrumb-item active">' . htmlspecialchars($data['title']) . '</li>';
        $html .= '</ol></nav>';

        // Entity type badge + title
        $html .= '<div class="d-flex align-items-center mb-3">';
        $html .= '<span class="badge bg-secondary me-2">' . htmlspecialchars($data['entityType']) . '</span>';
        if ($data['repositoryName']) {
            $html .= '<span class="badge bg-info me-2">' . htmlspecialchars($data['repositoryName']) . '</span>';
        }
        $html .= '</div>';
        $html .= '<h1 class="h2 mb-4">' . htmlspecialchars($data['title']) . '</h1>';

        // Digital object thumbnails
        if (!empty($data['digitalObjects'])) {
            $html .= '<div class="mb-4">';
            foreach ($data['digitalObjects'] as $do) {
                $path = $do->path ?? '';
                $name = $do->name ?? 'Digital object';
                $mime = $do->mime_type ?? '';
                if ($path && str_starts_with($mime, 'image/')) {
                    $html .= '<div class="mb-2"><img src="/uploads/' . htmlspecialchars($path . $name)
                        . '" alt="' . htmlspecialchars($name) . '" class="img-fluid rounded" style="max-height:400px"></div>';
                } elseif ($path) {
                    $html .= '<div class="mb-2"><a href="/uploads/' . htmlspecialchars($path . $name)
                        . '" class="btn btn-sm btn-outline-secondary"><i class="fas fa-file me-1"></i>'
                        . htmlspecialchars($name) . '</a></div>';
                }
            }
            $html .= '</div>';
        }

        // i18n fields
        if (!empty($data['i18nData'])) {
            $html .= '<div class="card mb-4"><div class="card-header"><h2 class="h5 mb-0">Description</h2></div>';
            $html .= '<div class="card-body">';
            foreach ($data['i18nData'] as $col => $val) {
                if (in_array($col, ['id', 'culture']) || null === $val || '' === $val) {
                    continue;
                }
                $label = $this->columnLabel($col);
                $html .= '<div class="row mb-2"><div class="col-md-3 text-muted">'
                    . htmlspecialchars($label) . '</div><div class="col-md-9">'
                    . nl2br(htmlspecialchars((string) $val)) . '</div></div>';
            }
            $html .= '</div></div>';
        }

        // Main table fields (non-hidden, non-i18n)
        $extraFields = [];
        foreach ($data['mainData'] as $col => $val) {
            if (in_array($col, self::HIDDEN_COLUMNS) || null === $val || '' === $val) {
                continue;
            }
            if (str_ends_with($col, '_id') && isset($data['termNames'][$col])) {
                $extraFields[$col] = $data['termNames'][$col];
            } elseif (!str_ends_with($col, '_id')) {
                $extraFields[$col] = $val;
            }
        }

        if (!empty($extraFields)) {
            $html .= '<div class="card mb-4"><div class="card-header"><h2 class="h5 mb-0">Details</h2></div>';
            $html .= '<div class="card-body">';
            foreach ($extraFields as $col => $val) {
                $label = $this->columnLabel(str_ends_with($col, '_id') ? substr($col, 0, -3) : $col);
                $html .= '<div class="row mb-2"><div class="col-md-3 text-muted">'
                    . htmlspecialchars($label) . '</div><div class="col-md-9">'
                    . nl2br(htmlspecialchars((string) $val)) . '</div></div>';
            }
            $html .= '</div></div>';
        }

        // Events (dates, creators)
        if (!empty($data['events'])) {
            $html .= '<div class="card mb-4"><div class="card-header"><h2 class="h5 mb-0">Events / Dates</h2></div>';
            $html .= '<div class="card-body">';
            foreach ($data['events'] as $event) {
                $date = $event->date ?? ($event->start_date ?? '');
                if ($event->end_date && $event->end_date !== ($event->start_date ?? '')) {
                    $date .= ' – ' . $event->end_date;
                }
                $name = $event->event_name ?? '';
                $html .= '<div class="row mb-2">';
                if ($name) {
                    $html .= '<div class="col-md-3 text-muted">' . htmlspecialchars($name) . '</div>';
                    $html .= '<div class="col-md-9">' . htmlspecialchars($date) . '</div>';
                } else {
                    $html .= '<div class="col-12">' . htmlspecialchars($date) . '</div>';
                }
                $html .= '</div>';
            }
            $html .= '</div></div>';
        }

        // Notes
        if (!empty($data['notes'])) {
            $html .= '<div class="card mb-4"><div class="card-header"><h2 class="h5 mb-0">Notes</h2></div>';
            $html .= '<div class="card-body">';
            foreach ($data['notes'] as $note) {
                $html .= '<div class="mb-2">' . nl2br(htmlspecialchars($note->content ?? '')) . '</div>';
            }
            $html .= '</div></div>';
        }

        // Children count
        if ($data['childCount'] > 0) {
            $html .= '<div class="alert alert-info">'
                . '<i class="fas fa-sitemap me-2"></i>'
                . 'This record has <strong>' . $data['childCount'] . '</strong> child record(s).'
                . '</div>';
        }

        // Metadata footer
        $html .= '<div class="text-muted small mt-4 border-top pt-3">';
        $html .= '<span class="me-3">Slug: <code>' . htmlspecialchars($data['slug']) . '</code></span>';
        $html .= '<span class="me-3">ID: ' . $data['objectId'] . '</span>';
        if ($data['createdAt']) {
            $html .= '<span class="me-3">Created: ' . htmlspecialchars($data['createdAt']) . '</span>';
        }
        if ($data['updatedAt']) {
            $html .= '<span>Updated: ' . htmlspecialchars($data['updatedAt']) . '</span>';
        }
        $html .= '</div>';

        $html .= '</div></div>';

        return $html;
    }

    /**
     * Convert a database column name to a human-readable label.
     */
    private function columnLabel(string $column): string
    {
        // Remove common prefixes
        $label = preg_replace('/^(authorized_form_of_|other_form_of_|parallel_form_of_)/', '', $column);
        // Convert snake_case to Title Case
        $label = str_replace('_', ' ', $label);

        return ucfirst($label);
    }

    /**
     * Wrap content in the standalone layout.
     */
    private function wrap(string $content, string $pageTitle = ''): string
    {
        require_once dirname(__DIR__, 2) . '/Views/blade_shims.php';

        try {
            $renderer = BladeRenderer::getInstance();

            return $renderer->render('layouts.heratio', [
                'sf_user' => SfContextAdapter::getInstance()->getUser(),
                'sf_content' => $content,
                'siteTitle' => $pageTitle
                    ?: ConfigService::get('siteTitle', ConfigService::get('app_siteTitle', 'AtoM')),
                'culture' => $this->getCulture(),
            ]);
        } catch (\Throwable $e) {
            error_log('[heratio] StandaloneViewer layout failed: ' . $e->getMessage());

            $siteTitle = ConfigService::get('siteTitle', 'AtoM');

            return '<!DOCTYPE html><html><head><meta charset="utf-8">'
                . '<title>' . htmlspecialchars($pageTitle ?: $siteTitle) . '</title>'
                . '<link rel="stylesheet" href="/dist/css/app.css">'
                . '</head><body>' . $content . '</body></html>';
        }
    }

    /**
     * Render a simple error message block.
     */
    private function errorHtml(string $message): string
    {
        return '<div id="content"><div class="container py-5 text-center">'
            . '<h1 class="h2 mb-3"><i class="fas fa-exclamation-triangle me-2"></i>Error</h1>'
            . '<p>' . $message . '</p>'
            . '<a href="/" class="btn btn-primary">Go to homepage</a>'
            . '</div></div>';
    }

    /**
     * Get the current user's culture/language.
     */
    private function getCulture(): string
    {
        try {
            return SfContextAdapter::getInstance()->getUser()->getCulture();
        } catch (\Throwable $e) {
            return 'en';
        }
    }
}
