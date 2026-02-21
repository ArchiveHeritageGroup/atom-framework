<h2>Step 2: Plugin Selection</h2>
<p>Enable or disable plugins by category. Core plugins (locked) cannot be modified.</p>

<div id="plugin-catalog">
    <p>Loading plugin catalog...</p>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadPlugins();
});

function loadPlugins() {
    wizardAPI('get-plugins', {}, function(data) {
        if (!data.catalog) return;
        var html = '';
        var categories = {
            'core': 'Core (Required)',
            'glam': 'GLAM Sectors',
            'browse': 'Browse & Manage',
            'crud': 'Descriptive Standards',
            'ai': 'AI & Automation',
            'ingest': 'Data Ingest & Import/Export',
            'compliance': 'Compliance & Regulatory',
            'preservation': 'Digital Preservation',
            'rights': 'Rights Management',
            'research': 'Research & Public Access',
            'collection': 'Collection Management',
            'exhibition': 'Exhibition & Engagement',
            'integration': 'Advanced Integration',
            'reporting': 'Reporting & Admin',
            'general': 'Other'
        };

        for (var cat in data.catalog) {
            var label = categories[cat] || cat;
            html += '<div class="plugin-category">';
            html += '<h3>' + label + '</h3>';
            html += '<div class="plugin-list">';
            data.catalog[cat].forEach(function(p) {
                var disabled = p.locked ? 'disabled' : '';
                var checked = p.enabled ? 'checked' : '';
                html += '<label class="plugin-item ' + (p.locked ? 'locked' : '') + '">';
                html += '<input type="checkbox" name="plugin_' + p.name + '" value="' + p.name + '" ' + checked + ' ' + disabled + '>';
                html += '<span class="plugin-name">' + p.name + '</span>';
                if (p.description) html += '<span class="plugin-desc">' + p.description + '</span>';
                if (p.locked) html += '<span class="badge-locked">Locked</span>';
                html += '</label>';
            });
            html += '</div></div>';
        }

        document.getElementById('plugin-catalog').innerHTML = html;
    });
}
</script>

<button class="btn btn-primary" onclick="savePlugins()">Save Plugin Selection</button>
