<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Admin') - AtoM Archive</title>
    
    {{-- Bootstrap 5 CSS --}}
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    {{-- Font Awesome --}}
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --sidebar-width: 260px;
            --header-height: 56px;
            --primary-color: #0d6efd;
            --sidebar-bg: #212529;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background-color: #f8f9fa;
        }
        
        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: var(--sidebar-width);
            height: 100vh;
            background: var(--sidebar-bg);
            z-index: 1000;
            overflow-y: auto;
        }
        
        .sidebar-brand {
            height: var(--header-height);
            display: flex;
            align-items: center;
            padding: 0 1rem;
            background: rgba(0,0,0,0.2);
        }
        
        .sidebar-brand a {
            color: white;
            text-decoration: none;
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .sidebar-nav {
            padding: 1rem 0;
        }
        
        .sidebar-nav .nav-section {
            color: rgba(255,255,255,0.5);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 1rem 1rem 0.5rem;
            margin-top: 0.5rem;
        }
        
        .sidebar-nav .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.6rem 1rem;
            display: flex;
            align-items: center;
            transition: all 0.2s;
        }
        
        .sidebar-nav .nav-link:hover {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar-nav .nav-link.active {
            color: white;
            background: var(--primary-color);
        }
        
        .sidebar-nav .nav-link i {
            width: 24px;
            margin-right: 0.75rem;
        }
        
        /* Main Content */
        .main-wrapper {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
        }
        
        .main-header {
            height: var(--header-height);
            background: white;
            border-bottom: 1px solid #dee2e6;
            display: flex;
            align-items: center;
            padding: 0 1.5rem;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .main-content {
            padding: 1.5rem;
        }
        
        /* Cards */
        .card {
            border: none;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }
        
        .card-header {
            background-color: #fff;
            border-bottom: 1px solid rgba(0,0,0,0.125);
            font-weight: 600;
        }
        
        /* Tables */
        .table th {
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-top: none;
        }
        
        /* Responsive */
        @media (max-width: 991.98px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            
            .sidebar.show {
                transform: translateX(0);
            }
            
            .main-wrapper {
                margin-left: 0;
            }
        }
        
        /* Custom Styles */
        .badge {
            font-weight: 500;
        }
        
        .btn-group-sm .btn {
            padding: 0.25rem 0.5rem;
        }
        
        /* Alerts */
        .alert {
            border: none;
            border-radius: 0.375rem;
        }
    </style>
    
    @yield('styles')
</head>
<body>
    {{-- Sidebar --}}
    <aside class="sidebar">
        <div class="sidebar-brand">
            <a href="/">
                <i class="fas fa-archive me-2"></i>AtoM Archive
            </a>
        </div>
        
        <nav class="sidebar-nav">
            <a href="/admin" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            
            <div class="nav-section">Descriptions</div>
            <a href="/informationobject/browse" class="nav-link">
                <i class="fas fa-folder-open"></i>Browse
            </a>
            <a href="/informationobject/add" class="nav-link">
                <i class="fas fa-plus"></i>Add New
            </a>
            
            <div class="nav-section">Security</div>
            <a href="/admin/security" class="nav-link {{ request()->is('admin/security*') ? 'active' : '' }}">
                <i class="fas fa-shield-alt"></i>Dashboard
            </a>
            <a href="/admin/security/compliance" class="nav-link {{ request()->is('admin/security/compliance*') ? 'active' : '' }}">
                <i class="fas fa-clipboard-check"></i>Compliance
            </a>
            <a href="/admin/security/users" class="nav-link">
                <i class="fas fa-user-shield"></i>Clearances
            </a>
            
            <div class="nav-section">Condition</div>
            <a href="/admin/condition" class="nav-link {{ request()->is('admin/condition') ? 'active' : '' }}">
                <i class="fas fa-clipboard-list"></i>Dashboard
            </a>
            <a href="/admin/condition/risk" class="nav-link {{ request()->is('admin/condition/risk*') ? 'active' : '' }}">
                <i class="fas fa-exclamation-triangle"></i>Risk Assessment
            </a>
            <a href="/admin/condition/schedule" class="nav-link {{ request()->is('admin/condition/schedule*') ? 'active' : '' }}">
                <i class="fas fa-calendar-check"></i>Schedule
            </a>
            <a href="/admin/condition/vocabularies" class="nav-link {{ request()->is('admin/condition/vocabularies*') ? 'active' : '' }}">
                <i class="fas fa-list"></i>Vocabularies
            </a>
            
            <div class="nav-section">Privacy</div>
            <a href="/admin/privacy" class="nav-link {{ request()->is('admin/privacy') ? 'active' : '' }}">
                <i class="fas fa-user-shield"></i>Dashboard
            </a>
            <a href="/admin/privacy/ropa" class="nav-link {{ request()->is('admin/privacy/ropa*') ? 'active' : '' }}">
                <i class="fas fa-database"></i>ROPA
            </a>
            <a href="/admin/privacy/dsar" class="nav-link {{ request()->is('admin/privacy/dsar*') ? 'active' : '' }}">
                <i class="fas fa-user-clock"></i>DSARs
            </a>
            <a href="/admin/privacy/breaches" class="nav-link {{ request()->is('admin/privacy/breaches*') ? 'active' : '' }}">
                <i class="fas fa-exclamation-circle"></i>Breaches
            </a>
            <a href="/admin/privacy/templates" class="nav-link {{ request()->is('admin/privacy/templates*') ? 'active' : '' }}">
                <i class="fas fa-file-alt"></i>Templates
            </a>
            
            <div class="nav-section">Administration</div>
            <a href="/admin/settings" class="nav-link">
                <i class="fas fa-cog"></i>Settings
            </a>
            <a href="/admin/users" class="nav-link">
                <i class="fas fa-users"></i>Users
            </a>
        </nav>
    </aside>
    
    {{-- Main Content --}}
    <div class="main-wrapper">
        <header class="main-header">
            <button class="btn btn-link d-lg-none me-3" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            
            <div class="flex-grow-1">
                @yield('header-content')
            </div>
            
            <div class="d-flex align-items-center">
                <div class="dropdown">
                    <button class="btn btn-link text-dark dropdown-toggle" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle me-1"></i>
                        {{ Auth::user()->username ?? 'Admin' }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><a class="dropdown-item" href="/user/profile"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="/user/logout"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>
        
        <main class="main-content">
            {{-- Flash Messages --}}
            @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>{{ session('success') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            @endif
            
            @if(session('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>{{ session('error') }}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            @endif
            
            @if(request()->get('success'))
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i>Operation completed successfully
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            @endif
            
            @if(request()->get('error'))
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>An error occurred
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            @endif
            
            @yield('content')
        </main>
        
        <footer class="text-center text-muted py-4 small">
            <p class="mb-0">AtoM Archive &copy; {{ date('Y') }} The Archive and Heritage Group</p>
        </footer>
    </div>
    
    {{-- Bootstrap 5 JS --}}
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Sidebar toggle for mobile
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
        
        // Auto-dismiss alerts after 5 seconds
        document.querySelectorAll('.alert-dismissible').forEach(function(alert) {
            setTimeout(function() {
                var bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            }, 5000);
        });
    </script>
    
    @yield('scripts')
</body>
</html>
