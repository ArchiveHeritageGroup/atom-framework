<h2>Step 5: Compliance Modules</h2>
<p>Enable regulatory compliance modules for your jurisdiction.</p>

<form id="compliance-form" onsubmit="saveComplianceSettings(event)">
    <h3>Data Protection</h3>
    <div class="form-group">
        <label>Primary Jurisdiction</label>
        <select name="privacy_jurisdiction" class="form-control">
            <option value="">None</option>
            <option value="popia">POPIA - South Africa</option>
            <option value="gdpr">GDPR - European Union</option>
            <option value="uk_gdpr">UK GDPR - United Kingdom</option>
            <option value="ccpa">CCPA - California, USA</option>
            <option value="pipeda">PIPEDA - Canada</option>
            <option value="ndpa">NDPA - Nigeria</option>
            <option value="cdpa">CDPA - Zimbabwe</option>
        </select>
    </div>

    <div class="form-group">
        <label class="toggle">
            <input type="checkbox" name="pii_scanning" value="1">
            <span>Enable automatic PII scanning on ingest</span>
        </label>
    </div>

    <h3>National Archives Compliance</h3>
    <div class="form-group">
        <label class="toggle">
            <input type="checkbox" name="naz_enabled" value="1">
            <span>NAZ - National Archives of Zimbabwe (25-year rule)</span>
        </label>
    </div>
    <div class="form-group">
        <label class="toggle">
            <input type="checkbox" name="nmmz_enabled" value="1">
            <span>NMMZ - National Museums and Monuments of Zimbabwe</span>
        </label>
    </div>

    <h3>Security Classification</h3>
    <div class="form-group">
        <label>Classification Scheme</label>
        <select name="security_scheme" class="form-control">
            <option value="narssa">NARSSA (South Africa)</option>
            <option value="custom">Custom levels</option>
        </select>
    </div>

    <div class="form-group">
        <label class="toggle">
            <input type="checkbox" name="embargo_enabled" value="1">
            <span>Enable embargo management</span>
        </label>
    </div>

    <h3>Audit Trail</h3>
    <div class="form-group">
        <label class="toggle">
            <input type="checkbox" name="audit_trail" value="1" checked>
            <span>Enable audit trail logging (recommended for compliance)</span>
        </label>
    </div>

    <button type="submit" class="btn btn-primary">Save Compliance Settings</button>
</form>
