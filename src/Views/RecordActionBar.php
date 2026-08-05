<?php

declare(strict_types=1);

namespace AtomFramework\Views;

use Illuminate\Database\Capsule\Manager as DB;

/**
 * A row of per-record action buttons that any plugin can contribute to.
 *
 * Why this exists: the buttons a user expects on an archival description - item
 * feedback, add to favourites, add to cart, read aloud - are currently hardcoded
 * into six templates (ahgThemeB5Plugin's sfIsadPlugin override plus the museum,
 * cco, library, dam and gallery sector templates). Each copy re-tests which
 * plugins are enabled and re-renders the same markup, so a plugin can only show a
 * button by having a theme template already know about it. On a stock AtoM install
 * with none of those templates, no button appears at all.
 *
 * This inverts it. The bar has no owner: whichever contributor filters the response
 * first creates it, the rest append. A plugin contributes its own button and nothing
 * else needs changing - no theme, no shared template, no registration order.
 *
 * Contributors call render() from a response.filter_content listener:
 *
 *     $content = RecordActionBar::render($event, $content, 'ahg-feedback-btn',
 *         fn (string $slug) => '<a href="...">...</a>');
 */
class RecordActionBar
{
    /**
     * A description page is NOT served by the 'informationobject' module - AtoM
     * forwards /{slug} to the module for the record's descriptive standard, so
     * matching only 'informationobject' means a contributor silently never fires.
     */
    private const VIEW_MODULES = [
        'informationobject',
        'sfIsadPlugin',
        'sfRadPlugin',
        'sfDcPlugin',
        'sfModsPlugin',
        'sfDacsPlugin',
    ];

    private const CONTAINER_CLASS = 'ahg-record-actions';

    /**
     * Closing sentinel. Appending before a plain </div> would need the closing tag
     * to be matched by depth, and a button containing a <div> would break that;
     * a comment is unambiguous no matter what the buttons contain.
     */
    private const END_MARKER = '<!--/ahg-record-actions-->';

    /**
     * Add one button to the bar, creating the bar if this is the first contributor.
     *
     * $marker is a class or id unique to the caller's button - it is what stops the
     * same button being added twice when response.filter_content fires more than
     * once for a request, which it does.
     *
     * $factory receives the record slug and returns the button HTML, and is only
     * called once the page has been confirmed as a record view, so a contributor
     * never pays for a query on pages that will not show the bar.
     */
    public static function render(\sfEvent $event, $content, string $marker, callable $factory)
    {
        try {
            $html = (string) $content;

            if (false !== strpos($html, $marker)) {
                return $content;
            }
            if (!self::isRecordView($event)) {
                return $content;
            }

            $slug = self::slug();
            if (null === $slug) {
                return $content;
            }

            // Loaded here rather than by each contributor: url_for() and __() are
            // Symfony helpers, and a response filter runs outside the template
            // context that would normally have loaded them.
            \sfContext::getInstance()->getConfiguration()->loadHelpers(['I18N', 'Url']);

            $button = trim((string) $factory($slug));
            if ('' === $button) {
                return $content;
            }

            $existing = strpos($html, self::END_MARKER);
            if (false !== $existing) {
                return substr_replace($html, $button, $existing, 0);
            }

            return self::openBar($html, $button);
        } catch (\Throwable $e) {
            // A button is an enhancement. It must never take a record page down.
            return $content;
        }
    }

    /**
     * Create the bar directly below the record title.
     *
     * Anchored on the heading rather than the top of the column because the column
     * opens with the viewer on records that have a digital object, and buttons above
     * the title read as page chrome rather than as actions on this record.
     */
    private static function openBar(string $html, string $button): string
    {
        $column = strpos($html, 'id="main-column"');
        if (false === $column) {
            return $html;
        }

        $heading = stripos($html, '</h1>', $column);
        if (false === $heading) {
            return $html;
        }

        $at = $heading + 5;
        $bar = "\n".'<div class="d-flex flex-wrap gap-1 mb-3 align-items-center '.self::CONTAINER_CLASS.'">'
            .$button.self::END_MARKER.'</div>'."\n";

        return substr_replace($html, $bar, $at, 0);
    }

    /**
     * Is this an HTML GET rendering a record, and not an editing screen?
     */
    private static function isRecordView(\sfEvent $event): bool
    {
        $response = $event->getSubject();

        if (!$response instanceof \sfWebResponse) {
            return false;
        }
        if (200 !== $response->getStatusCode()) {
            return false;
        }
        if (false === stripos((string) $response->getContentType(), 'text/html')) {
            return false;
        }

        $context = \sfContext::getInstance();

        if ('GET' !== strtoupper((string) $context->getRequest()->getMethod())) {
            return false;
        }
        if (!in_array($context->getModuleName(), self::VIEW_MODULES, true)) {
            return false;
        }

        // Matched on the action rather than a list of modules, so a new editing
        // action in any module is covered without this needing to know about it.
        $action = strtolower((string) $context->getActionName());
        foreach (['edit', 'add', 'create', 'update', 'new', 'delete'] as $verb) {
            if ($action === $verb || 0 === strpos($action, $verb)) {
                return false;
            }
        }

        return true;
    }

    /**
     * The slug of the description being viewed, or null.
     *
     * Resolved against information_object rather than trusting the request
     * parameter: a static page, term, actor and repository all carry a slug too,
     * and none of them has the actions this bar offers.
     */
    private static function slug(): ?string
    {
        $slug = (string) \sfContext::getInstance()->getRequest()->getParameter('slug', '');

        if ('' === $slug) {
            return null;
        }

        try {
            $exists = DB::table('slug as s')
                ->join('information_object as io', 'io.id', '=', 's.object_id')
                ->where('s.slug', $slug)
                ->exists();
        } catch (\Throwable $e) {
            return null;
        }

        return $exists ? $slug : null;
    }

    /**
     * The id of the description being viewed, for contributors that need it.
     */
    public static function objectId(string $slug): ?int
    {
        try {
            $id = DB::table('slug')->where('slug', $slug)->value('object_id');
        } catch (\Throwable $e) {
            return null;
        }

        return $id ? (int) $id : null;
    }
}
