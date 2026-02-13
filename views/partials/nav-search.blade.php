{{--
  Search box — global site search form.
  Matches the search component rendered by ahgThemeB5Plugin.
--}}
<form class="d-flex flex-grow-1 my-2 my-lg-0 mx-lg-2" action="/search" method="get" role="search">
  <div class="input-group">
    <input class="form-control" type="search" name="query" placeholder="{{ __('Search') }}" aria-label="{{ __('Search') }}">
    <button class="btn btn-outline-light" type="submit">
      <i class="fas fa-search" aria-hidden="true"></i>
      <span class="visually-hidden">{{ __('Search') }}</span>
    </button>
  </div>
</form>
