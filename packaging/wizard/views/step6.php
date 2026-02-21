<h2>Step 6: Digital Preservation</h2>
<p>Configure preservation, backup, and format management settings.</p>

<form id="preservation-form" onsubmit="savePreservationSettings(event)">
    <h3>Fixity Checking</h3>
    <div class="form-group">
        <label class="toggle">
            <input type="checkbox" name="fixity_enabled" value="1" checked>
            <span>Enable fixity (checksum) verification</span>
        </label>
        <div class="form-row">
            <label>Algorithm</label>
            <select name="fixity_algorithm" class="form-control form-control-sm">
                <option value="sha256">SHA-256 (recommended)</option>
                <option value="sha512">SHA-512</option>
                <option value="md5">MD5 (legacy)</option>
            </select>
            <label>Schedule</label>
            <select name="fixity_schedule" class="form-control form-control-sm">
                <option value="weekly">Weekly</option>
                <option value="monthly">Monthly</option>
                <option value="daily">Daily</option>
            </select>
        </div>
    </div>

    <h3>Virus Scanning</h3>
    <div class="form-group">
        <label class="toggle">
            <input type="checkbox" name="virus_scan_enabled" value="1">
            <span>Enable virus scanning on upload (requires ClamAV)</span>
        </label>
    </div>

    <h3>Format Identification</h3>
    <div class="form-group">
        <label class="toggle">
            <input type="checkbox" name="format_id_enabled" value="1">
            <span>Enable PRONOM format identification (requires Siegfried)</span>
        </label>
    </div>

    <h3>OAIS Package Generation</h3>
    <div class="form-group">
        <p class="form-help">Default output packages for batch ingest.</p>
        <label class="toggle">
            <input type="checkbox" name="oais_sip" value="1" checked>
            <span>Generate SIP (Submission Information Package)</span>
        </label>
        <label class="toggle">
            <input type="checkbox" name="oais_aip" value="1">
            <span>Generate AIP (Archival Information Package)</span>
        </label>
        <label class="toggle">
            <input type="checkbox" name="oais_dip" value="1" checked>
            <span>Generate DIP (Dissemination Information Package)</span>
        </label>
    </div>

    <h3>Backup</h3>
    <div class="form-group">
        <label class="toggle">
            <input type="checkbox" name="backup_enabled" value="1" checked>
            <span>Enable automated backups</span>
        </label>
        <div class="form-row">
            <label>Retention (days)</label>
            <input type="number" name="backup_retention" value="30" class="form-control form-control-sm" min="1" max="365">
            <label>Schedule</label>
            <select name="backup_schedule" class="form-control form-control-sm">
                <option value="daily">Daily</option>
                <option value="weekly">Weekly</option>
            </select>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Save Preservation Settings</button>
</form>
