{{--
  Admin menu — AHG Plugins dropdown for administrators.
  Mirrors the structure of ahgThemeB5Plugin/_ahgAdminMenu.php.
--}}
@php
  $plugins = $enabledPlugins ?? [];
@endphp
<li class="nav-item dropdown d-flex flex-column">
  <a class="nav-link dropdown-toggle d-flex align-items-center p-0" href="#" id="ahg-plugins-menu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
    <i class="fas fa-2x fa-fw fa-cubes px-0 px-lg-2 py-2" aria-hidden="true"></i>
    <span class="d-lg-none mx-1">{{ __('AHG Plugins') }}</span>
    <span class="visually-hidden">{{ __('AHG Plugins') }}</span>
  </a>
  <ul class="dropdown-menu dropdown-menu-end mb-2" aria-labelledby="ahg-plugins-menu">
    {{-- Settings --}}
    @if (in_array('ahgSettingsPlugin', $plugins))
      <li><h6 class="dropdown-header">{{ __('Settings') }}</h6></li>
      <li><a class="dropdown-item" href="/admin/ahg-settings"><i class="fas fa-cog me-2"></i>{{ __('AHG Settings') }}</a></li>
      <li><hr class="dropdown-divider"></li>
    @endif

    {{-- Security --}}
    @if (in_array('ahgSecurityClearancePlugin', $plugins))
      <li><h6 class="dropdown-header">{{ __('Security') }}</h6></li>
      <li><a class="dropdown-item" href="/security/dashboard"><i class="fas fa-shield-alt me-2"></i>{{ __('Clearances') }}</a></li>
      <li><hr class="dropdown-divider"></li>
    @endif

    {{-- Research --}}
    @if (in_array('ahgResearchPlugin', $plugins))
      <li><h6 class="dropdown-header">{{ __('Research') }}</h6></li>
      <li><a class="dropdown-item" href="/research/admin"><i class="fas fa-flask me-2"></i>{{ __('Dashboard') }}</a></li>
      <li><hr class="dropdown-divider"></li>
    @endif

    {{-- Access --}}
    @if (in_array('ahgAccessRequestPlugin', $plugins))
      <li><h6 class="dropdown-header">{{ __('Access') }}</h6></li>
      <li><a class="dropdown-item" href="/security/access-requests"><i class="fas fa-key me-2"></i>{{ __('Requests') }}</a></li>
      <li><hr class="dropdown-divider"></li>
    @endif

    {{-- Audit --}}
    @if (in_array('ahgAuditTrailPlugin', $plugins))
      <li><h6 class="dropdown-header">{{ __('Audit') }}</h6></li>
      <li><a class="dropdown-item" href="/admin/audit"><i class="fas fa-clipboard-list me-2"></i>{{ __('Audit Logs') }}</a></li>
      <li><hr class="dropdown-divider"></li>
    @endif

    {{-- Heritage --}}
    @if (in_array('ahgHeritagePlugin', $plugins))
      <li><h6 class="dropdown-header">{{ __('Heritage') }}</h6></li>
      <li><a class="dropdown-item" href="/heritage/admin/config"><i class="fas fa-landmark me-2"></i>{{ __('Admin') }}</a></li>
      <li><hr class="dropdown-divider"></li>
    @endif

    {{-- Backup --}}
    @if (in_array('ahgBackupPlugin', $plugins))
      <li><h6 class="dropdown-header">{{ __('Maintenance') }}</h6></li>
      <li><a class="dropdown-item" href="/admin/backup"><i class="fas fa-database me-2"></i>{{ __('Backup') }}</a></li>
    @endif
  </ul>
</li>
