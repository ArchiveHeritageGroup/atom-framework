{{--
  Flash message alerts — matches ahgThemeB5Plugin/_alerts.php mapping.
--}}
@php
  $sfUser = $sf_user ?? null;
  $alertMap = [
    'notice'  => 'warning',
    'info'    => 'info',
    'error'   => 'danger',
    'success' => 'success',
  ];
@endphp
@if ($sfUser)
  @foreach ($alertMap as $type => $bsClass)
    @if ($sfUser->hasFlash($type))
      <div class="alert alert-{{ $bsClass }} alert-dismissible fade show" role="alert">
        {!! $sfUser->getFlash($type) !!}
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="{{ __('Close') }}"></button>
      </div>
    @endif
  @endforeach
@endif
