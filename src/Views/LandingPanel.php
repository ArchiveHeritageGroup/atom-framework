<?php

declare(strict_types=1);

namespace AtomFramework\Views;

/**
 * A sidebar panel on the site landing page that any plugin can add a link to.
 *
 * Why this exists: there is no menu node that renders only on the landing page.
 * staticPagesMenu comes closest, but AtoM also renders it in the archival
 * description sidebar, so anything put there follows the visitor onto every
 * record - and it is not even reliable as a landing-page surface, since an
 * install whose landing page comes from a plugin (ahgHeritagePlugin's, for
 * instance) never renders it at all. quickLinks is worse: its component drops any
 * child that fails QubitObject::actionExistsForUrl(), and on the installs checked
 * it renders an empty dropdown.
 *
 * So the menu table cannot express "landing page only", and this does it without
 * modifying an AtoM template. Same shape as RecordActionBar: the panel has no
 * owner, whichever contributor filters the response first creates it and the rest
 * append.
 */
class LandingPanel
{
    private const CONTAINER_CLASS = 'ahg-landing-links';

    /** Appending before a plain </section> would need the close matched by depth. */
    private const END_MARKER = '<!--/ahg-landing-links-->';

    /**
     * Add one link, creating the panel if this is the first contributor.
     *
     * $marker is a class or id unique to the caller's link - it is what stops the
     * same link being added twice when response.filter_content fires more than once
     * for a request, which it does.
     */
    public static function render(\sfEvent $event, $content, string $marker, callable $factory)
    {
        try {
            $html = (string) $content;

            if (false !== strpos($html, $marker)) {
                return $content;
            }
            if (!self::isLandingPage($event)) {
                return $content;
            }

            \sfContext::getInstance()->getConfiguration()->loadHelpers(['I18N', 'Url']);

            $link = trim((string) $factory());
            if ('' === $link) {
                return $content;
            }

            $existing = strpos($html, self::END_MARKER);
            if (false !== $existing) {
                return substr_replace($html, $link, $existing, 0);
            }

            return self::openPanel($html, $link);
        } catch (\Throwable $e) {
            // A convenience link must never take the landing page down.
            return $content;
        }
    }

    /**
     * Create the panel in the landing page's sidebar column.
     *
     * Anchored on the sidebar rather than the main column so the panel sits beside
     * the page's content instead of interrupting it. Falls back to doing nothing
     * when the layout has no sidebar - a landing page that is a single wide column
     * has nowhere this belongs.
     */
    private static function openPanel(string $html, string $link): string
    {
        $anchor = strpos($html, 'id="sidebar"');

        if (false === $anchor) {
            return $html;
        }

        $open = strpos($html, '>', $anchor);
        if (false === $open) {
            return $html;
        }

        $panel = "\n".'<section class="card mb-3 '.self::CONTAINER_CLASS.'">'
            .'<div class="list-group list-group-flush">'
            .$link.self::END_MARKER
            .'</div></section>'."\n";

        return substr_replace($html, $panel, $open + 1, 0);
    }

    /**
     * Is this an HTML GET of the site landing page?
     *
     * Matched on the request path rather than on a module and action, because the
     * landing page is not the same module on every install: stock AtoM serves
     * staticpage/home, and a site with a landing-page plugin serves its own.
     */
    private static function isLandingPage(\sfEvent $event): bool
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

        $request = \sfContext::getInstance()->getRequest();

        if ('GET' !== strtoupper((string) $request->getMethod())) {
            return false;
        }

        $path = parse_url((string) $request->getPathInfo(), PHP_URL_PATH);

        return in_array(rtrim((string) $path, '/'), ['', '/index.php'], true);
    }

    /**
     * Markup for one entry, matching AtoM's own sidebar list styling.
     */
    public static function link(string $url, string $label, string $icon, string $marker): string
    {
        return '<a class="list-group-item list-group-item-action '.$marker.'" href="'.htmlspecialchars($url, ENT_QUOTES).'">'
            .'<i class="'.$icon.' me-2" aria-hidden="true"></i>'
            .htmlspecialchars($label)
            .'</a>';
    }
}
