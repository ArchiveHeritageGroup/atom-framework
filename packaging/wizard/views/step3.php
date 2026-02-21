<h2>Step 3: GLAM Sector Configuration</h2>
<p>Configure settings for your institution type. Select your primary sector.</p>

<form id="glam-form" onsubmit="saveGlamSettings(event)">
    <div class="form-group">
        <label>Primary Sector</label>
        <select name="primary_sector" class="form-control">
            <option value="archive">Archive (ISAD(G) / ISAAR)</option>
            <option value="library">Library (MARC / Dublin Core)</option>
            <option value="museum">Museum (CCO / CIDOC-CRM / Spectrum)</option>
            <option value="gallery">Gallery (Exhibition-focused)</option>
            <option value="dam">Digital Asset Management (IPTC)</option>
        </select>
    </div>

    <div class="form-group">
        <label>Default Descriptive Standard</label>
        <select name="default_standard" class="form-control">
            <option value="isad">ISAD(G) - International Standard Archival Description</option>
            <option value="dacs">DACS - Describing Archives: A Content Standard</option>
            <option value="rad">RAD - Rules for Archival Description</option>
            <option value="dc">Dublin Core</option>
            <option value="mods">MODS - Metadata Object Description Schema</option>
        </select>
    </div>

    <div class="form-group">
        <label>Enable Multi-Sector Mode</label>
        <p class="form-help">Allow multiple GLAM types within the same installation.</p>
        <label class="toggle">
            <input type="checkbox" name="multi_sector" value="1">
            <span>Enable multi-sector support</span>
        </label>
    </div>

    <div class="form-group">
        <label>Default Identifier Scheme</label>
        <select name="identifier_scheme" class="form-control">
            <option value="auto">Auto-increment</option>
            <option value="accession">Accession-based (YYYY/NNN)</option>
            <option value="repository">Repository-prefixed</option>
            <option value="custom">Custom pattern</option>
        </select>
    </div>

    <button type="submit" class="btn btn-primary">Save GLAM Settings</button>
</form>
