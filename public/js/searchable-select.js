/**
 * Searchable Record Select - Tom Select Initialization
 * Format: Title (identifier) - Level
 */

(function() {
    'use strict';

    /**
     * Extract title from "Title (identifier)" format
     */
    function extractTitle(text, identifier) {
        if (!text) return '';
        const suffix = ' (' + identifier + ')';
        if (text.endsWith(suffix)) {
            return text.slice(0, -suffix.length);
        }
        if (text.endsWith(' (No identifier)')) {
            return text.slice(0, -16);
        }
        return text;
    }

    /**
     * Initialize a single select element
     */
    function initSearchableSelect(el) {
        if (el.tomselect) return; // Already initialized

        new TomSelect(el, {
            maxOptions: null,
            searchField: ['text'],
            sortField: {
                field: 'text',
                direction: 'asc'
            },
            render: {
                option: function(data, escape) {
                    const identifier = data.identifier || 'No ID';
                    const level = data.level || '';
                    const title = extractTitle(data.text, identifier);

                    let html = '<div class="record-option">';
                    html += '<span class="record-title">' + escape(title) + '</span>';
                    html += '<span class="record-identifier">(' + escape(identifier) + ')</span>';
                    if (level) {
                        html += '<span class="record-level">' + escape(level) + '</span>';
                    }
                    html += '</div>';
                    return html;
                },
                item: function(data, escape) {
                    const identifier = data.identifier || 'No ID';
                    const title = extractTitle(data.text, identifier);
                    return '<div>' + escape(title) + ' <span class="text-muted">(' + escape(identifier) + ')</span></div>';
                },
                no_results: function(data, escape) {
                    return '<div class="no-results">No records found for "' + escape(data.input) + '"</div>';
                }
            },
            onInitialize: function() {
                const select = this.input;
                Array.from(select.options).forEach(function(opt) {
                    if (opt.value && this.options[opt.value]) {
                        this.options[opt.value].identifier = opt.dataset.identifier || '';
                        this.options[opt.value].level = opt.dataset.level || '';
                    }
                }, this);
            }
        });
    }

    /**
     * Initialize all searchable selects on page
     */
    function initAll() {
        document.querySelectorAll('.searchable-record-select').forEach(initSearchableSelect);
    }

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }

    // Expose for dynamic initialization
    window.initSearchableSelect = initSearchableSelect;
    window.initAllSearchableSelects = initAll;
})();
