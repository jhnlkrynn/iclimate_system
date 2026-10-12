{{-- Panel revision 1.4: live search box. Results appear as you type (300 ms after the last key), match partly and in any
     letter case, show the name with key details, highlight the matched text, and say "No results found" when empty.
     Large tap targets; on a phone the box scrolls to the top so the list is not hidden behind the keyboard.
     Behaviour: resources/js/panel-revisions.js ([data-live-search]).

     $url  JSON endpoint (?q=…) returning {results: [{title, meta, badge, url, modal}]}
     $id, $label, $placeholder, $class (optional) --}}
@php $lsId = $id ?? 'liveSearch'; @endphp
<div class="live-search {{ $class ?? '' }}" data-live-search="{{ $url }}" data-debounce="300" data-min-chars="1"
     data-text-none="{{ __('farmer.search.no_results') }}" data-text-searching="{{ __('farmer.search.searching') }}">
    <label class="live-search-label" for="{{ $lsId }}">{{ $label ?? __('Search') }}</label>
    <div class="live-search-box">
        <svg class="live-search-icon" width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true"><circle cx="9" cy="9" r="6" stroke="currentColor" stroke-width="1.8"/><path d="m14 14 4 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/></svg>
        <input id="{{ $lsId }}" type="search" inputmode="search" enterkeyhint="search" autocomplete="off" spellcheck="false"
               placeholder="{{ $placeholder ?? __('farmer.search.placeholder') }}" aria-describedby="{{ $lsId }}-status">
        <span class="live-search-spinner" aria-hidden="true"></span>
    </div>
    <ul class="live-search-results" id="{{ $lsId }}-results" role="listbox" hidden data-live-search-results></ul>
    <p class="visually-hidden" id="{{ $lsId }}-status" role="status" aria-live="polite" data-live-search-status></p>
</div>
