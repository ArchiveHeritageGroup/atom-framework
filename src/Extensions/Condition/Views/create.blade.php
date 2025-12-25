@extends('layouts.default')

@section('title', $pageTitle)

@section('content')
<div class="container-fluid px-4">
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item"><a href="/{{ $object->slug }}">{{ $object->title ?? $object->identifier }}</a></li>
            <li class="breadcrumb-item"><a href="/{{ $object->slug }}/condition">Condition</a></li>
            <li class="breadcrumb-item active">New Report</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">
            <i class="fas fa-plus-circle me-2"></i>{{ $pageTitle }}
        </h1>
    </div>

    <form method="POST" action="/{{ $object->slug }}/condition" id="conditionForm">
        @csrf
        
        <div class="row g-4">
            {{-- Main Form --}}
            <div class="col-lg-8">
                {{-- Basic Information --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-info-circle me-2"></i>Basic Information
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Event Type <span class="text-danger">*</span></label>
                                <select name="event_type" class="form-select" required>
                                    @foreach($vocabularies['event_types'] as $code => $name)
                                    <option value="{{ $code }}" {{ $code === 'condition_check' ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Assessment Date <span class="text-danger">*</span></label>
                                <input type="date" name="event_date" class="form-control" 
                                       value="{{ date('Y-m-d') }}" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Overall Condition <span class="text-danger">*</span></label>
                                <select name="overall_condition" class="form-select" required>
                                    <option value="">Select condition...</option>
                                    @foreach($vocabularies['condition_terms'] as $code => $name)
                                    <option value="{{ $code }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Condition Rating (1-10)</label>
                                <input type="number" name="condition_rating" class="form-control" 
                                       min="1" max="10" placeholder="1 = Poor, 10 = Excellent">
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Damage Assessment --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-exclamation-triangle me-2"></i>Damage Assessment
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Damage Type</label>
                                <select name="damage_type" class="form-select">
                                    <option value="">No damage / Not applicable</option>
                                    @foreach($vocabularies['damage_types'] as $code => $name)
                                    <option value="{{ $code }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Severity</label>
                                <select name="severity" class="form-select">
                                    <option value="">Select severity...</option>
                                    @foreach($vocabularies['severity_levels'] as $code => $name)
                                    <option value="{{ $code }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Location on Object</label>
                                <select name="location_on_object" class="form-select">
                                    <option value="">Select location...</option>
                                    @foreach($vocabularies['location_zones'] as $code => $name)
                                    <option value="{{ $code }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Material Type</label>
                                <select name="material_type" class="form-select">
                                    <option value="">Select material...</option>
                                    @foreach($vocabularies['material_types'] as $code => $name)
                                    <option value="{{ $code }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Description</label>
                                <textarea name="description" class="form-control" rows="4" 
                                          placeholder="Describe the current condition, any damage observed, and relevant details..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Treatment Requirements --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-tools me-2"></i>Treatment Requirements
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-check">
                                    <input type="checkbox" name="treatment_required" value="1" 
                                           class="form-check-input" id="treatmentRequired">
                                    <label class="form-check-label" for="treatmentRequired">
                                        <strong>Treatment Required</strong>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6 treatment-fields" style="display: none;">
                                <label class="form-label">Treatment Priority</label>
                                <select name="treatment_priority" class="form-select">
                                    <option value="">Select priority...</option>
                                    <option value="low">Low</option>
                                    <option value="medium">Medium</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            
                            <div class="col-12 treatment-fields" style="display: none;">
                                <label class="form-label">Recommended Treatment</label>
                                <textarea name="treatment_recommended" class="form-control" rows="3" 
                                          placeholder="Describe recommended conservation treatment..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Environmental & Handling --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-thermometer-half me-2"></i>Environmental & Handling
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Environmental Factors</label>
                                <textarea name="environmental_factors" class="form-control" rows="2" 
                                          placeholder="Temperature, humidity, light exposure, etc."></textarea>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label">Handling Requirements</label>
                                <textarea name="handling_requirements" class="form-control" rows="2" 
                                          placeholder="Special handling instructions..."></textarea>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Storage Requirements</label>
                                <textarea name="storage_requirements" class="form-control" rows="2" 
                                          placeholder="Storage conditions..."></textarea>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Display Requirements</label>
                                <textarea name="display_requirements" class="form-control" rows="2" 
                                          placeholder="Display conditions..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Sidebar --}}
            <div class="col-lg-4">
                {{-- Object Summary --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-archive me-2"></i>Object
                        </h6>
                    </div>
                    <div class="card-body">
                        <h5>{{ $object->title ?? 'Untitled' }}</h5>
                        <p class="text-muted mb-1"><code>{{ $object->identifier }}</code></p>
                        <a href="/{{ $object->slug }}" class="btn btn-sm btn-outline-secondary mt-2">
                            <i class="fas fa-external-link-alt me-1"></i>View Object
                        </a>
                    </div>
                </div>

                {{-- Templates --}}
                @if(count($templates) > 0)
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-file-alt me-2"></i>Load Template
                        </h6>
                    </div>
                    <div class="card-body">
                        <select class="form-select" id="templateSelect">
                            <option value="">Select template...</option>
                            @foreach($templates as $template)
                            <option value="{{ $template->id }}" data-config="{{ $template->configuration }}">
                                {{ $template->name }}
                            </option>
                            @endforeach
                        </select>
                        <small class="text-muted mt-2 d-block">
                            Templates pre-fill form fields based on common assessment scenarios.
                        </small>
                    </div>
                </div>
                @endif

                {{-- Schedule Next Assessment --}}
                <div class="card mb-4">
                    <div class="card-header">
                        <h6 class="card-title mb-0">
                            <i class="fas fa-calendar-plus me-2"></i>Schedule Next Assessment
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">Next Assessment Date</label>
                            <input type="date" name="next_assessment_date" class="form-control">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Frequency</label>
                            <select name="assessment_frequency" class="form-select">
                                <option value="monthly">Monthly</option>
                                <option value="quarterly">Quarterly</option>
                                <option value="biannual">Bi-annual</option>
                                <option value="annual" selected>Annual</option>
                                <option value="biennial">Every 2 years</option>
                                <option value="as_needed">As needed</option>
                            </select>
                        </div>
                        <div>
                            <label class="form-label">Notes</label>
                            <textarea name="assessment_notes" class="form-control" rows="2" 
                                      placeholder="Assessment scheduling notes..."></textarea>
                        </div>
                    </div>
                </div>

                {{-- Actions --}}
                <div class="card">
                    <div class="card-body">
                        <button type="submit" class="btn btn-primary w-100 mb-2">
                            <i class="fas fa-save me-1"></i>Save Condition Report
                        </button>
                        <a href="/{{ $object->slug }}/condition" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-times me-1"></i>Cancel
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Toggle treatment fields
    const treatmentCheckbox = document.getElementById('treatmentRequired');
    const treatmentFields = document.querySelectorAll('.treatment-fields');
    
    treatmentCheckbox.addEventListener('change', function() {
        treatmentFields.forEach(function(field) {
            field.style.display = treatmentCheckbox.checked ? 'block' : 'none';
        });
    });
    
    // Template loading
    const templateSelect = document.getElementById('templateSelect');
    if (templateSelect) {
        templateSelect.addEventListener('change', function() {
            const option = this.options[this.selectedIndex];
            const config = option.dataset.config;
            
            if (config) {
                try {
                    const data = JSON.parse(config);
                    // Apply template values to form fields
                    Object.keys(data).forEach(function(key) {
                        const field = document.querySelector(`[name="${key}"]`);
                        if (field) {
                            field.value = data[key];
                        }
                    });
                } catch (e) {
                    console.error('Error parsing template config:', e);
                }
            }
        });
    }
});
</script>
@endsection
