{{-- Security Classification Fieldset - Include in Edit Form - Mimics AtoM 2.10 --}}
{{-- Include this in your archival description edit form --}}

<fieldset class="border rounded p-3 mb-4" id="security-fieldset">
    <legend class="float-none w-auto px-2 fs-6">
        <a class="text-decoration-none" data-bs-toggle="collapse" href="#securityCollapse" 
           role="button" aria-expanded="false" aria-controls="securityCollapse">
            <i class="fas fa-shield-alt me-2"></i>Security Classification
            @if($classification)
                <span class="badge {{ $classification->getBadgeClass() }} ms-2">
                    {{ $classification->classificationName }}
                </span>
            @else
                <span class="badge bg-success ms-2">Public</span>
            @endif
            <i class="fas fa-chevron-down ms-2 collapse-icon"></i>
        </a>
    </legend>

    <div class="collapse" id="securityCollapse">
        <div class="pt-3">
            {{-- Current Status Alert --}}
            @if($classification)
                <div class="alert alert-warning py-2 small">
                    <i class="fas fa-lock me-2"></i>
                    This record is classified as <strong>{{ $classification->classificationName }}</strong>
                    by {{ $classification->classifiedByUsername ?? 'System' }}
                    on {{ $classification->classifiedAt ? date('Y-m-d', strtotime($classification->classifiedAt)) : 'Unknown' }}.
                </div>
            @endif

            {{-- Classification Level --}}
            <div class="mb-3">
                <label for="security_classification_id" class="form-label">
                    Security Classification
                    <i class="fas fa-question-circle text-muted ms-1" 
                       data-bs-toggle="tooltip" 
                       title="Select the security classification level for this record. Users with lower clearance will not be able to view this record."></i>
                </label>
                <select name="security_classification_id" id="security_classification_id" class="form-select">
                    <option value="">-- Public (No Classification) --</option>
                    @foreach($classifications as $c)
                        <option value="{{ $c->id }}" 
                                {{ $classification && $classification->classificationId == $c->id ? 'selected' : '' }}
                                data-color="{{ $c->color }}"
                                data-level="{{ $c->level }}">
                            {{ $c->name }} (Level {{ $c->level }})
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Classification Reason --}}
            <div class="mb-3 classification-details" style="{{ $classification ? '' : 'display: none;' }}">
                <label for="security_reason" class="form-label">
                    Classification Reason
                </label>
                <textarea name="security_reason" id="security_reason" class="form-control" rows="2"
                          placeholder="Reason for classification...">{{ $classification->reason ?? '' }}</textarea>
            </div>

            {{-- Review Date --}}
            <div class="row classification-details" style="{{ $classification ? '' : 'display: none;' }}">
                <div class="col-md-6 mb-3">
                    <label for="security_review_date" class="form-label">
                        Review Date
                    </label>
                    <input type="date" name="security_review_date" id="security_review_date" class="form-control"
                           value="{{ $classification && $classification->reviewDate ? date('Y-m-d', strtotime($classification->reviewDate)) : '' }}">
                </div>
                <div class="col-md-6 mb-3">
                    <label for="security_declassify_date" class="form-label">
                        Auto-Declassify Date
                    </label>
                    <input type="date" name="security_declassify_date" id="security_declassify_date" class="form-control"
                           value="{{ $classification && $classification->declassifyDate ? date('Y-m-d', strtotime($classification->declassifyDate)) : '' }}">
                </div>
            </div>

            {{-- Handling Instructions --}}
            <div class="mb-3 classification-details" style="{{ $classification ? '' : 'display: none;' }}">
                <label for="security_handling_instructions" class="form-label">
                    Handling Instructions
                </label>
                <textarea name="security_handling_instructions" id="security_handling_instructions" 
                          class="form-control" rows="2"
                          placeholder="Special handling requirements...">{{ $classification->handlingInstructions ?? '' }}</textarea>
            </div>

            {{-- Inherit to Children --}}
            <div class="form-check classification-details" style="{{ $classification ? '' : 'display: none;' }}">
                <input class="form-check-input" type="checkbox" 
                       name="security_inherit_to_children" id="security_inherit_to_children" value="1"
                       {{ !$classification || $classification->inheritToChildren ? 'checked' : '' }}>
                <label class="form-check-label" for="security_inherit_to_children">
                    Apply to child records
                </label>
            </div>

            {{-- Link to Full Security Page --}}
            @if(isset($object) && $object->slug)
                <div class="mt-3 pt-3 border-top">
                    <a href="/{{ $object->slug }}/security" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-external-link-alt me-1"></i>View Full Security Details
                    </a>
                </div>
            @endif
        </div>
    </div>
</fieldset>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const classificationSelect = document.getElementById('security_classification_id');
    const detailsElements = document.querySelectorAll('.classification-details');
    
    if (classificationSelect) {
        classificationSelect.addEventListener('change', function() {
            const hasClassification = this.value !== '';
            detailsElements.forEach(el => {
                el.style.display = hasClassification ? '' : 'none';
            });
        });
    }
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>
@endpush

@push('styles')
<style>
    #security-fieldset legend a {
        color: inherit;
    }
    #security-fieldset legend a:hover {
        color: #0d6efd;
    }
    #security-fieldset .collapse-icon {
        transition: transform 0.2s;
    }
    #security-fieldset .collapsed .collapse-icon {
        transform: rotate(-90deg);
    }
    .bg-orange { background-color: #fd7e14 !important; }
    .bg-purple { background-color: #6f42c1 !important; }
</style>
@endpush
