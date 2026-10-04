{{-- The one "View" pop-up of the farmer pages (Advisories, Climate Records, the dashboard's Latest Farming Advice).
     A link marked data-fad-view opens its own page inside this shell (resources/js/farmer-advisories.js fetches it with
     ?modal=1); without JavaScript the link simply opens the page. Keep it outside .fad / .mfd: those are size containers
     and would trap a fixed-position pop-up.
     $label = small heading while loading, $thing = the word used in "Loading … details" and the close button. --}}
@php
    $label = $label ?? 'Advisory';
    $thing = $thing ?? 'advisory';
@endphp
<div class="fad-modal" data-fad-modal hidden>
    <div class="fad-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="fadModalTitle" data-fad-dialog tabindex="-1"></div>
    <template id="fadModalLoading">
        <header class="fad-m-head">
            <div class="fad-m-head-text">
                <p class="fad-label">{{ $label }}</p>
                <h2 id="fadModalTitle" tabindex="-1">Loading {{ $thing }} details…</h2>
            </div>
            <button type="button" class="fad-m-x" data-fad-close aria-label="Close {{ $thing }}"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
        </header>
        <div class="fad-m-scroll" aria-busy="true">
            <div class="fad-skel is-wide"></div><div class="fad-skel"></div><div class="fad-skel is-short"></div>
            <div class="fad-skel is-block"></div>
        </div>
    </template>
    <template id="fadModalError">
        <header class="fad-m-head">
            <div class="fad-m-head-text">
                <p class="fad-label">{{ $label }}</p>
                <h2 id="fadModalTitle" tabindex="-1">Unable to load {{ $thing }} details.</h2>
            </div>
            <button type="button" class="fad-m-x" data-fad-close aria-label="Close {{ $thing }}"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
        </header>
        <div class="fad-m-scroll">
            <div class="fad-m-note is-danger" role="alert"><strong>Something went wrong.</strong><span>Please check your connection and try again.</span></div>
        </div>
        <footer class="fad-m-foot">
            <button type="button" class="fad-btn is-primary" data-fad-retry>Retry</button>
            <button type="button" class="fad-btn is-quiet" data-fad-close>Close</button>
        </footer>
    </template>
</div>
