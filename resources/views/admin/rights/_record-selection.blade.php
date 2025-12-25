{{--
    Batch Rights - Record Selection Section
    Mimics AtoM 2.10 /admin/rights/batch layout
--}}

<fieldset class="mb-4">
    <legend class="h5 border-bottom pb-2 mb-3">
        <i class="fas fa-folder-open me-2"></i>Option A: Select from hierarchy
    </legend>

    <div class="row">
        <div class="col-md-8">
            {{-- Searchable Record Select --}}
            <div class="mb-3">
                <label for="information_object_id" class="form-label">
                    Select a record
                </label>
                <x-form.searchable-record-select
                    name="information_object_id"
                    id="information_object_id"
                    :records="$records"
                    placeholder="-- Select a record --"
                    :selected="old('information_object_id')"
                />
            </div>
        </div>

        <div class="col-md-4">
            {{-- Scope Options --}}
            <div class="mb-3">
                <label class="form-label d-block">Apply to</label>
                
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="scope" id="scope_selected" 
                           value="selected" checked>
                    <label class="form-check-label" for="scope_selected">
                        Selected only
                    </label>
                </div>
                
                <div class="form-check">
                    <input class="form-check-input" type="radio" 
                           name="scope" id="scope_children" 
                           value="children">
                    <label class="form-check-label" for="scope_children">
                        Direct children
                    </label>
                </div>
                
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" 
                           name="include_parent" id="include_parent" 
                           value="1" checked>
                    <label class="form-check-label" for="include_parent">
                        Include the parent record itself
                    </label>
                </div>
            </div>
        </div>
    </div>
</fieldset>

<fieldset class="mb-4">
    <legend class="h5 border-bottom pb-2 mb-3">
        <i class="fas fa-keyboard me-2"></i>Option B: Enter Object IDs Manually
    </legend>

    <div class="mb-3">
        <label for="object_ids" class="form-label">
            Object IDs (comma-separated)
        </label>
        <textarea 
            class="form-control" 
            id="object_ids" 
            name="object_ids" 
            rows="3"
            placeholder="e.g., 12345, 12346, 12347"
        >{{ old('object_ids') }}</textarea>
        <div class="form-text">
            Enter the database IDs of records to apply rights to.
        </div>
    </div>
</fieldset>
