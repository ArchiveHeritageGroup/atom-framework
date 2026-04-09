/**
 * Donor TomSelect remote-load picker.
 *
 * Replaces the legacy AtoM `form-autocomplete` widget on the accession edit
 * "Donor/Transferring body area" modal with a TomSelect-backed searchable
 * dropdown that loads results from /donor/autocomplete.
 *
 * Bridges three things the locked base AtoM modal.js does NOT understand:
 *
 *   1. submitModal — handled natively. modal.js's SELECT branch reads
 *      $input.val() (URL) and option:selected.text() (display name), which
 *      TomSelect maintains on the underlying <select>.
 *
 *   2. editRow pre-population — modal.js sets the field via
 *      `$input.val({uri, text})` for non-form-autocomplete selects, which is
 *      a no-op on a real <select>. We capture-phase intercept clicks on
 *      `.edit-row` to remember which <tr> was opened, then on
 *      `shown.bs.modal` we read `data-donor-uri` / `data-donor-name` from
 *      that row and set the TomSelect value.
 *
 *   3. Primary contact auto-fetch — modal.js's listener targets
 *      `input[name="relatedDonor[resource]"]` (an <input>, not a <select>),
 *      so it never fires for our widget. We listen on the underlying select
 *      ourselves and POST to /donor/<slug>/primaryContact, then mirror the
 *      response into the modal's contact-info inputs.
 */
(function () {
  'use strict';

  function parseAutocompleteHtml(html) {
    var doc = new DOMParser().parseFromString(html, 'text/html');
    var rows = [];
    doc.querySelectorAll('tbody tr td a').forEach(function (a) {
      var href = a.getAttribute('href');
      if (!href) return;
      rows.push({ value: href, text: (a.textContent || '').trim() });
    });
    return rows;
  }

  function initSelect(el) {
    if (el.tomselect) return;
    if (typeof TomSelect === 'undefined') return;

    var url = el.getAttribute('data-remote-url');
    if (!url) return;

    var ts = new TomSelect(el, {
      valueField: 'value',
      labelField: 'text',
      searchField: 'text',
      maxOptions: 50,
      preload: false,
      create: false,
      placeholder: el.getAttribute('data-placeholder') || '',
      load: function (query, callback) {
        if (!query.length) {
          callback();
          return;
        }
        var sep = url.indexOf('?') > -1 ? '&' : '?';
        fetch(url + sep + 'query=' + encodeURIComponent(query) + '&limit=20', {
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          credentials: 'same-origin'
        })
          .then(function (r) { return r.text(); })
          .then(function (html) { callback(parseAutocompleteHtml(html)); })
          .catch(function () { callback(); });
      },
      render: {
        no_results: function (data, escape) {
          return '<div class="no-results p-2 text-muted">No donors match "' + escape(data.input) + '"</div>';
        }
      }
    });

    // (3) Primary contact auto-fetch bridge
    el.addEventListener('change', function () {
      var uri = el.value;
      if (!uri) return;
      var modal = el.closest('.modal');
      if (!modal) return;

      var contactUrl = uri + '/donor/primaryContact';
      fetch(contactUrl, {
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        credentials: 'same-origin'
      })
        .then(function (r) { return r.ok ? r.json() : null; })
        .then(function (data) {
          if (!data) return;
          // Mirror modal.js updateInputs (string-value branch only) for contact fields
          var keys = [
            'city', 'contactPerson', 'countryCode', 'email', 'postalCode',
            'region', 'streetAddress', 'telephone', 'contactType', 'fax',
            'website', 'latitude', 'longitude', 'note'
          ];
          keys.forEach(function (key) {
            if (data[key] === undefined) return;
            var input = modal.querySelector('#relatedDonor_' + key);
            if (!input) return;
            if (input.type === 'checkbox') {
              input.checked = !!data[key];
            } else {
              input.value = data[key] == null ? '' : data[key];
            }
          });
        })
        .catch(function () { /* silent */ });
    });
  }

  function initAll() {
    document.querySelectorAll('select.tom-remote-donor').forEach(initSelect);
  }

  // (2) editRow bridge
  var pendingEditRow = null;
  document.addEventListener('click', function (e) {
    var btn = e.target.closest && e.target.closest('.edit-row');
    if (!btn) return;
    pendingEditRow = btn.closest('tr');
  }, true); // capture phase: fires before jQuery delegated handlers

  document.addEventListener('shown.bs.modal', function (e) {
    var modal = e.target;
    if (!modal || !modal.querySelector) return;

    modal.querySelectorAll('select.tom-remote-donor').forEach(function (el) {
      if (!el.tomselect) initSelect(el);
      if (!el.tomselect) return;

      // Edit case: pull URI/name from the row that was clicked
      if (pendingEditRow) {
        var uri = pendingEditRow.getAttribute('data-donor-uri');
        var name = pendingEditRow.getAttribute('data-donor-name');
        if (uri) {
          el.tomselect.addOption({ value: uri, text: name || uri });
          el.tomselect.setValue(uri, true); // silent — don't trigger contact refetch
        }
      }
    });

    pendingEditRow = null;
  }, true);

  document.addEventListener('hidden.bs.modal', function (e) {
    var modal = e.target;
    if (!modal || !modal.querySelector) return;
    modal.querySelectorAll('select.tom-remote-donor').forEach(function (el) {
      if (el.tomselect) el.tomselect.clear(true);
    });
  }, true);

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAll);
  } else {
    initAll();
  }
})();
