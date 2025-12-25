{{-- Edit Security Clearance Form - Mimics AtoM 2.10 --}}
@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
<div class="row">
    <div class="col-lg-8 mx-auto">
        {{-- Breadcrumb --}}
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/admin">Admin</a></li>
                <li class="breadcrumb-item"><a href="/admin/security/clearances">Security Clearances</a></li>
                <li class="breadcrumb-item active">Edit - {{ $clearance->username }}</li>
            </ol>
        </nav>

        @if(request()->get('error'))
            <div class="alert alert-danger alert-dismissible fade show">
                @switch(request()->get('error'))
                    @case('invalid')
                        Please select a valid classification level.
                        @break
                    @case('failed')
                        Failed to update clearance. Please try again.
                        @break
                    @default
                        An error occurred.
                @endswitch
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        <div class="card mb-4">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">
                    <i class="fas fa-user-edit me-2"></i>{{ $pageTitle }}
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" action="/admin/security/clearances/{{ $clearance->userId }}">
                    @method('PUT')

                    {{-- User Info (Read-only) --}}
                    <fieldset class="mb-4">
                        <legend class="h6 border-bottom pb-2 mb-3">
                            <i class="fas fa-user me-2"></i>User Information
                        </legend>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Username</label>
                                <p class="form-control-plaintext fw-bold">{{ $clearance->username }}</p>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label text-muted">Email</label>
                                <p class="form-control-plaintext">{{ $clearance->userEmail }}</p>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label text-muted">Current Clearance</label>
                            <p class="form-control-plaintext">
                                <span class="badge {{ $clearance->getBadgeClass() }} fs-6">
                                    <i class="{{ $clearance->classificationIcon ?? 'fa-lock' }} me-1"></i>
                                    {{ $clearance->classificationName }}
                                </span>
                                @if($clearance->isExpired())
                                    <span class="badge bg-danger ms-2">Expired</span>
                                @elseif($clearance->expiresWithinDays(30))
                                    <span class="badge bg-warning text-dark ms-2">Expiring Soon</span>
                                @endif
                            </p>
                        </div>
                    </fieldset>

                    {{-- Classification Selection --}}
                    <fieldset class="mb-4">
                        <legend class="h6 border-bottom pb-2 mb-3">
                            <i class="fas fa-shield-alt me-2"></i>Update Classification
                        </legend>
                        
                        <div class="mb-3">
                            <label for="classification_id" class="form-label">
                                New Clearance Level <span class="text-danger">*</span>
                            </label>
                            <select name="classification_id" id="classification_id" class="form-select" required>
                                @foreach($classifications as $id => $name)
                                    <option value="{{ $id }}" {{ $clearance->classificationId == $id ? 'selected' : '' }}>
                                        {{ $name }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                    </fieldset>

                    {{-- Expiry & Notes --}}
                    <fieldset class="mb-4">
                        <legend class="h6 border-bottom pb-2 mb-3">
                            <i class="fas fa-calendar-alt me-2"></i>Validity Period
                        </legend>
                        
                        <div class="mb-3">
                            <label for="expires_at" class="form-label">Expiry Date</label>
                            <input type="date" name="expires_at" id="expires_at" class="form-control"
                                   value="{{ $clearance->expiresAt ? date('Y-m-d', strtotime($clearance->expiresAt)) : '' }}"
                                   min="{{ date('Y-m-d', strtotime('+1 day')) }}">
                            <div class="form-text">
                                Leave empty for no expiry.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="notes" class="form-label">Notes</label>
                            <textarea name="notes" id="notes" class="form-control" rows="3"
                                      placeholder="Reason for update...">{{ $clearance->notes }}</textarea>
                        </div>
                    </fieldset>

                    {{-- Actions --}}
                    <div class="d-flex justify-content-between border-top pt-3">
                        <a href="/admin/security/clearances" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-1"></i>Back to List
                        </a>
                        <div>
                            <button type="button" class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#revokeModal">
                                <i class="fas fa-times me-1"></i>Revoke
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Update Clearance
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- Clearance History --}}
        @if(!empty($history))
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">
                        <i class="fas fa-history me-2"></i>Clearance History
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Action</th>
                                    <th>Previous Level</th>
                                    <th>New Level</th>
                                    <th>Changed By</th>
                                    <th>Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($history as $record)
                                    <tr>
                                        <td>{{ date('Y-m-d H:i', strtotime($record['created_at'])) }}</td>
                                        <td>
                                            <span class="badge {{ $record['action'] == 'revoked' ? 'bg-danger' : ($record['action'] == 'granted' ? 'bg-success' : 'bg-info') }}">
                                                {{ ucfirst($record['action']) }}
                                            </span>
                                        </td>
                                        <td>{{ $record['previous_name'] ?? '-' }}</td>
                                        <td>{{ $record['new_name'] ?? '-' }}</td>
                                        <td>{{ $record['changed_by_username'] ?? 'System' }}</td>
                                        <td>{{ $record['reason'] ?? '-' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
</div>

{{-- Revoke Modal --}}
<div class="modal fade" id="revokeModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/admin/security/clearances/{{ $clearance->userId }}">
                @method('DELETE')
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-exclamation-triangle me-2"></i>Revoke Clearance
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to revoke the security clearance for <strong>{{ $clearance->username }}</strong>?</p>
                    <p class="text-danger mb-3">
                        <i class="fas fa-exclamation-circle me-1"></i>
                        This will immediately remove all elevated access for this user.
                    </p>
                    <div class="mb-3">
                        <label class="form-label">Reason for revocation <span class="text-danger">*</span></label>
                        <textarea name="reason" class="form-control" rows="2" required 
                                  placeholder="Enter reason for revocation..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times me-1"></i>Revoke Clearance
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .bg-orange { background-color: #fd7e14 !important; }
    .bg-purple { background-color: #6f42c1 !important; }
</style>
@endpush
