@extends('layouts.default')

@section('title', $pageTitle)

@section('content')
<div class="container-fluid px-4">
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item"><a href="/{{ $object->slug }}">{{ $object->title ?? $object->identifier }}</a></li>
            <li class="breadcrumb-item active">Condition</li>
        </ol>
    </nav>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">
            <i class="fas fa-clipboard-check me-2"></i>Condition Report
        </h1>
        <div class="btn-group">
            <a href="/{{ $object->slug }}/condition/new" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>New Report
            </a>
            <a href="/{{ $object->slug }}/condition/treatment/new" class="btn btn-outline-success">
                <i class="fas fa-tools me-1"></i>Record Treatment
            </a>
            <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                    <i class="fas fa-download me-1"></i>Export
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="/{{ $object->slug }}/condition/export?format=json">JSON</a></li>
                    <li><a class="dropdown-item" href="/{{ $object->slug }}/condition/export?format=iiif-annotation">IIIF Annotation</a></li>
                    <li><a class="dropdown-item" href="/{{ $object->slug }}/condition/export?format=iiif-manifest">IIIF Manifest</a></li>
                    <li><a class="dropdown-item" href="/{{ $object->slug }}/condition/export?format=preservation-package">Preservation Package</a></li>
                </ul>
            </div>
        </div>
    </div>

    {{-- Object Info & Risk Score --}}
    <div class="row g-4 mb-4">
        <div class="col-md-8">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-archive me-2"></i>Object Information
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Title</dt>
                                <dd class="col-sm-8">{{ $object->title ?? 'Untitled' }}</dd>

                                <dt class="col-sm-4">Identifier</dt>
                                <dd class="col-sm-8"><code>{{ $object->identifier }}</code></dd>

                                <dt class="col-sm-4">Level</dt>
                                <dd class="col-sm-8">{{ $object->level_of_description_id ?? '-' }}</dd>
                            </dl>
                        </div>
                        <div class="col-md-6">
                            @if($latestCondition)
                            <dl class="row mb-0">
                                <dt class="col-sm-4">Current Condition</dt>
                                <dd class="col-sm-8">
                                    @php
                                        $condColors = [
                                            'excellent' => 'success',
                                            'good' => 'success',
                                            'fair' => 'warning',
                                            'poor' => 'danger',
                                            'critical' => 'dark'
                                        ];
                                    @endphp
                                    <span class="badge bg-{{ $condColors[$latestCondition->overall_condition] ?? 'secondary' }} fs-6">
                                        {{ ucfirst($latestCondition->overall_condition ?? 'Unknown') }}
                                    </span>
                                </dd>

                                <dt class="col-sm-4">Last Assessed</dt>
                                <dd class="col-sm-8">{{ \Carbon\Carbon::parse($latestCondition->event_date)->format('M d, Y') }}</dd>

                                @if($latestCondition->treatment_required)
                                <dt class="col-sm-4">Treatment</dt>
                                <dd class="col-sm-8">
                                    <span class="badge bg-warning text-dark">Required</span>
                                    @if($latestCondition->treatment_priority)
                                    <span class="badge bg-{{ $latestCondition->treatment_priority === 'urgent' ? 'danger' : 'secondary' }}">
                                        {{ ucfirst($latestCondition->treatment_priority) }}
                                    </span>
                                    @endif
                                </dd>
                                @endif
                            </dl>
                            @else
                            <div class="text-center text-muted py-3">
                                <i class="fas fa-clipboard fa-2x mb-2"></i>
                                <p class="mb-0">No condition assessments yet</p>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card h-100 {{ $riskScore && $riskScore['score'] >= 7 ? 'border-danger' : ($riskScore && $riskScore['score'] >= 4 ? 'border-warning' : '') }}">
                <div class="card-header {{ $riskScore && $riskScore['score'] >= 7 ? 'bg-danger text-white' : ($riskScore && $riskScore['score'] >= 4 ? 'bg-warning' : '') }}">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>Risk Assessment
                    </h5>
                </div>
                <div class="card-body text-center">
                    @if($riskScore)
                    <div class="display-2 fw-bold {{ $riskScore['score'] >= 7 ? 'text-danger' : ($riskScore['score'] >= 4 ? 'text-warning' : 'text-success') }}">
                        {{ number_format($riskScore['score'], 1) }}
                    </div>
                    <p class="text-muted mb-3">Risk Score (0-10)</p>
                    <div class="progress mb-3" style="height: 10px;">
                        <div class="progress-bar {{ $riskScore['score'] >= 7 ? 'bg-danger' : ($riskScore['score'] >= 4 ? 'bg-warning' : 'bg-success') }}" 
                             style="width: {{ $riskScore['score'] * 10 }}%"></div>
                    </div>
                    @if($riskScore['factors'])
                    <div class="text-start small">
                        <strong>Risk Factors:</strong>
                        <ul class="mb-0 ps-3">
                            @foreach($riskScore['factors'] as $factor)
                            <li>{{ $factor }}</li>
                            @endforeach
                        </ul>
                    </div>
                    @endif
                    @else
                    <div class="text-muted py-3">
                        <i class="fas fa-chart-line fa-2x mb-2"></i>
                        <p class="mb-0">No risk score calculated</p>
                    </div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    {{-- Assessment Schedule --}}
    @if($assessmentSchedule)
    <div class="alert alert-info d-flex align-items-center mb-4">
        <i class="fas fa-calendar-check fa-2x me-3"></i>
        <div>
            <strong>Next Assessment Scheduled:</strong> 
            {{ \Carbon\Carbon::parse($assessmentSchedule->next_assessment_date)->format('F d, Y') }}
            ({{ ucfirst($assessmentSchedule->frequency) }})
            @if($assessmentSchedule->notes)
            <br><small class="text-muted">{{ $assessmentSchedule->notes }}</small>
            @endif
        </div>
    </div>
    @endif

    <div class="row g-4">
        {{-- Condition History --}}
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history me-2"></i>Condition History
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Condition</th>
                                    <th>Damage</th>
                                    <th>Severity</th>
                                    <th>Assessor</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($events as $event)
                                <tr>
                                    <td>{{ \Carbon\Carbon::parse($event->event_date)->format('Y-m-d') }}</td>
                                    <td>
                                        @php
                                            $typeColors = [
                                                'condition_check' => 'primary',
                                                'conservation_treatment' => 'success',
                                                'damage_report' => 'danger',
                                                'loan_inspection' => 'info',
                                                'acquisition_inspection' => 'warning'
                                            ];
                                        @endphp
                                        <span class="badge bg-{{ $typeColors[$event->event_type] ?? 'secondary' }}">
                                            {{ str_replace('_', ' ', ucfirst($event->event_type)) }}
                                        </span>
                                    </td>
                                    <td>
                                        @if($event->overall_condition)
                                        <span class="badge bg-{{ $condColors[$event->overall_condition] ?? 'secondary' }}">
                                            {{ ucfirst($event->overall_condition) }}
                                        </span>
                                        @else
                                        -
                                        @endif
                                    </td>
                                    <td>{{ $event->damage_type ?? '-' }}</td>
                                    <td>
                                        @if($event->severity)
                                        @php
                                            $sevColors = [
                                                'none' => 'light',
                                                'minor' => 'info',
                                                'moderate' => 'warning',
                                                'severe' => 'danger',
                                                'critical' => 'dark'
                                            ];
                                        @endphp
                                        <span class="badge bg-{{ $sevColors[$event->severity] ?? 'secondary' }}">
                                            {{ ucfirst($event->severity) }}
                                        </span>
                                        @else
                                        -
                                        @endif
                                    </td>
                                    <td>{{ $event->assessor_username ?? 'Unknown' }}</td>
                                    <td>
                                        <a href="/{{ $object->slug }}/condition/{{ $event->id }}" 
                                           class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="fas fa-clipboard fa-2x mb-2"></i>
                                        <p class="mb-0">No condition events recorded</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Conservation History --}}
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header bg-success text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-tools me-2"></i>Conservation History
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse($conservationHistory as $treatment)
                        <div class="list-group-item">
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">{{ $treatment->treatment_type }}</h6>
                                <small>{{ \Carbon\Carbon::parse($treatment->treatment_date)->format('M Y') }}</small>
                            </div>
                            <p class="mb-1 small">{{ Str::limit($treatment->description, 100) }}</p>
                            @if($treatment->conservator)
                            <small class="text-muted">By: {{ $treatment->conservator }}</small>
                            @endif
                            @if($treatment->cost)
                            <br><small class="text-muted">Cost: R{{ number_format($treatment->cost, 2) }}</small>
                            @endif
                        </div>
                        @empty
                        <div class="list-group-item text-center text-muted py-4">
                            <i class="fas fa-tools fa-2x mb-2"></i>
                            <p class="mb-0">No conservation treatments recorded</p>
                        </div>
                        @endforelse
                    </div>
                </div>
                <div class="card-footer">
                    <a href="/{{ $object->slug }}/condition/treatment/new" class="btn btn-sm btn-success w-100">
                        <i class="fas fa-plus me-1"></i>Record Treatment
                    </a>
                </div>
            </div>
        </div>
    </div>

    {{-- Latest Condition Details --}}
    @if($latestCondition)
    <div class="card mt-4">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="fas fa-info-circle me-2"></i>Latest Assessment Details
            </h5>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6">
                    @if($latestCondition->description)
                    <h6>Description</h6>
                    <p>{{ $latestCondition->description }}</p>
                    @endif

                    @if($latestCondition->location_on_object)
                    <h6>Location on Object</h6>
                    <p>{{ $latestCondition->location_on_object }}</p>
                    @endif

                    @if($latestCondition->environmental_factors)
                    <h6>Environmental Factors</h6>
                    <p>{{ $latestCondition->environmental_factors }}</p>
                    @endif
                </div>
                <div class="col-md-6">
                    @if($latestCondition->handling_requirements)
                    <h6>Handling Requirements</h6>
                    <p>{{ $latestCondition->handling_requirements }}</p>
                    @endif

                    @if($latestCondition->storage_requirements)
                    <h6>Storage Requirements</h6>
                    <p>{{ $latestCondition->storage_requirements }}</p>
                    @endif

                    @if($latestCondition->display_requirements)
                    <h6>Display Requirements</h6>
                    <p>{{ $latestCondition->display_requirements }}</p>
                    @endif

                    @if($latestCondition->treatment_recommended)
                    <h6>Recommended Treatment</h6>
                    <p class="text-warning">{{ $latestCondition->treatment_recommended }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>
    @endif
</div>
@endsection
