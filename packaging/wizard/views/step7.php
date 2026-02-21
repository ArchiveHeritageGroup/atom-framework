<h2>Step 7: Review & Apply</h2>
<p>Review your configuration and apply changes.</p>

<div id="review-summary">
    <h3>Configuration Summary</h3>
    <p>Loading...</p>
</div>

<div class="action-buttons">
    <button class="btn btn-primary btn-lg" onclick="applyConfiguration()">Apply Configuration</button>
    <button class="btn btn-secondary" onclick="clearCaches()">Clear Caches Only</button>
    <button class="btn btn-secondary" onclick="restartServices()">Restart Services Only</button>
</div>

<div id="apply-status" style="display:none;">
    <div class="status-card status-ok">
        <h3>Configuration Applied</h3>
        <p id="apply-message"></p>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadReviewSummary();
});

function loadReviewSummary() {
    var sections = ['general', 'glam', 'ai', 'compliance', 'preservation'];
    var html = '';

    var loaded = 0;
    sections.forEach(function(group) {
        wizardAPI('get-settings', {group: group}, function(data) {
            loaded++;
            if (data.settings && Object.keys(data.settings).length > 0) {
                html += '<div class="review-section"><h4>' + group.charAt(0).toUpperCase() + group.slice(1) + '</h4><table>';
                for (var key in data.settings) {
                    html += '<tr><td>' + key + '</td><td>' + data.settings[key] + '</td></tr>';
                }
                html += '</table></div>';
            }

            if (loaded === sections.length) {
                if (html === '') {
                    html = '<p>No settings configured yet. Visit the previous steps to configure your installation.</p>';
                }
                document.getElementById('review-summary').innerHTML = '<h3>Configuration Summary</h3>' + html;
            }
        });
    });
}

function applyConfiguration() {
    var btn = event.target;
    btn.disabled = true;
    btn.textContent = 'Applying...';

    wizardAPI('apply', {}, function(data) {
        btn.disabled = false;
        btn.textContent = 'Apply Configuration';

        var statusDiv = document.getElementById('apply-status');
        var msgP = document.getElementById('apply-message');

        if (data.status === 'ok') {
            statusDiv.style.display = 'block';
            msgP.textContent = data.message || 'Configuration applied successfully. Services restarted.';
        } else {
            statusDiv.style.display = 'block';
            statusDiv.querySelector('.status-card').className = 'status-card status-error';
            msgP.textContent = 'Error: ' + (data.error || 'Unknown error');
        }
    });
}

function clearCaches() {
    wizardAPI('clear-cache', {}, function(data) {
        alert(data.status === 'ok' ? 'Caches cleared' : 'Error clearing caches');
    });
}

function restartServices() {
    wizardAPI('restart-services', {}, function(data) {
        alert(data.status === 'ok' ? 'Services restarted' : 'Error restarting services');
    });
}
</script>
