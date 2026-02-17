{{--
  User menu — matches ahgThemeB5Plugin/modules/menu/templates/_userMenu.mod_standard.php
  When not logged in: button dropdown with login form
  When logged in: button dropdown with profile, tasks, logout
--}}
@php
  $isAuth = $isAuthenticated ?? ($sf_user && $sf_user->isAuthenticated());
  $isAdmin = $isAuth && $sf_user && $sf_user->isAdministrator();
@endphp

@if ($isAuth)
{{-- Authenticated user menu --}}
<div class="dropdown my-2">
  <button class="btn btn-sm atom-btn-secondary dropdown-toggle" type="button" id="user-menu"
          data-bs-toggle="dropdown" aria-expanded="false">
    <i class="fas fa-user-circle me-1"></i>{{ $sf_user->getUsername() ?? __('Account') }}
  </button>
  <ul class="dropdown-menu dropdown-menu-lg-end mt-2" aria-labelledby="user-menu">
    {{-- Profile --}}
    <li><h6 class="dropdown-header"><i class="fas fa-user me-1"></i>{{ __('Profile') }}</h6></li>
    <li>
      <a class="dropdown-item" href="/user/{{ $sf_user->getAttribute('user_slug', '') }}">
        <i class="fas fa-id-card me-2"></i>{{ __('My Profile') }}
      </a>
    </li>
    <li>
      <a class="dropdown-item" href="/user/passwordEdit">
        <i class="fas fa-key me-2"></i>{{ __('Change Password') }}
      </a>
    </li>

    {{-- Research workspace --}}
    <li><hr class="dropdown-divider"></li>
    <li>
      <a class="dropdown-item" href="/research/dashboard">
        <i class="fas fa-folder-open me-2"></i>{{ __('My Workspace') }}
      </a>
    </li>

    {{-- Logout --}}
    <li><hr class="dropdown-divider"></li>
    <li>
      <a class="dropdown-item text-danger" href="/auth/logout">
        <i class="fas fa-sign-out-alt me-2"></i>{{ __('Logout') }}
      </a>
    </li>
  </ul>
</div>

@else
{{-- Login dropdown for unauthenticated users --}}
<div class="dropdown my-2">
  <button class="btn btn-sm atom-btn-secondary dropdown-toggle" type="button" id="user-menu"
          data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
    <i class="fas fa-sign-in-alt me-1"></i>{{ __('Log in') }}
  </button>
  <div class="dropdown-menu dropdown-menu-lg-end mt-2 p-3" aria-labelledby="user-menu" style="min-width: 280px;">
    <h6 class="dropdown-header px-0">{{ __('Have an account?') }}</h6>
    <form action="/auth/login" method="post" class="mt-2">
      <div class="mb-2">
        <label for="login-email" class="form-label small mb-1">{{ __('Email') }}</label>
        <input type="email" class="form-control form-control-sm" id="login-email" name="email"
               placeholder="{{ __('Email address') }}" required>
      </div>
      <div class="mb-2">
        <label for="login-password" class="form-label small mb-1">{{ __('Password') }}</label>
        <input type="password" class="form-control form-control-sm" id="login-password" name="password"
               placeholder="{{ __('Password') }}" autocomplete="off" required>
      </div>
      <button class="btn btn-sm atom-btn-secondary w-100 mt-2" type="submit">
        {{ __('Log in') }}
      </button>
    </form>
    <div class="text-center mt-2">
      <a href="/user/passwordReset" class="small text-muted">
        <i class="fas fa-key me-1"></i>{{ __('Forgot password?') }}
      </a>
    </div>
    <hr class="my-3">
    <div class="text-center">
      <small class="text-muted d-block mb-2"><i class="fas fa-user-graduate me-1"></i>{{ __('New Researcher?') }}</small>
      <a href="/research/publicRegister" class="btn btn-sm btn-success w-100">
        <i class="fas fa-user-plus me-1"></i>{{ __('Register as Researcher') }}
      </a>
    </div>
  </div>
</div>
@endif
