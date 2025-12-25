{{-- Grant Security Clearance Form - Mimics AtoM 2.10 --}}
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
                <li class="breadcrumb-item active">Grant Clearance</li>
            </ol>
        </nav>

        <div class="card">
            <div class="card-header bg-success text-white">
                <h5 class="mb-0">
                    <i class="fas fa-user-plus me-2"></i>{{ $pageTitle }}
                </h5>
            </div>
            <div class="card-body">
                @if(request()->get('error'))
                    <div class="alert alert-danger">
                        @switch(request()->get('error'))
                            @case('invalid')
                                Please select both a user and a classification level.
                                @break
                            @case('failed')
                                Failed to grant clearance. Please try again.
                                @break
                            @default
                                An error occurred.
                        @endswitch
                    </div>
                @endif

                @if(empty($users))
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        All users already have security clearances assigned.
                        <a href="/admin/security/clearances" class="alert-link">View existing clearances</a>
                    </div>
                @else
                    <form method="POST" action="/admin/security/clearances/grant">
                        {{-- User Selection --}}
                        <fieldset class="mb-4">
                            <legend class="h6 border-bottom pb-2 mb-3">
                                <i class="fas fa-user me-2"></i>Select User
                            </legend>
                            
                            <div class="mb-3">
                                <label for="user_id" class="form-label">User <span class="text-danger">*</span></label>
                                <select name="user_id" id="user_id" class="form-select" required>
                                    <option value="">-- Select User --</option>
                                    @foreach($users as $user)
                                        <option value="{{ $user['id'] }}">
                                            {{ $user['username'] }} ({{ $user['email'] }})
                                        </option>
                                    @endforeach
                                </select>
                                <div class="form-text">
                                    Only users without existing clearances are shown.
                                </div>
                            </div>
                        </fieldset>

                        {{-- Classification Selection --}}
                        <fieldset class="mb-4">
                            <legend class="h6 border-bottom pb-2 mb-3">
                                <i class="fas fa-shield-alt me-2"></i>Security Classification
                            </legend>
                            
                            <div class="mb-3">
                                <label for="classification_id" class="form-label">
                                    Clearance Level <span class="text-danger">*</span>
                                </label>
                                <select name="classification_id" id="classification_id" class="form-select" required>
                                    @foreach($classifications as $id => $name)
                                        <option value="{{ $id }}">{{ $name }}</option>
                                    @endforeach
                                </select>
                                <div class="form-text">
                                    User will be able to access documents at this level and below.
                                </div>
                            </div>

                            {{-- Classification Level Guide --}}
                            <div class="alert alert-light border">
                                <h6 class="alert-heading"><i class="fas fa-info-circle me-2"></i>Classification Levels</h6>
                                <ul class="mb-0 small">
                                    <li><span class="badge bg-success">Public</span> - Can view public documents only</li>
                                    <li><span class="badge bg-info">Internal</span> - Can view internal and public documents</li>
                                    <li><span class="badge bg-warning text-dark">Restricted</span> - Can view restricted, internal, and public</li>
                                    <li><span class="badge bg-orange text-white">Confidential</span> - Can view confidential and below</li>
                                    <li><span class="badge bg-danger">Secret</span> - Can view secret and below</li>
                                    <li><span class="badge bg-purple text-white">Top Secret</span> - Can view all documents</li>
                                </ul>
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
                                       min="{{ date('Y-m-d', strtotime('+1 day')) }}">
                                <div class="form-text">
                                    Leave empty for no expiry. Clearance will be automatically revoked on this date.
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="notes" class="form-label">Notes</label>
                                <textarea name="notes" id="notes" class="form-control" rows="3"
                                          placeholder="Enter any relevant notes about this clearance grant..."></textarea>
                            </div>
                        </fieldset>

                        {{-- Actions --}}
                        <div class="d-flex justify-content-between border-top pt-3">
                            <a href="/admin/security/clearances" class="btn btn-secondary">
                                <i class="fas fa-times me-1"></i>Cancel
                            </a>
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-check me-1"></i>Grant Clearance
                            </button>
                        </div>
                    </form>
                @endif
            </div>
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
