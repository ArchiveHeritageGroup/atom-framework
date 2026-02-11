{!! get_partial('layout_start') !!}
@php
$adminNotificationsPath = sfConfig::get('sf_plugins_dir') . '/ahgThemeB5Plugin/templates/_adminNotifications.php';
if (file_exists($adminNotificationsPath)) {
    include($adminNotificationsPath);
}
@endphp
<div id="wrapper" class="container-xxl pt-3 flex-grow-1">
  {!! get_partial('alerts') !!}
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
{!! get_partial('layout_end') !!}
