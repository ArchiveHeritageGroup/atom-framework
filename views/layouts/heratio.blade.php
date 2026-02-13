<!DOCTYPE html>
<html lang="{{ $culture ?? 'en' }}">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title', $siteTitle ?? 'AtoM')</title>
  <link rel="shortcut icon" href="/favicon.ico">
  @include('partials.head-assets')
  @yield('head')
</head>
<body class="d-flex flex-column min-vh-100 @yield('body-class')">

  @include('partials.header')

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

        @yield('title-bar')
        @yield('before-content')

        @hasSection('content')
          @yield('content')
        @else
          {{-- Injected content from autoRender (fragment wrapping) --}}
          @if (!empty($sf_content))
            <div id="content">
              {!! $sf_content !!}
            </div>
          @else
            @yield('body')
          @endif
        @endif

        @yield('after-content')
      </div>
    </div>
  </div>

  @include('partials.footer')
  @yield('scripts')
</body>
</html>
