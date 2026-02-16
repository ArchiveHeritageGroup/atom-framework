<?php

/**
 * Minimal sfWebRequest shim for standalone Heratio mode.
 *
 * Provides a base class so that SfWebRequestAdapter can extend it,
 * making instanceof/type-hint checks pass for plugin action methods
 * that declare `sfWebRequest $request` parameters.
 *
 * The real sfWebRequest lives in vendor/symfony/ and requires
 * sfEventDispatcher — which is not available in standalone mode.
 */
class sfWebRequest
{
    // Intentionally minimal — SfWebRequestAdapter overrides everything.
}
