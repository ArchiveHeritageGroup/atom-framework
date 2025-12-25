@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">
            <i class="fas fa-list-alt me-2"></i>{{ $pageTitle }}
        </h1>
        <div class="btn-group">
            <a href="{{ route('privacy.ropa.create') }}" class="btn btn-primary">
                <i class="fas fa-plus me-1"></i>Add Activity
            </a>
            <a href="{{ route('privacy.export', ['format' => 'csv']) }}" class="btn btn-outline-secondary">
                <i class="fas fa-download me-1"></i>Export
            </a>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="draft" {{ $status === 'draft' ? 'selected' : '' }}>Draft</option>
                        <option value="pending_review" {{ $status === 'pending_review' ? 'selected' : '' }}>Pending Review</option>
                        <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Approved</option>
                        <option value="archived" {{ $status === 'archived' ? 'selected' : '' }}>Archived</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-1"></i>Filter
                    </button>
                </div>
            </form>
        </div>
    </div>

    {{-- ROPA Table --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Activity Name</th>
                            <th>Purpose</th>
                            <th>Lawful Basis</th>
                            <th>DPIA</th>
                            <th>Status</th>
                            <th>Last Updated</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($activities as $activity)
                        <tr>
                            <td>
                                <a href="{{ route('privacy.ropa.view', ['id' => $activity->id]) }}" class="fw-bold text-decoration-none">
                                    {{ $activity->name }}
                                </a>
                                @if($activity->responsible_person)
                                <br><small class="text-muted">{{ $activity->responsible_person }}</small>
                                @endif
                            </td>
                            <td>{{ Str::limit($activity->purpose, 60) }}</td>
                            <td>
                                @php
                                    $basisLabels = [
                                        'consent' => 'Consent',
                                        'contract' => 'Contract',
                                        'legal_obligation' => 'Legal Obligation',
                                        'vital_interests' => 'Vital Interests',
                                        'public_task' => 'Public Task',
                                        'legitimate_interests' => 'Legitimate Interests'
                                    ];
                                    $basisColors = [
                                        'consent' => 'success',
                                        'contract' => 'primary',
                                        'legal_obligation' => 'warning',
                                        'vital_interests' => 'danger',
                                        'public_task' => 'info',
                                        'legitimate_interests' => 'secondary'
                                    ];
                                @endphp
                                <span class="badge bg-{{ $basisColors[$activity->lawful_basis] ?? 'secondary' }}">
                                    {{ $basisLabels[$activity->lawful_basis] ?? $activity->lawful_basis }}
                                </span>
                                @if($activity->popia_condition)
                                <br><small class="text-muted">POPIA: {{ $activity->popia_condition }}</small>
                                @endif
                            </td>
                            <td>
                                @if($activity->dpia_required)
                                    @if($activity->dpia_completed)
                                    <span class="badge bg-success"><i class="fas fa-check me-1"></i>Completed</span>
                                    @else
                                    <span class="badge bg-danger"><i class="fas fa-exclamation me-1"></i>Required</span>
                                    @endif
                                @else
                                <span class="badge bg-secondary">Not Required</span>
                                @endif
                            </td>
                            <td>
                                @php
                                    $statusColors = [
                                        'draft' => 'secondary',
                                        'pending_review' => 'warning',
                                        'approved' => 'success',
                                        'archived' => 'dark'
                                    ];
                                @endphp
                                <span class="badge bg-{{ $statusColors[$activity->status] ?? 'secondary' }}">
                                    {{ str_replace('_', ' ', ucfirst($activity->status)) }}
                                </span>
                            </td>
                            <td>
                                <small>{{ $activity->updated_at ? \Carbon\Carbon::parse($activity->updated_at)->format('Y-m-d') : '-' }}</small>
                            </td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('privacy.ropa.view', ['id' => $activity->id]) }}" 
                                       class="btn btn-outline-secondary" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="{{ route('privacy.ropa.edit', ['id' => $activity->id]) }}" 
                                       class="btn btn-outline-primary" title="Edit">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <i class="fas fa-database fa-3x text-muted mb-3"></i>
                                <p class="text-muted mb-3">No processing activities recorded</p>
                                <a href="{{ route('privacy.ropa.create') }}" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i>Add First Activity
                                </a>
                            </td>
                        </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($total > $perPage)
        <div class="card-footer">
            <nav>
                <ul class="pagination pagination-sm mb-0 justify-content-center">
                    @for($i = 1; $i <= ceil($total / $perPage); $i++)
                    <li class="page-item {{ $page == $i ? 'active' : '' }}">
                        <a class="page-link" href="?page={{ $i }}&status={{ $status }}">{{ $i }}</a>
                    </li>
                    @endfor
                </ul>
            </nav>
        </div>
        @endif
    </div>

    {{-- Help Info --}}
    <div class="card mt-4">
        <div class="card-header">
            <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>About ROPA</h6>
        </div>
        <div class="card-body small">
            <p class="mb-2">
                The <strong>Record of Processing Activities (ROPA)</strong> is required under POPIA Section 14 
                and GDPR Article 30 for organizations processing personal information.
            </p>
            <p class="mb-0">
                Each processing activity should document: the purpose of processing, categories of data subjects, 
                types of personal data, recipients, retention periods, and security measures.
            </p>
        </div>
    </div>
</div>
@endsection
