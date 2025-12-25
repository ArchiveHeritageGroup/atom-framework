{{-- Information Object Security Classification - Mimics AtoM 2.10 --}}
@extends('layouts.main')

@section('title', $pageTitle)

@section('content')
<div class="container-fluid">
    {{-- Breadcrumb --}}
    <nav aria-label="breadcrumb" class="mb-3">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item"><a href="/{{ $object->slug }}">{{ $object->title ?? $object->identifier }}</a></li>
            <li class="breadcrumb-item active">Security Classification</li>
        </ol>
    </nav>

    <div class="row">
        {{-- Main Content --}}
        <div class="col-lg-8">
            {{-- Object Header --}}
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-file-alt me-2"></i>{{ $object->title ?? 'Untitled' }}
                        </h5>
                        @if($classification)
                            <span class="badge {{ $classification->getBadgeClass() }} fs-6">
                                <i class="{{ $classification->classificationIcon ?? 'fa-lock' }} me-1"></i>
                                {{ $classification->classificationName }}
                            </span>
                        @else
                            <span class="badge bg-success fs-6">
                                <i class="fa-globe me-1"></i>Public
                            </span>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    @if($object->identifier)
                        <p class="mb-1"><strong>Identifier:</strong> {{ $object->identifier }}</p>
                    @endif
                </div>
            </div>

            {{-- Flash Messages --}}
            @if(request()->get('success'))
                <div class="alert alert-success alert-dismissible fade show">
                    @switch(request()->get('success'))
                        @case('classified')
                            Security classification has been applied successfully.
                            @break
                        @case('declassified')
                            Security classification has been removed.
                            @break
                    @endswitch
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            {{-- Current Classification Details --}}
            <div class="card mb-4">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">
                        <i class="fas fa-shield-alt me-2"></i>Security Classification
                    </h5>
                    <a href="/{{ $object->slug }}/security/classify" class="btn btn-sm btn-primary">
                        <i class="fas fa-edit me-1"></i>
                        {{ $classification ? 'Reclassify' : 'Classify' }}
                    </a>
                </div>
                <div class="card-body">
                    @if($classification)
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small">Classification Level</label>
                                <p class="mb-0">
                                    <span class="badge {{ $classification->getBadgeClass() }} fs-6">
                                        <i class="{{ $classification->classificationIcon ?? 'fa-lock' }} me-1"></i>
                                        {{ $classification->classificationName }}
                                    </span>
                                </p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small">Classified By</label>
                                <p class="mb-0">{{ $classification->classifiedByUsername ?? 'System' }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small">Classification Date</label>
                                <p class="mb-0">{{ $classification->classifiedAt ? date('Y-m-d', strtotime($classification->classifiedAt)) : '-' }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted small">Review Date</label>
                                <p class="mb-0">
                                    @if($classification->reviewDate)
                                        {{ date('Y-m-d', strtotime($classification->reviewDate)) }}
                                        @if($classification->isDueForReview())
                                            <span class="badge bg-warning text-dark ms-1">Due</span>
                                        @endif
                                    @else
                                        <span class="text-muted">Not set</span>
                                    @endif
                                </p>
                            </div>
                            @if($classification->declassifyDate)
                                <div class="col-md-6 mb-3">
                                    <label class="form-label text-muted small">Auto-declassify Date</label>
                                    <p class="mb-0">
                                        {{ date('Y-m-d', strtotime($classification->declassifyDate)) }}
                                        @if($classification->isDueForDeclassification())
                                            <span class="badge bg-info ms-1">Due</span>
                                        @endif
                                    </p>
                                </div>
                            @endif
                            @if($classification->reason)
                                <div class="col-12 mb-3">
                                    <label class="form-label text-muted small">Classification Reason</label>
                                    <p class="mb-0">{{ $classification->reason }}</p>
                                </div>
                            @endif
                            @if($classification->handlingInstructions)
                                <div class="col-12">
                                    <label class="form-label text-muted small">Handling Instructions</label>
                                    <div class="alert alert-warning mb-0">
                                        <i class="fas fa-exclamation-triangle me-2"></i>
                                        {{ $classification->handlingInstructions }}
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- Declassify Button --}}
                        <div class="border-top mt-3 pt-3">
                            <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#declassifyModal">
                                <i class="fas fa-unlock me-1"></i>Remove Classification
                            </button>
                        </div>
                    @else
                        <div class="text-center py-4">
                            <i class="fas fa-globe fa-3x text-success mb-3"></i>
                            <h5>This record is publicly accessible</h5>
                            <p class="text-muted">No security classification has been applied to this record.</p>
                            <a href="/{{ $object->slug }}/security/classify" class="btn btn-primary">
                                <i class="fas fa-lock me-1"></i>Apply Classification
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            {{-- Classification History --}}
            @if(!empty($history))
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-history me-2"></i>Classification History
                        </h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date</th>
                                        <th>Action</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>By</th>
                                        <th>Reason</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($history as $record)
                                        <tr>
                                            <td>{{ date('Y-m-d H:i', strtotime($record->created_at)) }}</td>
                                            <td>
                                                <span class="badge {{ $record->action == 'declassified' ? 'bg-success' : ($record->action == 'reclassified' ? 'bg-info' : 'bg-warning text-dark') }}">
                                                    {{ ucfirst($record->action) }}
                                                </span>
                                            </td>
                                            <td>{{ $record->previous_name ?? '-' }}</td>
                                            <td>{{ $record->new_name ?? '-' }}</td>
                                            <td>{{ $record->changed_by_username ?? 'System' }}</td>
                                            <td>{{ $record->reason ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            @endif
        </div>

        {{-- Sidebar --}}
        <div class="col-lg-4">
            {{-- Actions --}}
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-cog me-2"></i>Actions</h6>
                </div>
                <div class="list-group list-group-flush">
                    <a href="/{{ $object->slug }}" class="list-group-item list-group-item-action">
                        <i class="fas fa-eye me-2"></i>View Record
                    </a>
                    <a href="/{{ $object->slug }}/edit" class="list-group-item list-group-item-action">
                        <i class="fas fa-edit me-2"></i>Edit Record
                    </a>
                    <a href="/{{ $object->slug }}/security/classify" class="list-group-item list-group-item-action">
                        <i class="fas fa-shield-alt me-2"></i>
                        {{ $classification ? 'Change Classification' : 'Apply Classification' }}
                    </a>
                </div>
            </div>

            {{-- Classification Guide --}}
            <div class="card">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>Classification Levels</h6>
                </div>
                <div class="card-body p-0">
                    <ul class="list-group list-group-flush">
                        <li class="list-group-item">
                            <span class="badge bg-success me-2">Public</span>
                            <small class="text-muted">Available to everyone</small>
                        </li>
                        <li class="list-group-item">
                            <span class="badge bg-info me-2">Internal</span>
                            <small class="text-muted">Staff only</small>
                        </li>
                        <li class="list-group-item">
                            <span class="badge bg-warning text-dark me-2">Restricted</span>
                            <small class="text-muted">Limited access</small>
                        </li>
                        <li class="list-group-item">
                            <span class="badge bg-orange text-white me-2">Confidential</span>
                            <small class="text-muted">Sensitive content</small>
                        </li>
                        <li class="list-group-item">
                            <span class="badge bg-danger me-2">Secret</span>
                            <small class="text-muted">Highly sensitive</small>
                        </li>
                        <li class="list-group-item">
                            <span class="badge bg-purple text-white me-2">Top Secret</span>
                            <small class="text-muted">Maximum restriction</small>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- Declassify Modal --}}
@if($classification)
<div class="modal fade" id="declassifyModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/{{ $object->slug }}/security/declassify">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-unlock me-2"></i>Remove Classification
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to remove the security classification from this record?</p>
                    <p class="text-success">
                        <i class="fas fa-globe me-1"></i>
                        This record will become publicly accessible.
                    </p>
                    <div class="mb-3">
                        <label class="form-label">Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="2" required 
                                  placeholder="Enter reason for declassification..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-unlock me-1"></i>Remove Classification
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endif
@endsection

@push('styles')
<style>
    .bg-orange { background-color: #fd7e14 !important; }
    .bg-purple { background-color: #6f42c1 !important; }
</style>
@endpush
