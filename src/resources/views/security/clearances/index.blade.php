{{-- User Security Clearances Index - Mimics AtoM 2.10 --}}
@extends('layouts.admin')

@section('title', $pageTitle)

@section('content')
<div class="row">
    <div class="col-12">
        {{-- Page Header --}}
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 class="h3 mb-0">
                <i class="fas fa-user-shield me-2"></i>{{ $pageTitle }}
            </h1>
            <a href="/admin/security/clearances/grant" class="btn btn-success">
                <i class="fas fa-plus me-1"></i>Grant Clearance
            </a>
        </div>

        {{-- Flash Messages --}}
        @if(request()->get('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                @switch(request()->get('success'))
                    @case('granted')
                        Security clearance has been granted successfully.
                        @break
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

        @if(request()->get('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                An error occurred. Please try again.
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        @endif

        {{-- Statistics Cards --}}
        <div class="row mb-4">
            @foreach($stats['users_by_level'] as $level)
                <div class="col-md-2">
                    <div class="card text-white" style="background-color: {{ $level->color }};">
                        <div class="card-body text-center py-3">
                            <h2 class="mb-0">{{ $level->count }}</h2>
                            <small>{{ $level->name }}</small>
                        </div>
                    </div>
                </div>
            @endforeach
            <div class="col-md-2">
                <div class="card bg-secondary text-white">
                    <div class="card-body text-center py-3">
                        <h2 class="mb-0">{{ $stats['expiring_clearances'] }}</h2>
                        <small>Expiring Soon</small>
                    </div>
                </div>
            </div>
        </div>

        {{-- Search Form --}}
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" action="/admin/security/clearances" class="row g-3">
                    <div class="col-md-10">
                        <input type="text" class="form-control" name="search" 
                               value="{{ $search }}" placeholder="Search by username, email, or classification...">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-search me-1"></i>Search
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Clearances Table --}}
        <div class="card">
            <div class="card-header bg-light">
                <strong>User Clearances</strong>
                <span class="badge bg-secondary ms-2">{{ $total }} total</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover table-striped mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Clearance Level</th>
                                <th>Granted By</th>
                                <th>Granted Date</th>
                                <th>Expires</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($clearances as $clearance)
                                <tr @if($clearance->isExpired()) class="table-secondary" @elseif($clearance->expiresWithinDays(30)) class="table-warning" @endif>
                                    <td>
                                        <a href="/admin/users/{{ $clearance->userId }}">
                                            {{ $clearance->username }}
                                        </a>
                                    </td>
                                    <td>{{ $clearance->userEmail }}</td>
                                    <td>
                                        <span class="badge {{ $clearance->getBadgeClass() }}">
                                            <i class="{{ $clearance->classificationIcon ?? 'fa-lock' }} me-1"></i>
                                            {{ $clearance->classificationName }}
                                        </span>
                                        @if($clearance->isExpired())
                                            <span class="badge bg-danger ms-1">Expired</span>
                                        @endif
                                    </td>
                                    <td>{{ $clearance->grantedByUsername ?? 'System' }}</td>
                                    <td>{{ $clearance->grantedAt ? date('Y-m-d', strtotime($clearance->grantedAt)) : '-' }}</td>
                                    <td>
                                        @if($clearance->expiresAt)
                                            {{ date('Y-m-d', strtotime($clearance->expiresAt)) }}
                                            @if($clearance->expiresWithinDays(30) && !$clearance->isExpired())
                                                <span class="badge bg-warning text-dark ms-1">Soon</span>
                                            @endif
                                        @else
                                            <span class="text-muted">No expiry</span>
                                        @endif
                                    </td>
                                    <td class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <a href="/admin/security/clearances/{{ $clearance->userId }}/edit" 
                                               class="btn btn-outline-primary" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-outline-danger" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#revokeModal{{ $clearance->userId }}"
                                                    title="Revoke">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>

                                        {{-- Revoke Modal --}}
                                        <div class="modal fade" id="revokeModal{{ $clearance->userId }}" tabindex="-1">
                                            <div class="modal-dialog">
                                                <div class="modal-content">
                                                    <form method="POST" action="/admin/security/clearances/{{ $clearance->userId }}">
                                                        @method('DELETE')
                                                        <div class="modal-header">
                                                            <h5 class="modal-title">Revoke Clearance</h5>
                                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                                        </div>
                                                        <div class="modal-body text-start">
                                                            <p>Are you sure you want to revoke the security clearance for <strong>{{ $clearance->username }}</strong>?</p>
                                                            <div class="mb-3">
                                                                <label class="form-label">Reason for revocation</label>
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
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="text-center py-4 text-muted">
                                        <i class="fas fa-user-shield fa-3x mb-3 d-block"></i>
                                        No user clearances found.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            {{-- Pagination --}}
            @if($total > $perPage)
                <div class="card-footer">
                    <nav>
                        <ul class="pagination pagination-sm mb-0 justify-content-center">
                            @php
                                $totalPages = ceil($total / $perPage);
                            @endphp
                            
                            @if($page > 1)
                                <li class="page-item">
                                    <a class="page-link" href="?page={{ $page - 1 }}{{ $search ? '&search='.$search : '' }}">
                                        &laquo; Previous
                                    </a>
                                </li>
                            @endif

                            @for($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++)
                                <li class="page-item {{ $i == $page ? 'active' : '' }}">
                                    <a class="page-link" href="?page={{ $i }}{{ $search ? '&search='.$search : '' }}">
                                        {{ $i }}
                                    </a>
                                </li>
                            @endfor

                            @if($page < $totalPages)
                                <li class="page-item">
                                    <a class="page-link" href="?page={{ $page + 1 }}{{ $search ? '&search='.$search : '' }}">
                                        Next &raquo;
                                    </a>
                                </li>
                            @endif
                        </ul>
                    </nav>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
