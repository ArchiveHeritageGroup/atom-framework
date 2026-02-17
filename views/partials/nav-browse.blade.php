{{--
  Browse dropdown menu — reads from menu table (browse children).
  Matches ahgThemeB5Plugin/modules/menu/templates/_browseMenu.php structure.
--}}
@php
  use AtomFramework\Services\MenuService;

  $browseItems = MenuService::getBrowseMenu($culture ?? 'en');
@endphp
@if (!empty($browseItems))
  <div class="dropdown my-2 me-3">
    <button class="btn btn-sm atom-btn-secondary dropdown-toggle" type="button" id="browse-menu"
            data-bs-toggle="dropdown" aria-expanded="false">
      {{ __('Browse') }}
    </button>
    <ul class="dropdown-menu mt-2" aria-labelledby="browse-menu">
      <li><h6 class="dropdown-header">{{ __('Browse') }}</h6></li>
      @foreach ($browseItems as $item)
        <li>
          <a class="dropdown-item" href="{{ \AtomFramework\Services\MenuService::resolvePath($item->path) }}">
            {{ $item->label ?: $item->name }}
          </a>
        </li>
      @endforeach
    </ul>
  </div>
@endif
