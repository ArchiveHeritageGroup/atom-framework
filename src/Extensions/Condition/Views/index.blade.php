@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
<div class="container-fluid px-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h2">
            <i class="fas fa-clipboard-check me-2"></i>{{ $pageTitle }}
        </h1>
        <div class="btn-group">
            <a href="{{ route('condition.risk') }}" class="btn btn-outline-warning">
                <i class="fas fa-exclamation-triangle me-1"></i>Risk Assessment
            </a>
            <a href="{{ route('condition.schedule') }}" class="btn btn-outline-info">
                <i class="fas fa-calendar-check me-1"></i>Schedule
            </a>
            <a href="{{ route('condition.vocabularies') }}" class="btn btn-outline-secondary">
                <i class="fas fa-list me-1"></i>Vocabularies
            </a>
        </div>
    </div>

    {{-- Summary Cards --}}
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card bg-success text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1 text-white-50">Good Condition</h6>
                            <h2 class="card-title mb-0">{{ $dashboard['good_count'] ?? 0 }}</h2>
                        </div>
                        <i class="fas fa-check-circle fa-2x opacity-50"></i>
                    </div>
                    <div class="mt-2">
                        <small>{{ $dashboard['good_percentage'] ?? 0 }}% of collection</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-warning text-dark h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1 text-dark-50">Fair Condition</h6>
                            <h2 class="card-title mb-0">{{ $dashboard['fair_count'] ?? 0 }}</h2>
                        </div>
                        <i class="fas fa-minus-circle fa-2x opacity-50"></i>
                    </div>
                    <div class="mt-2">
                        <small>{{ $dashboard['fair_percentage'] ?? 0 }}% of collection</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-danger text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1 text-white-50">Poor Condition</h6>
                            <h2 class="card-title mb-0">{{ $dashboard['poor_count'] ?? 0 }}</h2>
                        </div>
                        <i class="fas fa-times-circle fa-2x opacity-50"></i>
                    </div>
                    <div class="mt-2">
                        <small>{{ $dashboard['poor_percentage'] ?? 0 }}% of collection</small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card bg-info text-white h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="card-subtitle mb-1 text-white-50">Treatments This Month</h6>
                            <h2 class="card-title mb-0">{{ $dashboard['treatments_this_month'] ?? 0 }}</h2>
                        </div>
                        <i class="fas fa-tools fa-2x opacity-50"></i>
                    </div>
                    <div class="mt-2">
                        <small>Conservation actions</small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">
        {{-- Priority Items --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center bg-danger text-white">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-exclamation-triangle me-2"></i>Priority Items (High Risk)
                    </h5>
                    <a href="{{ route('condition.risk') }}" class="btn btn-sm btn-light">
                        View All
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse($priorities as $item)
                        <a href="/{{ $item->slug }}/condition" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">{{ $item->title ?? $item->identifier }}</h6>
                                    <small class="text-muted">{{ $item->identifier }}</small>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-danger fs-6">{{ number_format($item->risk_score, 1) }}</span>
                                    <br>
                                    <small class="text-muted">Risk Score</small>
                                </div>
                            </div>
                            @if($item->last_condition)
                            <div class="mt-2">
                                <span class="badge bg-secondary">{{ $item->last_condition }}</span>
                                @if($item->damage_type)
                                <span class="badge bg-warning text-dark">{{ $item->damage_type }}</span>
                                @endif
                            </div>
                            @endif
                        </a>
                        @empty
                        <div class="list-group-item text-center text-muted py-4">
                            <i class="fas fa-check-circle fa-2x mb-2 text-success"></i>
                            <p class="mb-0">No high-risk items</p>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Upcoming Assessments --}}
        <div class="col-lg-6">
            <div class="card h-100">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-calendar-alt me-2"></i>Upcoming Assessments (30 Days)
                    </h5>
                    <a href="{{ route('condition.schedule') }}" class="btn btn-sm btn-outline-primary">
                        Full Schedule
                    </a>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        @forelse($upcomingAssessments as $assessment)
                        <a href="/{{ $assessment->slug }}/condition/new" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-1">{{ $assessment->title ?? $assessment->identifier }}</h6>
                                    <small class="text-muted">{{ $assessment->identifier }}</small>
                                </div>
                                <div class="text-end">
                                    @php
                                        $daysUntil = \Carbon\Carbon::parse($assessment->next_assessment_date)->diffInDays(now());
                                        $badgeClass = $daysUntil <= 7 ? 'bg-danger' : ($daysUntil <= 14 ? 'bg-warning text-dark' : 'bg-info');
                                    @endphp
                                    <span class="badge {{ $badgeClass }}">
                                        {{ \Carbon\Carbon::parse($assessment->next_assessment_date)->format('M d') }}
                                    </span>
                                    <br>
                                    <small class="text-muted">
                                        {{ $daysUntil }} day{{ $daysUntil != 1 ? 's' : '' }}
                                    </small>
                                </div>
                            </div>
                            <div class="mt-1">
                                <small class="badge bg-secondary">{{ $assessment->frequency ?? 'annual' }}</small>
                            </div>
                        </a>
                        @empty
                        <div class="list-group-item text-center text-muted py-4">
                            <i class="fas fa-calendar-check fa-2x mb-2"></i>
                            <p class="mb-0">No assessments scheduled</p>
                        </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent Condition Events --}}
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-history me-2"></i>Recent Condition Events
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Object</th>
                                    <th>Event Type</th>
                                    <th>Condition</th>
                                    <th>Severity</th>
                                    <th>Assessor</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse($recentEvents as $event)
                                <tr>
                                    <td>
                                        <small>{{ \Carbon\Carbon::parse($event->event_date)->format('Y-m-d') }}</small>
                                    </td>
                                    <td>
                                        <a href="/{{ $event->slug }}/condition">
                                            {{ $event->title ?? $event->identifier }}
                                        </a>
                                    </td>
                                    <td>
                                        @switch($event->event_type)
                                            @case('condition_check')
                                                <span class="badge bg-primary">Condition Check</span>
                                                @break
                                            @case('conservation_treatment')
                                                <span class="badge bg-success">Treatment</span>
                                                @break
                                            @case('damage_report')
                                                <span class="badge bg-danger">Damage Report</span>
                                                @break
                                            @case('loan_inspection')
                                                <span class="badge bg-info">Loan Inspection</span>
                                                @break
                                            @default
                                                <span class="badge bg-secondary">{{ $event->event_type }}</span>
                                        @endswitch
                                    </td>
                                    <td>
                                        @if($event->overall_condition)
                                            @php
                                                $conditionColors = [
                                                    'excellent' => 'success',
                                                    'good' => 'success',
                                                    'fair' => 'warning',
                                                    'poor' => 'danger',
                                                    'critical' => 'dark',
                                                ];
                                                $color = $conditionColors[$event->overall_condition] ?? 'secondary';
                                            @endphp
                                            <span class="badge bg-{{ $color }}">{{ ucfirst($event->overall_condition) }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($event->severity)
                                            @php
                                                $severityColors = [
                                                    'none' => 'light',
                                                    'minor' => 'info',
                                                    'moderate' => 'warning',
                                                    'severe' => 'danger',
                                                    'critical' => 'dark',
                                                ];
                                                $sevColor = $severityColors[$event->severity] ?? 'secondary';
                                            @endphp
                                            <span class="badge bg-{{ $sevColor }}">{{ ucfirst($event->severity) }}</span>
                                        @else
                                            <span class="text-muted">-</span>
                                        @endif
                                    </td>
                                    <td>{{ $event->assessor_username ?? 'Unknown' }}</td>
                                    <td>
                                        <a href="/{{ $event->slug }}/condition/{{ $event->id }}" 
                                           class="btn btn-sm btn-outline-secondary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">
                                        <i class="fas fa-clipboard fa-2x mb-2"></i>
                                        <p class="mb-0">No recent condition events</p>
                                    </td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        {{-- Condition Distribution Chart --}}
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-pie me-2"></i>Condition Distribution
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="conditionChart" height="200"></canvas>
                </div>
            </div>
        </div>

        {{-- Damage Type Distribution --}}
        <div class="col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-chart-bar me-2"></i>Damage Types
                    </h5>
                </div>
                <div class="card-body">
                    <canvas id="damageChart" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Condition Distribution Chart
    const conditionCtx = document.getElementById('conditionChart');
    if (conditionCtx) {
        new Chart(conditionCtx, {
            type: 'doughnut',
            data: {
                labels: ['Excellent', 'Good', 'Fair', 'Poor', 'Critical'],
                datasets: [{
                    data: [
                        {{ $dashboard['excellent_count'] ?? 0 }},
                        {{ $dashboard['good_count'] ?? 0 }},
                        {{ $dashboard['fair_count'] ?? 0 }},
                        {{ $dashboard['poor_count'] ?? 0 }},
                        {{ $dashboard['critical_count'] ?? 0 }}
                    ],
                    backgroundColor: [
                        '#198754',
                        '#20c997',
                        '#ffc107',
                        '#dc3545',
                        '#212529'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'right'
                    }
                }
            }
        });
    }

    // Damage Type Chart
    const damageCtx = document.getElementById('damageChart');
    if (damageCtx) {
        new Chart(damageCtx, {
            type: 'bar',
            data: {
                labels: ['Physical', 'Biological', 'Chemical', 'Water', 'Fire', 'Other'],
                datasets: [{
                    label: 'Reported Cases',
                    data: [
                        {{ $dashboard['physical_damage'] ?? 0 }},
                        {{ $dashboard['biological_damage'] ?? 0 }},
                        {{ $dashboard['chemical_damage'] ?? 0 }},
                        {{ $dashboard['water_damage'] ?? 0 }},
                        {{ $dashboard['fire_damage'] ?? 0 }},
                        {{ $dashboard['other_damage'] ?? 0 }}
                    ],
                    backgroundColor: [
                        'rgba(13, 110, 253, 0.7)',
                        'rgba(25, 135, 84, 0.7)',
                        'rgba(111, 66, 193, 0.7)',
                        'rgba(13, 202, 240, 0.7)',
                        'rgba(220, 53, 69, 0.7)',
                        'rgba(108, 117, 125, 0.7)'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }
});
</script>
@endsection
