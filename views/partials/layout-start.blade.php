{{--
  Standalone replacement for ahgThemeB5Plugin/_layout_start.php.
  Used when get_partial('layout_start') is called in standalone mode.
--}}
<!DOCTYPE html>
<html lang="{{ $culture ?? 'en' }}" dir="ltr">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="/favicon.ico">
    @include('partials.head-assets')
  </head>
  <body class="d-flex flex-column min-vh-100 show-edit-tooltips">
    @include('partials.header')
