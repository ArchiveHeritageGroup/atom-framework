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
          <img src="/plugins/arDominionB5Plugin/images/logo" alt="{{ __('AtoM logo') }}" class="d-inline-block my-2 me-3" height="35">
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

          {{-- Admin menu (conditional) --}}
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
