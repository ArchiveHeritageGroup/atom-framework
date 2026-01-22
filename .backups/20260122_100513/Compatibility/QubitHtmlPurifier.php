<?php

/**
 * QubitHtmlPurifier Compatibility Layer.
 *
 * @deprecated Use AtomExtensions\Services\HtmlPurifierService directly
 */

use AtomExtensions\Services\HtmlPurifierService;

class QubitHtmlPurifier
{
    public static function getInstance(): HtmlPurifierService
    {
        return HtmlPurifierService::getInstance();
    }
}
