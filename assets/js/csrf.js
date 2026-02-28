/**
 * CSRF Token Auto-Injection for AtoM Heratio.
 *
 * Reads the CSRF token from <meta name="csrf-token"> and automatically
 * attaches it to all outbound fetch() and jQuery $.ajax() requests.
 *
 * Usage: Include this script in the page layout. The theme should render
 * <?php echo csrf_meta() ?> in the <head> section.
 */
(function () {
    'use strict';

    /**
     * Read the CSRF token from the meta tag.
     */
    function getCsrfToken() {
        var meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : null;
    }

    /**
     * Intercept fetch() to inject the CSRF header on mutating requests.
     */
    if (typeof window.fetch === 'function') {
        var originalFetch = window.fetch;

        window.fetch = function (url, options) {
            options = options || {};
            var method = (options.method || 'GET').toUpperCase();

            if (['POST', 'PUT', 'DELETE', 'PATCH'].indexOf(method) !== -1) {
                var token = getCsrfToken();
                if (token) {
                    if (!options.headers) {
                        options.headers = {};
                    }
                    // Support Headers object or plain object
                    if (options.headers instanceof Headers) {
                        if (!options.headers.has('X-CSRF-TOKEN')) {
                            options.headers.set('X-CSRF-TOKEN', token);
                        }
                    } else {
                        if (!options.headers['X-CSRF-TOKEN']) {
                            options.headers['X-CSRF-TOKEN'] = token;
                        }
                    }
                }
            }

            return originalFetch.call(this, url, options);
        };
    }

    /**
     * Intercept jQuery AJAX to inject the CSRF header.
     */
    if (typeof jQuery !== 'undefined') {
        jQuery(document).ajaxSend(function (event, jqxhr, settings) {
            var method = (settings.type || 'GET').toUpperCase();
            if (['POST', 'PUT', 'DELETE', 'PATCH'].indexOf(method) !== -1) {
                var token = getCsrfToken();
                if (token) {
                    jqxhr.setRequestHeader('X-CSRF-TOKEN', token);
                }
            }
        });
    }

    /**
     * Intercept XMLHttpRequest.send() for non-jQuery XHR.
     */
    if (typeof XMLHttpRequest !== 'undefined') {
        var originalOpen = XMLHttpRequest.prototype.open;
        var originalSend = XMLHttpRequest.prototype.send;

        XMLHttpRequest.prototype.open = function (method) {
            this._csrfMethod = (method || 'GET').toUpperCase();
            return originalOpen.apply(this, arguments);
        };

        XMLHttpRequest.prototype.send = function () {
            if (['POST', 'PUT', 'DELETE', 'PATCH'].indexOf(this._csrfMethod) !== -1) {
                var token = getCsrfToken();
                if (token) {
                    try {
                        this.setRequestHeader('X-CSRF-TOKEN', token);
                    } catch (e) {
                        // Header already set or request not open
                    }
                }
            }
            return originalSend.apply(this, arguments);
        };
    }
})();
