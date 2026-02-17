{{--
  Header partial — standalone navbar matching ahgThemeB5Plugin structure.
  Uses MenuService for database-driven menus + SfUserAdapter for auth state.
--}}
@php
  use AtomFramework\Services\MenuService;
  use AtomFramework\Services\ConfigService;

  $sfUser = $sf_user ?? null;
  $currentCulture = $sfUser ? $sfUser->getCulture() : ($culture ?? 'en');
  $isAuthenticated = $sfUser && $sfUser->isAuthenticated();
  $isAdmin = $sfUser && $sfUser->isAdministrator();
  $toggleLogo = ConfigService::get('toggleLogo', true);
  $toggleTitle = ConfigService::get('toggleTitle', true);
  $siteTitle = ConfigService::get('siteTitle', 'AtoM');
  $toggleDescription = ConfigService::get('toggleDescription', false);
  $siteDescription = ConfigService::get('siteDescription', '');
  $enabledPlugins = MenuService::getEnabledPlugins();

  // Main menu items (Add, Manage, Import, Admin) — only for authenticated users
  $mainMenuItems = $isAuthenticated ? MenuService::getMainMenu($currentCulture) : [];

  // Quick links
  $quickLinks = MenuService::getQuickLinks($currentCulture);

  // Clipboard
  $clipboardItems = MenuService::getChildren('clipboard', $currentCulture);

  // Icon map for main menu sections
  $menuIcons = [
      'add' => 'plus',
      'manage' => 'wrench',
      'import' => 'download',
      'admin' => 'cog',
  ];
@endphp
<div class="visually-hidden-focusable p-3 border-bottom">
  <a class="btn btn-sm btn-secondary" href="#main-column">
    {{ __('Skip to main content') }}
  </a>
</div>

<header id="top-bar" class="navbar navbar-expand-lg navbar-dark bg-dark" role="navigation" aria-label="{{ __('Main navigation') }}">
  <div class="container-fluid">

    {{-- Logo / Title --}}
    @if ($toggleLogo || $toggleTitle)
      <a class="navbar-brand d-flex flex-wrap flex-lg-nowrap align-items-center py-0 me-0" href="/" title="{{ __('Home') }}" rel="home">
        @if ($toggleLogo)
          <img src="/plugins/ahgThemeB5Plugin/images/image.png" alt="{{ $siteTitle ?? 'Archive' }}" class="d-inline-block my-2 me-3" height="35">
        @endif
        @if ($toggleTitle && !empty($siteTitle))
          <span class="text-wrap my-1 me-3">{{ $siteTitle }}</span>
        @endif
      </a>
    @endif

    {{-- Mobile Toggle --}}
    <button class="navbar-toggler atom-btn-secondary my-2 me-1 px-1" type="button" data-bs-toggle="collapse" data-bs-target="#navbar-content" aria-controls="navbar-content" aria-expanded="false">
      <i class="fas fa-2x fa-fw fa-bars" aria-hidden="true"></i>
      <span class="visually-hidden">{{ __('Toggle navigation') }}</span>
    </button>

    {{-- Collapsible Nav --}}
    <div class="collapse navbar-collapse flex-wrap justify-content-end me-1" id="navbar-content">

      {{-- Browse Menu + Search --}}
      <div class="d-flex flex-wrap flex-lg-nowrap flex-grow-1">
        @include('partials.nav-browse', ['culture' => $currentCulture])
        @include('partials.nav-search')
      </div>

      {{-- Right-aligned menus --}}
      <div class="d-flex flex-nowrap flex-column flex-lg-row align-items-strech align-items-lg-center">
        <ul class="navbar-nav mx-lg-2">

          {{-- Main menu dropdowns: Add, Manage, Import, Admin --}}
          @foreach ($mainMenuItems as $menuItem)
            @if (!empty($menuItem->children))
              <li class="nav-item dropdown d-flex flex-column">
                <a class="nav-link dropdown-toggle d-flex align-items-center p-0" href="#"
                   id="{{ $menuItem->name }}-menu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                  <i class="fas fa-2x fa-fw fa-{{ $menuIcons[$menuItem->name] ?? 'ellipsis-h' }} px-0 px-lg-2 py-2"
                     data-bs-toggle="tooltip" data-bs-placement="bottom"
                     data-bs-custom-class="d-none d-lg-block"
                     title="{{ $menuItem->label }}" aria-hidden="true"></i>
                  <span class="d-lg-none mx-1" aria-hidden="true">{{ $menuItem->label }}</span>
                  <span class="visually-hidden">{{ $menuItem->label }}</span>
                </a>
                <ul class="dropdown-menu dropdown-menu-end mb-2" aria-labelledby="{{ $menuItem->name }}-menu">
                  <li><h6 class="dropdown-header">{{ $menuItem->label }}</h6></li>
                  @foreach ($menuItem->children as $child)
                    <li>
                      <a class="dropdown-item" href="{{ MenuService::resolvePath($child->path) }}">
                        {{ $child->label ?: $child->name }}
                      </a>
                    </li>
                  @endforeach
                </ul>
              </li>
            @endif
          @endforeach

          {{-- Clipboard menu --}}
          @if (!empty($clipboardItems))
            <li class="nav-item dropdown d-flex flex-column">
              <a class="nav-link dropdown-toggle d-flex align-items-center p-0" href="#"
                 id="clipboard-menu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-2x fa-fw fa-paperclip px-0 px-lg-2 py-2"
                   data-bs-toggle="tooltip" data-bs-placement="bottom"
                   data-bs-custom-class="d-none d-lg-block"
                   title="{{ __('Clipboard') }}" aria-hidden="true"></i>
                <span class="d-lg-none mx-1" aria-hidden="true">{{ __('Clipboard') }}</span>
                <span class="visually-hidden">{{ __('Clipboard') }}</span>
              </a>
              <ul class="dropdown-menu dropdown-menu-end mb-2" aria-labelledby="clipboard-menu">
                <li><h6 class="dropdown-header">{{ __('Clipboard') }}</h6></li>
                @foreach ($clipboardItems as $child)
                  <li>
                    <a class="dropdown-item" href="{{ MenuService::resolvePath($child->path) }}">
                      {{ $child->label ?: $child->name }}
                    </a>
                  </li>
                @endforeach
              </ul>
            </li>
          @endif

          {{-- Quick links menu (hardcoded About/Contact + DB static pages) --}}
          <li class="nav-item dropdown d-flex flex-column">
            <a class="nav-link dropdown-toggle d-flex align-items-center p-0" href="#"
               id="quick-links-menu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
              <i class="fas fa-2x fa-fw fa-info-circle px-0 px-lg-2 py-2"
                 data-bs-toggle="tooltip" data-bs-placement="bottom"
                 data-bs-custom-class="d-none d-lg-block"
                 title="{{ __('Quick links') }}" aria-hidden="true"></i>
              <span class="d-lg-none mx-1" aria-hidden="true">{{ __('Quick links') }}</span>
              <span class="visually-hidden">{{ __('Quick links') }}</span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end mb-2" aria-labelledby="quick-links-menu">
              <li><h6 class="dropdown-header">{{ __('Quick links') }}</h6></li>
              <li><a class="dropdown-item" href="/about"><i class="fas fa-info-circle fa-fw me-2"></i>{{ __('About') }}</a></li>
              <li><a class="dropdown-item" href="/contact"><i class="fas fa-envelope fa-fw me-2"></i>{{ __('Contact') }}</a></li>
              @foreach ($quickLinks as $child)
                <li>
                  <a class="dropdown-item" href="{{ MenuService::resolvePath($child->path) }}">
                    {{ $child->label ?: $child->name }}
                  </a>
                </li>
              @endforeach
            </ul>
          </li>

          {{-- AHG Admin menu (conditional) --}}
          @if ($isAdmin)
            @include('partials.nav-admin', ['enabledPlugins' => $enabledPlugins])
          @endif

        </ul>

        {{-- User menu --}}
        @include('partials.nav-user', ['sf_user' => $sfUser, 'isAuthenticated' => $isAuthenticated])
      </div>
    </div>
  </div>
</header>

{{-- Description bar --}}
@if ($toggleDescription && !empty($siteDescription))
  <div class="bg-secondary text-white">
    <div class="container-xl py-1">
      {{ $siteDescription }}
    </div>
  </div>
@endif
