/**
 * AtoM Heratio Web Wizard - JavaScript (no external deps)
 */

/**
 * Call the wizard API
 */
function wizardAPI(action, params, callback, method) {
    method = method || 'POST';
    var url = 'api.php?token=' + encodeURIComponent(WIZARD_TOKEN);

    if (method === 'GET') {
        url += '&action=' + encodeURIComponent(action);
        for (var key in params) {
            url += '&' + encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
        }
    }

    var xhr = new XMLHttpRequest();
    xhr.open(method, url, true);
    xhr.setRequestHeader('X-Wizard-Token', WIZARD_TOKEN);

    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            try {
                var data = JSON.parse(xhr.responseText);
                callback(data);
            } catch (e) {
                callback({ error: 'Invalid response', raw: xhr.responseText });
            }
        }
    };

    if (method === 'POST') {
        var formData = new FormData();
        formData.append('action', action);
        formData.append('token', WIZARD_TOKEN);
        for (var k in params) {
            formData.append(k, typeof params[k] === 'object' ? JSON.stringify(params[k]) : params[k]);
        }
        xhr.send(formData);
    } else {
        xhr.send();
    }
}

/**
 * Refresh system health checks (Step 1)
 */
function refreshChecks() {
    wizardAPI('system-check', {}, function(data) {
        if (data.checks) {
            location.reload();
        }
    }, 'GET');
}

/**
 * Save plugin selections (Step 2)
 */
function savePlugins() {
    var checkboxes = document.querySelectorAll('#plugin-catalog input[type="checkbox"]:not([disabled])');
    var enable = [];
    var disable = [];

    checkboxes.forEach(function(cb) {
        if (cb.checked) {
            enable.push(cb.value);
        } else {
            disable.push(cb.value);
        }
    });

    wizardAPI('set-plugins', {
        enable: JSON.stringify(enable),
        disable: JSON.stringify(disable)
    }, function(data) {
        if (data.status === 'ok') {
            alert('Plugins updated: ' + (data.results.enabled || []).length + ' enabled, ' + (data.results.disabled || []).length + ' disabled');
        } else {
            alert('Error: ' + (data.error || 'Unknown'));
        }
    });
}

/**
 * Save GLAM settings (Step 3)
 */
function saveGlamSettings(e) {
    e.preventDefault();
    var form = document.getElementById('glam-form');
    var settings = {};
    var formData = new FormData(form);
    formData.forEach(function(val, key) { settings[key] = val; });

    wizardAPI('save-settings', {
        settings: JSON.stringify(settings),
        group: 'glam'
    }, function(data) {
        alert(data.status === 'ok' ? 'GLAM settings saved (' + data.saved + ' settings)' : 'Error saving settings');
    });
}

/**
 * Save AI settings (Step 4)
 */
function saveAISettings(e) {
    e.preventDefault();
    var form = document.getElementById('ai-form');
    var settings = {};
    var formData = new FormData(form);
    formData.forEach(function(val, key) { settings[key] = val; });

    // Handle unchecked checkboxes
    form.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
        if (!cb.checked) settings[cb.name] = '0';
    });

    wizardAPI('save-settings', {
        settings: JSON.stringify(settings),
        group: 'ai'
    }, function(data) {
        alert(data.status === 'ok' ? 'AI settings saved (' + data.saved + ' settings)' : 'Error saving settings');
    });
}

/**
 * Save compliance settings (Step 5)
 */
function saveComplianceSettings(e) {
    e.preventDefault();
    var form = document.getElementById('compliance-form');
    var settings = {};
    var formData = new FormData(form);
    formData.forEach(function(val, key) { settings[key] = val; });

    form.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
        if (!cb.checked) settings[cb.name] = '0';
    });

    wizardAPI('save-settings', {
        settings: JSON.stringify(settings),
        group: 'compliance'
    }, function(data) {
        alert(data.status === 'ok' ? 'Compliance settings saved (' + data.saved + ' settings)' : 'Error saving settings');
    });
}

/**
 * Save preservation settings (Step 6)
 */
function savePreservationSettings(e) {
    e.preventDefault();
    var form = document.getElementById('preservation-form');
    var settings = {};
    var formData = new FormData(form);
    formData.forEach(function(val, key) { settings[key] = val; });

    form.querySelectorAll('input[type="checkbox"]').forEach(function(cb) {
        if (!cb.checked) settings[cb.name] = '0';
    });

    wizardAPI('save-settings', {
        settings: JSON.stringify(settings),
        group: 'preservation'
    }, function(data) {
        alert(data.status === 'ok' ? 'Preservation settings saved (' + data.saved + ' settings)' : 'Error saving settings');
    });
}
