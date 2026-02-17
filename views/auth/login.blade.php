@extends('layouts.heratio')

@section('title', __('Log in') . ' - ' . ($siteTitle ?? 'AtoM'))

@section('content')
<div class="row justify-content-center mt-5">
  <div class="col-md-5 col-lg-4">
    <div class="card shadow-sm">
      <div class="card-body p-4">
        <h4 class="card-title text-center mb-4">
          <i class="fas fa-sign-in-alt me-2"></i>{{ __('Log in') }}
        </h4>

        @if (!empty($error))
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-1"></i>{{ $error }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>
        @endif

        <form action="/auth/login" method="post">
          <div class="mb-3">
            <label for="login-email" class="form-label">{{ __('Email') }}</label>
            <input type="email" class="form-control" id="login-email" name="email"
                   placeholder="{{ __('Email address') }}" autofocus required>
          </div>
          <div class="mb-3">
            <label for="login-password" class="form-label">{{ __('Password') }}</label>
            <input type="password" class="form-control" id="login-password" name="password"
                   placeholder="{{ __('Password') }}" autocomplete="off" required>
          </div>
          <button class="btn atom-btn-secondary w-100" type="submit">
            <i class="fas fa-sign-in-alt me-1"></i>{{ __('Log in') }}
          </button>
        </form>

        <div class="text-center mt-3">
          <a href="/user/passwordReset" class="small text-muted">
            <i class="fas fa-key me-1"></i>{{ __('Forgot password?') }}
          </a>
        </div>

        <hr class="my-4">

        <div class="text-center">
          <small class="text-muted d-block mb-2">
            <i class="fas fa-user-graduate me-1"></i>{{ __('New Researcher?') }}
          </small>
          <a href="/research/publicRegister" class="btn btn-sm btn-success w-100">
            <i class="fas fa-user-plus me-1"></i>{{ __('Register as Researcher') }}
          </a>
        </div>
      </div>
    </div>
  </div>
</div>
@endsection
