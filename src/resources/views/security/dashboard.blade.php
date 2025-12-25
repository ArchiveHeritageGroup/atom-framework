{{-- Security Dashboard - Admin Overview - Mimics AtoM 2.10 --}}
@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
<div class="row">
    <div class="col-12">
        {{-- Page Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="fas fa-shield-alt me-2"></i>{{ $pageTitle }}
            </h1>
            <div>
                <a href="/admin/security/clearances" class="btn btn-outline-primary me-2">
                    <i class="fas fa-users me-1"></i>User Clearances
                </a>
                <a href="/admin/security/objects" class="btn btn-outline-primary">
                    <i class="fas fa-file-alt me-1"></i>Classified Objects
                </a>
            </div>
        </div>

        {{-- Statistics Cards --}}
        <div class="row mb-4">
            {{-- Users by Level --}}
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-users me-2"></i>Users by Clearance Level
                        </h5>
                    </div>
                    <div class="card-body">
                        @if(!empty($stats['users_by_level']))
                            <div class="row text-center">
                                @foreach($stats['users_by_level'] as $level)
                                    <div class="col">
                                        <div class="p-3 rounded" style="background-color: {{ $level->color }}20;">
                                            <h2 class="mb-0" style="color: {{ $level->color }};">{{ $level->count }}</h2>
                                            <small class="text-muted">{{ $level->name }}</small>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-muted text-center mb-0">No user clearances assigned.</p>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Objects by Level --}}
            <div class="col-lg-6 mb-4">
                <div class="card h-100">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0">
                            <i class="fas fa-file-alt me-2"></i>Objects by Classification
                        </h5>
                    </div>
                    <div class="card-body">
                        @if(!empty($stats['objects_by_level']))
                            <div class="row text-center">
                                @foreach($stats['objects_by_level'] as $level)
                                    <div class="col">
                                        <div class="p-3 rounded" style="background-color: {{ $level->color }}20;">
                                            <h2 class="mb-0" style="color: {{ $level->color }};">{{ $level->count }}</h2>
                                            <small class="text-muted">{{ $level->name }}</small>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-muted text-center mb-0">No classified objects.</p>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Alert Cards --}}
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card border-warning">
                    <div class="card-body d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-clock fa-3x text-warning"></i>
                        </div>
                        <div>
                            <h3 class="mb-0">{{ $stats['pending_reviews'] }}</h3>
                            <p class="text-muted mb-0">Objects Due for Review</p>
                        </div>
                        <a href="/admin/security/reviews" class="btn btn-warning ms-auto">View</a>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card border-danger">
                    <div class="card-body d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-user-clock fa-3x text-danger"></i>
                        </div>
                        <div>
                            <h3 class="mb-0">{{ $stats['expiring_clearances'] }}</h3>
                            <p class="text-muted mb-0">Clearances Expiring (30 days)</p>
                        </div>
                        <a href="/admin/security/clearances?expiring=1" class="btn btn-danger ms-auto">View</a>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            {{-- Expiring Clearances --}}
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-user-clock me-2"></i>Expiring Clearances
                        </h5>
                        <a href="/admin/security/clearances?expiring=1" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        @if(!empty($expiringClearances))
                            <div class="list-group list-group-flush">
                                @foreach($expiringClearances as $clearance)
                                    <a href="/admin/security/clearances/{{ $clearance->user_id }}/edit" 
                                       class="list-group-item list-group-item-action">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>{{ $clearance->username }}</strong>
                                                <span class="badge bg-warning text-dark ms-2">{{ $clearance->classification_name }}</span>
                                            </div>
                                            <small class="text-danger">
                                                Expires: {{ date('Y-m-d', strtotime($clearance->expires_at)) }}
                                            </small>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <p class="mb-0">No clearances expiring soon.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Pending Reviews --}}
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header bg-light d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-clipboard-check me-2"></i>Pending Reviews
                        </h5>
                        <a href="/admin/security/reviews" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body p-0">
                        @if(!empty($pendingReviews))
                            <div class="list-group list-group-flush">
                                @foreach($pendingReviews as $review)
                                    <a href="/{{ $review['slug'] ?? $review['object_id'] }}/security" 
                                       class="list-group-item list-group-item-action">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong>{{ $review['object_title'] ?? 'Untitled' }}</strong>
                                                <span class="badge bg-secondary ms-2">{{ $review['classification_name'] }}</span>
                                            </div>
                                            <small class="text-warning">
                                                Due: {{ date('Y-m-d', strtotime($review['review_date'])) }}
                                            </small>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @else
                            <div class="text-center py-4 text-muted">
                                <i class="fas fa-check-circle fa-2x mb-2"></i>
                                <p class="mb-0">No pending reviews.</p>
                            </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        {{-- Recent Activity --}}
        <div class="card">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-history me-2"></i>Recent Security Activity
                </h5>
                <a href="/admin/security/audit" class="btn btn-sm btn-outline-primary">Full Audit Log</a>
            </div>
            <div class="card-body p-0">
                @if(!empty($recentActivity))
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Time</th>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Object</th>
                                    <th>Result</th>
                                    <th>IP Address</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($recentActivity as $activity)
                                    <tr>
                                        <td>{{ date('Y-m-d H:i', strtotime($activity->created_at)) }}</td>
                                        <td>{{ $activity->username }}</td>
                                        <td>{{ ucfirst($activity->action) }}</td>
                                        <td>{{ $activity->object_title ?? 'ID: '.$activity->object_id }}</td>
                                        <td>
                                            @if($activity->access_granted)
                                                <span class="badge bg-success">Granted</span>
                                            @else
                                                <span class="badge bg-danger">Denied</span>
                                            @endif
                                        </td>
                                        <td><code>{{ $activity->ip_address ?? '-' }}</code></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="text-center py-4 text-muted">
                        <i class="fas fa-history fa-2x mb-2"></i>
                        <p class="mb-0">No recent activity.</p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
