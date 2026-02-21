<h2>Step 4: AI & Automation</h2>
<p>Configure AI-powered features. These require additional dependencies (Python, spaCy, Argos Translate).</p>

<form id="ai-form" onsubmit="saveAISettings(event)">
    <h3>Named Entity Recognition (NER)</h3>
    <div class="form-group">
        <label class="toggle">
            <input type="checkbox" name="ner_enabled" value="1">
            <span>Enable NER extraction</span>
        </label>
        <p class="form-help">Automatically extract persons, organizations, places, and dates from descriptions.</p>
    </div>

    <h3>Machine Translation</h3>
    <div class="form-group">
        <label class="toggle">
            <input type="checkbox" name="translation_enabled" value="1">
            <span>Enable offline translation (Argos Translate)</span>
        </label>
        <div class="form-row">
            <label>Default source language</label>
            <select name="translate_from" class="form-control form-control-sm">
                <option value="en">English</option>
                <option value="af">Afrikaans</option>
                <option value="fr">French</option>
                <option value="de">German</option>
                <option value="pt">Portuguese</option>
                <option value="es">Spanish</option>
            </select>
            <label>Default target language</label>
            <select name="translate_to" class="form-control form-control-sm">
                <option value="af">Afrikaans</option>
                <option value="en">English</option>
                <option value="fr">French</option>
                <option value="zu">Zulu</option>
                <option value="xh">Xhosa</option>
            </select>
        </div>
    </div>

    <h3>Summarization</h3>
    <div class="form-group">
        <label class="toggle">
            <input type="checkbox" name="summarize_enabled" value="1">
            <span>Enable AI-powered summarization</span>
        </label>
    </div>

    <h3>Spell Check</h3>
    <div class="form-group">
        <label class="toggle">
            <input type="checkbox" name="spellcheck_enabled" value="1" checked>
            <span>Enable spell checking (aspell)</span>
        </label>
        <div class="form-row">
            <label>Language</label>
            <select name="spellcheck_lang" class="form-control form-control-sm">
                <option value="en">English</option>
                <option value="af">Afrikaans</option>
                <option value="fr">French</option>
            </select>
        </div>
    </div>

    <h3>Face Detection</h3>
    <div class="form-group">
        <label class="toggle">
            <input type="checkbox" name="face_detection_enabled" value="1">
            <span>Enable face detection</span>
        </label>
        <div class="form-row">
            <label>Backend</label>
            <select name="face_detection_backend" class="form-control form-control-sm">
                <option value="opencv">OpenCV (local)</option>
                <option value="aws">AWS Rekognition</option>
                <option value="azure">Azure Face API</option>
            </select>
        </div>
    </div>

    <button type="submit" class="btn btn-primary">Save AI Settings</button>
</form>
