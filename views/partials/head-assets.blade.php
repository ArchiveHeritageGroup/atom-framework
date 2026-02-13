{{--
  Head Assets — loads CSS/JS bundles from /dist/ directory.

  Matches the same bundle-loading pattern as ahgThemeB5Plugin's
  _layout_start.php: dynamically discovers hashed bundle files.
--}}
@php
  $webDir = ($rootDir ?? '/usr/share/nginx/archive');
  $distPath = $webDir . '/dist';

  // Discover vendor JS bundle
  $vendorJsFiles = is_dir($distPath . '/js') ? glob($distPath . '/js/vendor.bundle.*.js') : [];
  $vendorJs = !empty($vendorJsFiles) ? '/dist/js/' . basename($vendorJsFiles[0]) : null;

  // Discover theme JS bundle
  $themeJsFiles = is_dir($distPath . '/js') ? glob($distPath . '/js/ahgThemeB5Plugin.bundle.*.js') : [];
  $themeJs = !empty($themeJsFiles) ? '/dist/js/' . basename($themeJsFiles[0]) : null;

  // Discover theme CSS bundle
  $themeCssFiles = is_dir($distPath . '/css') ? glob($distPath . '/css/ahgThemeB5Plugin.bundle.*.css') : [];
  $themeCss = !empty($themeCssFiles) ? '/dist/css/' . basename($themeCssFiles[0]) : null;
@endphp
@if ($themeCss)
  <link rel="stylesheet" href="{{ $themeCss }}">
@endif
@if ($vendorJs)
  <script defer src="{{ $vendorJs }}"></script>
@endif
@if ($themeJs)
  <script defer src="{{ $themeJs }}"></script>
@endif
