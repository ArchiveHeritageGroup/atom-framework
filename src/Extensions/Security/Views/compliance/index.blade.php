@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">
            <i class="fas fa-shield-alt me-2"></i>{{ $pageTitle }}
        </h1>
        <div class="btn-group">
            <a href="{{ route('security.compliance.report') }}" class="btn btn-outline-primary">
                <i class="fas fa-file-alt me-1"></i>Full Report
            </a>
            <a href="{{ route('security.compliance.export', ['format' => 'json']) }}" class="btn btn-outline-secondary">
                <i class="fas fa-download me-1"></i>Export
            </a>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1 text-white-50">Classified Objects</h6>
                            <h2 class="card-title mb-0">{{ $report['summary']['total_classified'] ?? 0 }}</h2>
                        </div>
                        <i class="fas fa-lock fa-2x opacity-50"></i>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="{{ route('security.objects') }}" class="text-white text-decoration-none small">
                        View all <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-warning text-dark h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1 text-dark-50">Pending Reviews</h6>
                            <h2 class="card-title mb-0">{{ $pendingReviews }}</h2>
                        </div>
                        <i class="fas fa-clock fa-2x opacity-50"></i>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="{{ route('security.compliance.reviews') }}" class="text-dark text-decoration-none small">
                        Review now <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1 text-white-50">Upcoming Declassifications</h6>
                            <h2 class="card-title mb-0">{{ $upcomingDeclassifications }}</h2>
                        </div>
                        <i class="fas fa-unlock fa-2x opacity-50"></i>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="{{ route('security.compliance.declassification') }}" class="text-white text-decoration-none small">
                        View schedule <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1 text-white-50">Active Clearances</h6>
                            <h2 class="card-title mb-0">{{ $report['summary']['users_with_clearance'] ?? 0 }}</h2>
                        </div>
                        <i class="fas fa-user-shield fa-2x opacity-50"></i>
                    </div>
                </div>
                <div class="card-footer bg-transparent border-0">
                    <a href="{{ route('security.users') }}" class="text-white text-decoration-none small">
                        Manage users <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Retention Schedules --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>Retention Schedules
                    </h5>
                    <a href="{{ route('security.compliance.retention') }}" class="btn btn-sm btn-outline-primary">
                        Manage
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Classification</th>
                                    <th>Retention</th>
                                    <th>Action</th>
                                    <th>NARSSA Ref</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($retentionSchedules as $schedule)
                                <tr>
                                    <td>
                                        <span class="badge bg-secondary">{{ $schedule->classification_code }}</span>
                                        {{ $schedule->classification_name }}
                                    </td>
                                    <td>{{ $schedule->retention_years }} years</td>
                                    <td>
                                        @if($schedule->action === 'declassify')
                                            <span class="text-info">Declassify to {{ $schedule->declassify_to_code }}</span>
                                        @elseif($schedule->action === 'destroy')
                                            <span class="text-danger">Destroy</span>
                                        @else
                                            <span class="text-success">Archive</span>
                                        @endif
                                    </td>
                                    <td><code>{{ $schedule->narssa_reference ?? 'N/A' }}</code></td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <i class="fas fa-inbox fa-2x mb-2 d-block"></i>
                                        No retention schedules configured
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Classification Access Matrix --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-th me-2"></i>Classification Access Matrix
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Level</th>
                                    <th>Objects</th>
                                    <th>Users</th>
                                    <th>Coverage</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($report['classification_access'] ?? [] as $access)
                                <tr>
                                    <td>
                                        <span class="badge" style="background-color: {{ $access['classification']['color'] ?? '#6c757d' }}">
                                            {{ $access['classification']['code'] }}
                                        </span>
                                        {{ $access['classification']['name'] }}
                                    </td>
                                    <td>{{ number_format($access['object_count']) }}</td>
                                    <td>{{ number_format($access['user_count']) }}</td>
                                    <td>
                                        @php
                                            $coverage = $access['object_count'] > 0 && $access['user_count'] > 0 ? 100 : 0;
                                        @endphp
                                        <div class="progress" style="height: 6px;">
                                            <div class="progress-bar bg-success" style="width: {{ $coverage }}%"></div>
                                        </div>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        No classification data available
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent Compliance Actions --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history me-2"></i>Recent Compliance Actions
                    </h5>
                    <div class="btn-group btn-group-sm">
                        <a href="{{ route('security.compliance.accessLogs') }}" class="btn btn-outline-secondary">
                            Access Logs
                        </a>
                        <a href="{{ route('security.compliance.clearanceLogs') }}" class="btn btn-outline-secondary">
                            Clearance Logs
                        </a>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date/Time</th>
                                    <th>Action</th>
                                    <th>User</th>
                                    <th>Details</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentActions as $action)
                                <tr>
                                    <td>
                                        <small>{{ \Carbon\Carbon::parse($action->created_at)->format('Y-m-d H:i') }}</small>
                                    </td>
                                    <td>
                                        @switch($action->action)
                                            @case('classified')
                                                <span class="badge bg-primary">Classified</span>
                                                @break
                                            @case('declassified')
                                                <span class="badge bg-success">Declassified</span>
                                                @break
                                            @case('clearance_granted')
                                                <span class="badge bg-info">Clearance Granted</span>
                                                @break
                                            @case('access_logs_exported')
                                                <span class="badge bg-secondary">Logs Exported</span>
                                                @break
                                            @default
                                                <span class="badge bg-light text-dark">{{ $action->action }}</span>
                                        @endswitch
                                    </td>
                                    <td>{{ $action->username ?? 'System' }}</td>
                                    <td>
                                        @if($action->details)
                                            @php $details = json_decode($action->details, true); @endphp
                                            <small class="text-muted">
                                                @if(isset($details['classification_id']))
                                                    Classification ID: {{ $details['classification_id'] }}
                                                @elseif(isset($details['record_count']))
                                                    Records: {{ $details['record_count'] }}
                                                @endif
                                            </small>
                                        @endif
                                    </td>
                                    <td><code class="small">{{ $action->ip_address ?? '-' }}</code></td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        No recent actions
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
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
