<?php

/**
 * QubitOai Compatibility Layer.
 *
 * @deprecated Use AtomExtensions\Services\OaiService directly
 */

use AtomExtensions\Services\OaiService;

class QubitOai
{
    public static function getRepositoryIdentifier(): string
    {
        return OaiService::getRepositoryIdentifier();
    }

    public static function getOaiSampleIdentifier(): string
    {
        return OaiService::getOaiSampleIdentifier();
    }
}
