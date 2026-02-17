{{--
  Search box — global site search form.
  Matches the search component rendered by ahgThemeB5Plugin/modules/search/_box.php.
--}}
<form id="search-box" class="d-flex flex-grow-1 my-2" role="search" action="/display/browse">
  <h2 class="visually-hidden">{{ __('Search') }}</h2>
  <input type="hidden" name="topLod" value="0">
  <input type="hidden" name="sort" value="relevance">
  <div class="input-group flex-nowrap">
    <button id="search-box-options" class="btn btn-sm atom-btn-secondary dropdown-toggle" type="button"
            data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
      <i class="fas fa-cog" aria-hidden="true"></i>
      <span class="visually-hidden">{{ __('Search options') }}</span>
    </button>
    <div class="dropdown-menu mt-2" aria-labelledby="search-box-options">
      <a class="dropdown-item" href="/display/browse?showAdvanced=true&topLevel=0">
        {{ __('Advanced search') }}
      </a>
    </div>
    <input id="search-box-input" class="form-control form-control-sm" type="search" name="query"
           autocomplete="off" placeholder="{{ __('Search') }}" aria-label="{{ __('Search') }}">
    <button class="btn btn-sm atom-btn-secondary" type="submit">
      <i class="fas fa-search" aria-hidden="true"></i>
      <span class="visually-hidden">{{ __('Search') }}</span>
    </button>
  </div>
</form>
