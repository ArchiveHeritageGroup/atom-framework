@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">
            <i class="fas fa-user-clock me-2"></i>{{ $pageTitle }}
        </h1>
        <a href="{{ route('privacy.dsar.create') }}" class="btn btn-primary">
            <i class="fas fa-plus me-1"></i>New DSAR
        </a>
    </div>

    {{-- Status Summary --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-warning text-dark">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Pending</h6>
                            <h3 class="mb-0">{{ $pendingCount }}</h3>
                        </div>
                        <i class="fas fa-hourglass-start fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-danger text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Overdue</h6>
                            <h3 class="mb-0">{{ $overdueCount }}</h3>
                        </div>
                        <i class="fas fa-exclamation-triangle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">In Progress</h6>
                            <h3 class="mb-0">{{ collect($requests)->where('status', 'in_progress')->count() }}</h3>
                        </div>
                        <i class="fas fa-spinner fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-0">Total Requests</h6>
                            <h3 class="mb-0">{{ $total }}</h3>
                        </div>
                        <i class="fas fa-check-circle fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Filters --}}
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Statuses</option>
                        <option value="pending" {{ $status === 'pending' ? 'selected' : '' }}>Pending</option>
                        <option value="in_progress" {{ $status === 'in_progress' ? 'selected' : '' }}>In Progress</option>
                        <option value="awaiting_info" {{ $status === 'awaiting_info' ? 'selected' : '' }}>Awaiting Info</option>
                        <option value="completed" {{ $status === 'completed' ? 'selected' : '' }}>Completed</option>
                        <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Rejected</option>
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

    {{-- DSAR Table --}}
    <div class="card">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Reference</th>
                            <th>Subject</th>
                            <th>Type</th>
                            <th>Priority</th>
                            <th>Status</th>
                            <th>Deadline</th>
                            <th>Assigned To</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($requests as $dsar)
                        @php
                            $deadline = \Carbon\Carbon::parse($dsar->deadline);
                            $daysLeft = now()->diffInDays($deadline, false);
                            $isOverdue = $daysLeft < 0 && !in_array($dsar->status, ['completed', 'rejected', 'withdrawn']);
                        @endphp
                        <tr class="{{ $isOverdue ? 'table-danger' : '' }}">
                            <td>
                                <a href="{{ route('privacy.dsar.view', ['id' => $dsar->id]) }}" class="fw-bold text-decoration-none">
                                    {{ $dsar->reference_number }}
                                </a>
                            </td>
                            <td>
                                {{ $dsar->subject_name }}
                                @if($dsar->subject_email)
                                <br><small class="text-muted">{{ $dsar->subject_email }}</small>
                                @endif
                            </td>
                            <td>
                                @php
                                    $typeLabels = [
                                        'access' => 'Access',
                                        'rectification' => 'Rectification',
                                        'erasure' => 'Erasure',
                                        'restriction' => 'Restriction',
                                        'portability' => 'Portability',
                                        'objection' => 'Objection'
                                    ];
                                    $typeColors = [
                                        'access' => 'primary',
                                        'rectification' => 'info',
                                        'erasure' => 'danger',
                                        'restriction' => 'warning',
                                        'portability' => 'success',
                                        'objection' => 'dark'
                                    ];
                                @endphp
                                <span class="badge bg-{{ $typeColors[$dsar->request_type] ?? 'secondary' }}">
                                    {{ $typeLabels[$dsar->request_type] ?? $dsar->request_type }}
                                </span>
                            </td>
                            <td>
                                @php
                                    $priorityColors = [
                                        'low' => 'secondary',
                                        'normal' => 'primary',
                                        'high' => 'warning',
                                        'urgent' => 'danger'
                                    ];
                                @endphp
                                <span class="badge bg-{{ $priorityColors[$dsar->priority] ?? 'secondary' }}">
                                    {{ ucfirst($dsar->priority) }}
                                </span>
                            </td>
                            <td>
                                @php
                                    $statusColors = [
                                        'pending' => 'warning',
                                        'in_progress' => 'info',
                                        'awaiting_info' => 'secondary',
                                        'completed' => 'success',
                                        'rejected' => 'danger',
                                        'withdrawn' => 'dark'
                                    ];
                                @endphp
                                <span class="badge bg-{{ $statusColors[$dsar->status] ?? 'secondary' }}">
                                    {{ str_replace('_', ' ', ucfirst($dsar->status)) }}
                                </span>
                            </td>
                            <td>
                                @if($isOverdue)
                                <span class="text-danger fw-bold">
                                    <i class="fas fa-exclamation-circle me-1"></i>
                                    {{ abs($daysLeft) }} days overdue
                                </span>
                                @elseif($dsar->status === 'completed')
                                <span class="text-success">
                                    <i class="fas fa-check me-1"></i>Completed
                                </span>
                                @else
                                <span class="{{ $daysLeft <= 7 ? 'text-warning fw-bold' : '' }}">
                                    {{ $deadline->format('M d, Y') }}
                                    <br><small>{{ $daysLeft }} days left</small>
                                </span>
                                @endif
                            </td>
                            <td>{{ $dsar->assigned_username ?? '-' }}</td>
                            <td class="text-end">
                                <div class="btn-group btn-group-sm">
                                    <a href="{{ route('privacy.dsar.view', ['id' => $dsar->id]) }}" 
                                       class="btn btn-outline-secondary" title="View">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                        @empty
                        <tr>
                            <td colspan="8" class="text-center py-5">
                                <i class="fas fa-user-check fa-3x text-muted mb-3"></i>
                                <p class="text-muted mb-3">No data subject access requests</p>
                                <a href="{{ route('privacy.dsar.create') }}" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i>Log First DSAR
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

    {{-- POPIA Reference --}}
    <div class="alert alert-info mt-4">
        <i class="fas fa-info-circle me-2"></i>
        <strong>POPIA Requirement:</strong> Data subject requests must be responded to within <strong>30 days</strong> 
        of receipt. Extensions may be granted for complex requests but subjects must be notified.
    </div>
</div>
@endsection
