{{-- The frame every farmer dashboard pop-up shares: dimmed page, icon + title + description, X, a scrolling body and a
     Cancel / save footer. The slot is the pop-up's own fields. Built from the same parts as the Calendar and Advisories
     pop-ups (.fad-modal / .fad-m-* in farmer-advisories.css); opened, saved and closed by resources/js/farmer-dashboard.js,
     which shows the one named by the clicked button's data-mfd-popup. --}}
@props(['name', 'title', 'intro', 'icon', 'action', 'save', 'saving', 'size' => 'sm'])
<div data-mfd-popup-modal="{{ $name }}" {{ $attributes->class(['fad-modal', 'mfd-pm', 'is-'.$size]) }} id="mfdPopup-{{ $name }}" hidden
    data-text-save="{{ $save }}" data-text-saving="{{ $saving }}" data-text-failed="{{ __('farmer.popup.failed') }}">
    <div class="fad-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="mfdPopupTitle-{{ $name }}" aria-describedby="mfdPopupIntro-{{ $name }}" tabindex="-1">
        <header class="fad-m-head">
            <span class="mfd-pm-icon" aria-hidden="true">@include('dashboards.mao.icon', ['name' => $icon])</span>
            <div class="fad-m-head-text">
                <h2 id="mfdPopupTitle-{{ $name }}" tabindex="-1">{{ $title }}</h2>
                <p class="mfd-pm-intro" id="mfdPopupIntro-{{ $name }}">{{ $intro }}</p>
            </div>
            <button type="button" class="fad-m-x" data-mfd-popup-close aria-label="{{ __('farmer.popup.close') }}">
                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg>
            </button>
        </header>

        {{-- data-fast-submit-bound: the layout's "page is leaving" fade must not start, this form never leaves the page. --}}
        <form method="POST" action="{{ $action }}" data-mfd-popup-form data-fast-submit-bound="true" novalidate>
            @csrf
            <div class="fad-m-scroll">
                <p class="mfd-pm-alert" role="alert" data-mfd-popup-alert hidden></p>
                {{ $slot }}
            </div>
            <footer class="fad-m-foot">
                <button type="button" class="fad-btn is-quiet" data-mfd-popup-close>{{ __('farmer.popup.cancel') }}</button>
                <button type="submit" class="fad-btn" data-mfd-popup-save>{{ $save }}</button>
            </footer>
        </form>
    </div>
</div>
