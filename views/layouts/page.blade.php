@if (defined('HERATIO_STANDALONE'))
{{-- Standalone mode: use Heratio Blade layout (no Symfony dependencies) --}}
<!DOCTYPE html>
<html lang="{{ $culture ?? 'en' }}" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="shortcut icon" href="/favicon.ico">
  @include('partials.head-assets')
  @yield('head')
</head>
<body class="d-flex flex-column min-vh-100 @yield('body-class', '') show-edit-tooltips">
  @include('partials.header')
  @yield('pre')
@else
{{-- Symfony mode: use theme partials --}}
{!! get_partial('layout_start') !!}
@php
$adminNotificationsPath = sfConfig::get('sf_plugins_dir') . '/ahgThemeB5Plugin/templates/_adminNotifications.php';
if (file_exists($adminNotificationsPath)) {
    include($adminNotificationsPath);
}
@endphp
@endif
<div id="wrapper" class="container-xxl pt-3 flex-grow-1">
  @include('partials.alerts')
  <div class="row">
    @hasSection('sidebar')
    <div id="sidebar" class="col-md-3">
      @yield('sidebar')
    </div>
    <div id="main-column" class="col-md-9" role="main">
    @else
    <div id="main-column" class="col-md-12" role="main">
    @endif
      @yield('title')
      @yield('before-content')
      @hasSection('content')
        @yield('content')
      @else
        <div id="content">
          @yield('body')
        </div>
      @endif
      @yield('after-content')
    </div>
  </div>
</div>
@if (defined('HERATIO_STANDALONE'))
  @include('partials.footer')
  @yield('scripts')
</body>
</html>
@else
@php
$footerPath = \sfConfig::get('sf_plugins_dir') . '/ahgThemeB5Plugin/templates/a_footer.php';
if (file_exists($footerPath)) { include($footerPath); }
@endphp
{!! get_partial('layout_end') !!}
@endif
