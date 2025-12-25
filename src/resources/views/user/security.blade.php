{{-- User Security Tab - Integrated into User Edit - Mimics AtoM 2.10 --}}
@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
<div class="row">
    <div class="col-lg-10 mx-auto">
        {{-- Breadcrumb --}}
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/admin">Admin</a></li>
                <li class="breadcrumb-item"><a href="/admin/users">Users</a></li>
                <li class="breadcrumb-item"><a href="/admin/users/{{ $user->id }}">{{ $user->username }}</a></li>
                <li class="breadcrumb-item active">Security Clearance</li>
            </ol>
        </nav>

        {{-- User Header --}}
        <div class="d-flex align-items-center mb-4">
            <div class="me-3">
                <div class="bg-secondary text-white rounded-circle d-flex align-items-center justify-content-center" 
                     style="width: 60px; height: 60px; font-size: 24px;">
                    {{ strtoupper(substr($user->username, 0, 1)) }}
                </div>
            </div>
            <div>
                <h2 class="mb-0">{{ $user->display_name ?? $user->username }}</h2>
                <p class="text-muted mb-0">{{ $user->email }}</p>
            </div>
            <div class="ms-auto">
                @if($clearance)
                    <span class="badge {{ $clearance->getBadgeClass() }} fs-5">
                        <i class="{{ $clearance->classificationIcon ?? 'fa-lock' }} me-1"></i>
                        {{ $clearance->classificationName }}
                    </span>
                @else
                    <span class="badge bg-secondary fs-5">
                        <i class="fa-globe me-1"></i>No Clearance
                    </span>
                @endif
            </div>
        </div>

        {{-- Tabs --}}
        <ul class="nav nav-tabs mb-4">
            <li class="nav-item">
                <a class="nav-link" href="/admin/users/{{ $user->id }}/edit">
                    <i class="fas fa-user me-1"></i>User Details
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link active" href="/admin/users/{{ $user->id }}/security">
                    <i class="fas fa-shield-alt me-1"></i>Security Clearance
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/admin/users/{{ $user->id }}/groups">
                    <i class="fas fa-users me-1"></i>Groups
                </a>
            </li>
        </ul>

        {{-- Flash Messages --}}
        @if(request()->get('success'))
            <div class="alert alert-success alert-dismissible fade show">
                @switch(request()->get('success'))
                    @case('updated')
                        Security clearance has been updated successfully.
                        @break
                    @case('revoked')
                        Security clearance has been revoked.
                        @break
                @endswitch
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="row">
            {{-- Main Form --}}
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-light">
                        <h5 class="mb-0">
                            <i class="fas fa-shield-alt me-2"></i>Security Clearance Settings
                        </h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="/admin/users/{{ $user->id }}/security">
                            {{-- Current Status --}}
                            @if($clearance)
                                <div class="alert alert-info mb-4">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <i class="fas fa-info-circle fa-2x"></i>
                                        </div>
                                        <div>
                                            <strong>Current Clearance:</strong> {{ $clearance->classificationName }}<br>
                                            <small class="text-muted">
                                                Granted by {{ $clearance->grantedByUsername ?? 'System' }}
                                                on {{ $clearance->grantedAt ? date('Y-m-d', strtotime($clearance->grantedAt)) : 'Unknown' }}
                                                @if($clearance->expiresAt)
                                                    | Expires: {{ date('Y-m-d', strtotime($clearance->expiresAt)) }}
                                                @endif
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            @else
                                <div class="alert alert-warning mb-4">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    This user does not have a security clearance. They can only access public documents.
                                </div>
                            @endif

                            {{-- Classification Selection --}}
                            <div class="mb-4">
                                <label for="classification_id" class="form-label fw-bold">
                                    <i class="fas fa-lock me-1"></i>Clearance Level
                                </label>
                                <select name="classification_id" id="classification_id" class="form-select form-select-lg">
                                    @foreach($classifications as $id => $name)
                                        <option value="{{ $id }}" 
                                                {{ $clearance && $clearance->classificationId == $id ? 'selected' : '' }}>
                                            {{ $name }}
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">
                                    Select the maximum classification level this user should be able to access.
                                </div>
                            </div>

                            {{-- Expiry Date --}}
                            <div class="mb-4">
                                <label for="expires_at" class="form-label fw-bold">
                                    <i class="fas fa-calendar-times me-1"></i>Expiry Date
                                </label>
                                <input type="date" name="expires_at" id="expires_at" class="form-control"
                                       value="{{ $clearance && $clearance->expiresAt ? date('Y-m-d', strtotime($clearance->expiresAt)) : '' }}"
                                       min="{{ date('Y-m-d', strtotime('+1 day')) }}">
                                <div class="form-text">
                                    Leave empty for no automatic expiry.
                                </div>
                            </div>

                            {{-- Notes --}}
                            <div class="mb-4">
                                <label for="notes" class="form-label fw-bold">
                                    <i class="fas fa-sticky-note me-1"></i>Notes
                                </label>
                                <textarea name="notes" id="notes" class="form-control" rows="3"
                                          placeholder="Enter any notes about this clearance...">{{ $clearance ? $clearance->notes : '' }}</textarea>
                            </div>

                            {{-- Actions --}}
                            <div class="d-flex justify-content-between border-top pt-3">
                                <div>
                                    @if($clearance)
                                        <button type="button" class="btn btn-outline-danger" 
                                                data-bs-toggle="modal" data-bs-target="#revokeModal">
                                            <i class="fas fa-times me-1"></i>Revoke Clearance
                                        </button>
                                    @endif
                                </div>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>
                                    {{ $clearance ? 'Update Clearance' : 'Grant Clearance' }}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Sidebar - Classification Guide --}}
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header bg-light">
                        <h6 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>Classification Levels
                        </h6>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span class="badge bg-success">Public</span>
                                <small class="text-muted">Level 0</small>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span class="badge bg-info">Internal</span>
                                <small class="text-muted">Level 1</small>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span class="badge bg-warning text-dark">Restricted</span>
                                <small class="text-muted">Level 2</small>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span class="badge bg-orange text-white">Confidential</span>
                                <small class="text-muted">Level 3</small>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span class="badge bg-danger">Secret</span>
                                <small class="text-muted">Level 4</small>
                            </li>
                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                <span class="badge bg-purple text-white">Top Secret</span>
                                <small class="text-muted">Level 5</small>
                            </li>
                        </ul>
                    </div>
                </div>

                {{-- History --}}
                @if(!empty($history))
                    <div class="card mt-4">
                        <div class="card-header bg-light">
                            <h6 class="mb-0">
                                <i class="fas fa-history me-2"></i>Change History
                            </h6>
                        </div>
                        <div class="card-body p-0">
                            <ul class="list-group list-group-flush">
                                @foreach(array_slice($history, 0, 5) as $record)
                                    <li class="list-group-item small">
                                        <div class="d-flex justify-content-between">
                                            <span class="badge {{ $record['action'] == 'revoked' ? 'bg-danger' : ($record['action'] == 'granted' ? 'bg-success' : 'bg-info') }}">
                                                {{ ucfirst($record['action']) }}
                                            </span>
                                            <small class="text-muted">
                                                {{ date('Y-m-d', strtotime($record['created_at'])) }}
                                            </small>
                                        </div>
                                        @if($record['new_name'])
                                            <small>{{ $record['previous_name'] ?? 'None' }} → {{ $record['new_name'] }}</small>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

{{-- Revoke Modal --}}
@if($clearance)
<div class="modal fade" id="revokeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/admin/users/{{ $user->id }}/security">
                <input type="hidden" name="action" value="revoke">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Revoke Clearance
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to revoke the security clearance for <strong>{{ $user->username }}</strong>?</p>
                    <div class="mb-3">
                        <label class="form-label">Reason <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="2" required></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">Revoke Clearance</button>
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
