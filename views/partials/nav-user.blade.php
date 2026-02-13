{{--
  User menu — login/profile/logout based on auth state.
--}}
@php
  $isAuth = $isAuthenticated ?? ($sf_user && $sf_user->isAuthenticated());
@endphp
<ul class="navbar-nav">
  @if ($isAuth)
    <li class="nav-item dropdown d-flex flex-column">
      <a class="nav-link dropdown-toggle d-flex align-items-center p-0" href="#" id="user-menu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-2x fa-fw fa-user px-0 px-lg-2 py-2" aria-hidden="true"></i>
        <span class="d-lg-none mx-1">{{ $sf_user->getUsername() ?? __('Account') }}</span>
        <span class="visually-hidden">{{ __('User menu') }}</span>
      </a>
      <ul class="dropdown-menu dropdown-menu-end mb-2" aria-labelledby="user-menu">
        <li><h6 class="dropdown-header">{{ $sf_user->getUsername() ?? __('Account') }}</h6></li>
        <li><a class="dropdown-item" href="/user/{{ $sf_user->getAttribute('user_slug', '') }}"><i class="fas fa-id-card me-2"></i>{{ __('Profile') }}</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="/auth/logout"><i class="fas fa-sign-out-alt me-2"></i>{{ __('Log out') }}</a></li>
      </ul>
    </li>
  @else
    <li class="nav-item d-flex flex-column">
      <a class="nav-link d-flex align-items-center p-0" href="/user/login">
        <i class="fas fa-2x fa-fw fa-sign-in-alt px-0 px-lg-2 py-2" aria-hidden="true"></i>
        <span class="d-lg-none mx-1">{{ __('Log in') }}</span>
        <span class="visually-hidden">{{ __('Log in') }}</span>
      </a>
    </li>
  @endif
</ul>
