@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">
            <i class="fas fa-user-shield me-2"></i>{{ $pageTitle }}
        </h1>
        <div class="btn-group">
            <a href="{{ route('privacy.ropa.index') }}" class="btn btn-outline-primary">
                <i class="fas fa-list-alt me-1"></i>ROPA
            </a>
            <a href="{{ route('privacy.dsar.index') }}" class="btn btn-outline-info">
                <i class="fas fa-user-clock me-1"></i>DSARs
            </a>
            <a href="{{ route('privacy.breaches.index') }}" class="btn btn-outline-danger">
                <i class="fas fa-exclamation-circle me-1"></i>Breaches
            </a>
            <a href="{{ route('privacy.export') }}" class="btn btn-outline-secondary">
                <i class="fas fa-download me-1"></i>Export
            </a>
        </div>
    </div>

    {{-- Compliance Score Card --}}
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card h-100">
                <div class="card-body text-center">
                    <h5 class="card-title text-muted">Overall Compliance Score</h5>
                    <div class="position-relative d-inline-block my-3">
                        <div class="compliance-score-circle" 
                             style="--score: {{ $complianceScore['percentage'] }}; --color: {{ $complianceScore['percentage'] >= 75 ? '#198754' : ($complianceScore['percentage'] >= 50 ? '#ffc107' : '#dc3545') }}">
                            <span class="score-value">{{ $complianceScore['percentage'] }}%</span>
                        </div>
                    </div>
                    <h4 class="mb-0">
                        @php
                            $ratingColors = [
                                'Excellent' => 'success',
                                'Good' => 'success',
                                'Satisfactory' => 'warning',
                                'Needs Improvement' => 'warning',
                                'Critical' => 'danger'
                            ];
                        @endphp
                        <span class="badge bg-{{ $ratingColors[$complianceScore['rating']] ?? 'secondary' }} fs-5">
                            {{ $complianceScore['rating'] }}
                        </span>
                    </h4>
                </div>
                <div class="card-footer bg-transparent">
                    <div class="row text-center small">
                        @foreach($complianceScore['breakdown'] as $key => $item)
                        <div class="col-3">
                            <div class="text-muted text-uppercase" style="font-size: 0.7rem;">{{ strtoupper($key) }}</div>
                            <div class="fw-bold">{{ $item['score'] }}/{{ $item['max'] }}</div>
                        </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        {{-- Summary Cards --}}
        <div class="col-md-8">
            <div class="row g-3">
                <div class="col-md-6">
                    <div class="card bg-primary text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-1 text-white-50">Processing Activities</h6>
                                    <h2 class="card-title mb-0">{{ $ropaStats['total'] ?? 0 }}</h2>
                                </div>
                                <i class="fas fa-database fa-2x opacity-50"></i>
                            </div>
                            <div class="mt-2 small">
                                <span class="text-white-50">{{ $ropaStats['approved'] ?? 0 }} approved</span> |
                                <span class="text-white-50">{{ $ropaStats['pending'] ?? 0 }} pending</span>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-0">
                            <a href="{{ route('privacy.ropa.index') }}" class="text-white text-decoration-none small">
                                Manage ROPA <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card bg-info text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-1 text-white-50">Open DSARs</h6>
                                    <h2 class="card-title mb-0">{{ $dsarStats['pending'] + $dsarStats['in_progress'] }}</h2>
                                </div>
                                <i class="fas fa-user-clock fa-2x opacity-50"></i>
                            </div>
                            <div class="mt-2 small">
                                @if($dsarStats['overdue'] > 0)
                                <span class="badge bg-danger">{{ $dsarStats['overdue'] }} overdue</span>
                                @else
                                <span class="text-white-50">All on track</span>
                                @endif
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-0">
                            <a href="{{ route('privacy.dsar.index') }}" class="text-white text-decoration-none small">
                                View DSARs <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card bg-danger text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-1 text-white-50">Open Breaches</h6>
                                    <h2 class="card-title mb-0">{{ count(array_filter($recentBreaches, fn($b) => $b->status !== 'closed')) }}</h2>
                                </div>
                                <i class="fas fa-exclamation-triangle fa-2x opacity-50"></i>
                            </div>
                        </div>
                        <div class="card-footer bg-transparent border-0">
                            <a href="{{ route('privacy.breaches.index') }}" class="text-white text-decoration-none small">
                                View Breaches <i class="fas fa-arrow-right ms-1"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card bg-success text-white h-100">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="card-subtitle mb-1 text-white-50">Avg DSAR Response</h6>
                                    <h2 class="card-title mb-0">
                                        {{ $dsarStats['average_completion_days'] ?? '-' }}
                                        <small class="fs-6">days</small>
                                    </h2>
                                </div>
                                <i class="fas fa-clock fa-2x opacity-50"></i>
                            </div>
                            <div class="mt-2 small">
                                <span class="text-white-50">POPIA deadline: 30 days</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Pending DSARs --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-tasks me-2"></i>Pending DSARs
                    </h5>
                    <a href="{{ route('privacy.dsar.create') }}" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus me-1"></i>New DSAR
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse($pendingDsars as $dsar)
                        <a href="{{ route('privacy.dsar.view', ['id' => $dsar->id]) }}" 
                           class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">
                                        <code>{{ $dsar->reference_number }}</code>
                                        <span class="badge bg-{{ $dsar->priority === 'urgent' ? 'danger' : ($dsar->priority === 'high' ? 'warning' : 'secondary') }} ms-1">
                                            {{ ucfirst($dsar->priority) }}
                                        </span>
                                    </h6>
                                    <p class="mb-1 text-muted small">{{ $dsar->subject_name }}</p>
                                    <span class="badge bg-info">{{ str_replace('_', ' ', ucfirst($dsar->request_type)) }}</span>
                                </div>
                                <div class="text-end">
                                    @php
                                        $deadline = \Carbon\Carbon::parse($dsar->deadline);
                                        $daysLeft = now()->diffInDays($deadline, false);
                                        $badgeClass = $daysLeft < 0 ? 'bg-danger' : ($daysLeft <= 7 ? 'bg-warning text-dark' : 'bg-success');
                                    @endphp
                                    <span class="badge {{ $badgeClass }}">
                                        @if($daysLeft < 0)
                                            {{ abs($daysLeft) }} days overdue
                                        @else
                                            {{ $daysLeft }} days left
                                        @endif
                                    </span>
                                    <br>
                                    <small class="text-muted">Due: {{ $deadline->format('M d, Y') }}</small>
                                </div>
                            </div>
                        </a>
                        @empty
                        <div class="list-group-item text-center text-muted py-4">
                            <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                            <p class="mb-0">No pending DSARs</p>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent Breaches --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center bg-danger text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-shield-alt me-2"></i>Recent Breach Incidents
                    </h5>
                    <a href="{{ route('privacy.breaches.create') }}" class="btn btn-sm btn-light">
                        <i class="fas fa-plus me-1"></i>Report
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse($recentBreaches as $breach)
                        <a href="{{ route('privacy.breaches.view', ['id' => $breach->id]) }}" 
                           class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between align-items-start">
                                <div>
                                    <h6 class="mb-1">
                                        <code>{{ $breach->reference_number }}</code>
                                    </h6>
                                    <p class="mb-1 small">{{ Str::limit($breach->description, 80) }}</p>
                                    <span class="badge bg-{{ $breach->severity === 'critical' ? 'dark' : ($breach->severity === 'high' ? 'danger' : ($breach->severity === 'medium' ? 'warning text-dark' : 'info')) }}">
                                        {{ ucfirst($breach->severity) }}
                                    </span>
                                    @if($breach->notification_required && !$breach->regulator_notified)
                                    <span class="badge bg-danger">Notification Required</span>
                                    @endif
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-{{ $breach->status === 'closed' ? 'success' : ($breach->status === 'contained' ? 'info' : 'warning') }}">
                                        {{ ucfirst($breach->status) }}
                                    </span>
                                    <br>
                                    <small class="text-muted">
                                        {{ \Carbon\Carbon::parse($breach->incident_date)->format('M d, Y') }}
                                    </small>
                                </div>
                            </div>
                        </a>
                        @empty
                        <div class="list-group-item text-center text-muted py-4">
                            <i class="fas fa-shield-alt fa-2x mb-2 text-success"></i>
                            <p class="mb-0">No breach incidents recorded</p>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Quick Actions --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-bolt me-2"></i>Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <a href="{{ route('privacy.ropa.create') }}" class="btn btn-outline-primary w-100 py-3">
                                <i class="fas fa-plus-circle fa-2x d-block mb-2"></i>
                                Add Processing Activity
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="{{ route('privacy.dsar.create') }}" class="btn btn-outline-info w-100 py-3">
                                <i class="fas fa-user-edit fa-2x d-block mb-2"></i>
                                Log New DSAR
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="{{ route('privacy.breaches.create') }}" class="btn btn-outline-danger w-100 py-3">
                                <i class="fas fa-exclamation-triangle fa-2x d-block mb-2"></i>
                                Report Breach
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="{{ route('privacy.templates.index') }}" class="btn btn-outline-secondary w-100 py-3">
                                <i class="fas fa-file-alt fa-2x d-block mb-2"></i>
                                Privacy Templates
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- POPIA/PAIA Reference --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-gavel me-2"></i>Regulatory Quick Reference
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-primary"><i class="fas fa-book me-2"></i>POPIA Key Deadlines</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-clock text-muted me-2"></i>DSAR Response: <strong>30 days</strong></li>
                                <li><i class="fas fa-bell text-muted me-2"></i>Breach Notification to Regulator: <strong>As soon as reasonably possible</strong></li>
                                <li><i class="fas fa-user text-muted me-2"></i>Breach Notification to Subjects: <strong>As soon as reasonably possible</strong></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-success"><i class="fas fa-book me-2"></i>PAIA Key Deadlines</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-clock text-muted me-2"></i>Initial Response: <strong>30 days</strong></li>
                                <li><i class="fas fa-redo text-muted me-2"></i>Extended Response: <strong>+30 days (with notice)</strong></li>
                                <li><i class="fas fa-file-alt text-muted me-2"></i>PAIA Manual Update: <strong>Annual review recommended</strong></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.compliance-score-circle {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    background: conic-gradient(var(--color) calc(var(--score) * 1%), #e9ecef calc(var(--score) * 1%));
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
}
.compliance-score-circle::before {
    content: '';
    width: 120px;
    height: 120px;
    border-radius: 50%;
    background: white;
    position: absolute;
}
.compliance-score-circle .score-value {
    position: relative;
    font-size: 2rem;
    font-weight: bold;
    color: var(--color);
}
</style>
@endsection

@section('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Auto-refresh dashboard every 5 minutes
    setTimeout(function() {
        location.reload();
    }, 300000);
});
</script>
@endsection
