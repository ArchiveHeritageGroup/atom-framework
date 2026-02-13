{{--
  Browse dropdown menu — reads from menu table (browseMenu children).
--}}
@php
  use AtomFramework\Services\MenuService;

  $browseItems = MenuService::getBrowseMenu($culture ?? 'en');
@endphp
<ul class="navbar-nav me-auto">
  @if (!empty($browseItems))
    <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle d-flex align-items-center p-0" href="#" id="browse-menu" role="button" data-bs-toggle="dropdown" aria-expanded="false">
        <i class="fas fa-2x fa-fw fa-book px-0 px-lg-2 py-2" aria-hidden="true"></i>
        <span class="d-lg-none mx-1">{{ __('Browse') }}</span>
        <span class="visually-hidden">{{ __('Browse') }}</span>
      </a>
      <ul class="dropdown-menu mb-2" aria-labelledby="browse-menu">
        <li><h6 class="dropdown-header">{{ __('Browse') }}</h6></li>
        @foreach ($browseItems as $item)
          <li>
            <a class="dropdown-item" href="{{ \AtomFramework\Services\MenuService::resolvePath($item->path) }}">
              {{ $item->label ?: $item->name }}
            </a>
          </li>
        @endforeach
      </ul>
    </li>
  @endif
</ul>
