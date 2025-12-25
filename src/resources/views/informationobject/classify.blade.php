{{-- Classify Information Object - Mimics AtoM 2.10 --}}
@extends('layouts.main')

@section('title', $pageTitle)

@section('content')
<div class="container-fluid">
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item"><a href="/{{ $object->slug }}">{{ $object->title ?? $object->identifier }}</a></li>
            <li class="breadcrumb-item"><a href="/{{ $object->slug }}/security">Security</a></li>
            <li class="breadcrumb-item active">{{ $currentClassification ? 'Reclassify' : 'Classify' }}</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-shield-alt me-2"></i>
                        {{ $currentClassification ? 'Reclassify Record' : 'Classify Record' }}
                    </h5>
                </div>
                <div class="card-body">
                    @if(request()->get('error'))
                        <div class="alert alert-danger">
                            @switch(request()->get('error'))
                                @case('invalid')
                                    Please select a valid classification level.
                                    @break
                                @case('failed')
                                    Failed to apply classification. Please try again.
                                    @break
                            @endswitch
                        </div>
                    @endif

                    {{-- Record Info --}}
                    <div class="alert alert-light border mb-4">
                        <h6 class="alert-heading">{{ $object->title ?? 'Untitled Record' }}</h6>
                        @if($object->identifier)
                            <small class="text-muted">Identifier: {{ $object->identifier }}</small>
                        @endif
                        @if($currentClassification)
                            <hr class="my-2">
                            <small>
                                <strong>Current Classification:</strong>
                                <span class="badge {{ $currentClassification->getBadgeClass() }}">
                                    {{ $currentClassification->classificationName }}
                                </span>
                            </small>
                        @endif
                    </div>

                    <form method="POST" action="/{{ $object->slug }}/security/classify">
                        {{-- Classification Level --}}
                        <fieldset class="mb-4">
                            <legend class="h6 border-bottom pb-2 mb-3">
                                <i class="fas fa-lock me-2"></i>Classification Level
                            </legend>

                            <div class="row">
                                @foreach($classifications as $classification)
                                    <div class="col-md-4 mb-3">
                                        <div class="form-check card h-100 {{ $currentClassification && $currentClassification->classificationId == $classification->id ? 'border-primary' : '' }}">
                                            <div class="card-body">
                                                <input class="form-check-input" type="radio" 
                                                       name="classification_id" 
                                                       id="classification_{{ $classification->id }}"
                                                       value="{{ $classification->id }}"
                                                       {{ $currentClassification && $currentClassification->classificationId == $classification->id ? 'checked' : '' }}
                                                       required>
                                                <label class="form-check-label w-100" for="classification_{{ $classification->id }}">
                                                    <span class="badge w-100 py-2 mb-2" style="background-color: {{ $classification->color }};">
                                                        <i class="{{ $classification->icon }} me-1"></i>
                                                        {{ $classification->name }}
                                                    </span>
                                                    <small class="d-block text-muted">Level {{ $classification->level }}</small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </fieldset>

                        {{-- Classification Details --}}
                        <fieldset class="mb-4">
                            <legend class="h6 border-bottom pb-2 mb-3">
                                <i class="fas fa-file-alt me-2"></i>Classification Details
                            </legend>

                            <div class="mb-3">
                                <label for="reason" class="form-label">Reason for Classification</label>
                                <textarea name="reason" id="reason" class="form-control" rows="3"
                                          placeholder="Explain why this classification level is appropriate...">{{ $currentClassification->reason ?? '' }}</textarea>
                                <div class="form-text">
                                    Document the justification for this classification decision.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="handling_instructions" class="form-label">Special Handling Instructions</label>
                                <textarea name="handling_instructions" id="handling_instructions" class="form-control" rows="2"
                                          placeholder="Any special handling requirements...">{{ $currentClassification->handlingInstructions ?? '' }}</textarea>
                            </div>
                        </fieldset>

                        {{-- Review & Declassification --}}
                        <fieldset class="mb-4">
                            <legend class="h6 border-bottom pb-2 mb-3">
                                <i class="fas fa-calendar-alt me-2"></i>Review & Declassification
                            </legend>

                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="review_date" class="form-label">Review Date</label>
                                    <input type="date" name="review_date" id="review_date" class="form-control"
                                           value="{{ $currentClassification && $currentClassification->reviewDate ? date('Y-m-d', strtotime($currentClassification->reviewDate)) : '' }}"
                                           min="{{ date('Y-m-d', strtotime('+1 day')) }}">
                                    <div class="form-text">
                                        Date when classification should be reviewed.
                                    </div>
                                </div>

                                <div class="col-md-6 mb-3">
                                    <label for="declassify_date" class="form-label">Auto-Declassify Date</label>
                                    <input type="date" name="declassify_date" id="declassify_date" class="form-control"
                                           value="{{ $currentClassification && $currentClassification->declassifyDate ? date('Y-m-d', strtotime($currentClassification->declassifyDate)) : '' }}"
                                           min="{{ date('Y-m-d', strtotime('+1 day')) }}">
                                    <div class="form-text">
                                        Date when classification will be automatically removed.
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="declassify_to_id" class="form-label">Declassify To Level</label>
                                <select name="declassify_to_id" id="declassify_to_id" class="form-control">
                                    <option value="">-- Remove classification entirely --</option>
                                    @foreach($classifications as $classification)
                                        <option value="{{ $classification->id }}"
                                                {{ $currentClassification && $currentClassification->declassifyToId == $classification->id ? 'selected' : '' }}>
                                            {{ $classification->name }} (Level {{ $classification->level }})
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">
                                    When auto-declassified, change to this level instead of making public.
                                </div>
                            </div>
                        </fieldset>

                        {{-- Inheritance --}}
                        <fieldset class="mb-4">
                            <legend class="h6 border-bottom pb-2 mb-3">
                                <i class="fas fa-sitemap me-2"></i>Inheritance
                            </legend>

                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" 
                                       name="inherit_to_children" id="inherit_to_children" value="1"
                                       {{ !$currentClassification || $currentClassification->inheritToChildren ? 'checked' : '' }}>
                                <label class="form-check-label" for="inherit_to_children">
                                    Apply this classification to all child records
                                </label>
                            </div>
                            <div class="form-text">
                                If checked, all descendant records will inherit this classification level.
                            </div>
                        </fieldset>

                        {{-- Actions --}}
                        <div class="d-flex justify-content-between border-top pt-3">
                            <a href="/{{ $object->slug }}/security" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i>Cancel
                            </a>
                            <div>
                                @if($currentClassification)
                                    <button type="submit" name="action" value="declassify" class="btn btn-outline-success me-2">
                                        <i class="fas fa-unlock me-1"></i>Remove Classification
                                    </button>
                                @endif
                                <button type="submit" name="action" value="classify" class="btn btn-warning">
                                    <i class="fas fa-shield-alt me-1"></i>
                                    {{ $currentClassification ? 'Update Classification' : 'Apply Classification' }}
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .form-check.card {
        cursor: pointer;
    }
    .form-check.card:hover {
        border-color: #0d6efd;
    }
    .form-check-input:checked + .form-check-label .badge {
        box-shadow: 0 0 0 3px rgba(13, 110, 253, 0.25);
    }
    .bg-orange { background-color: #fd7e14 !important; }
    .bg-purple { background-color: #6f42c1 !important; }
</style>
@endpush
