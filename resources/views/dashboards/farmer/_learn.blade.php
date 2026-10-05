{{-- "What does this mean?": a small ? button that opens the shared iClimate pop-up (info-modal.js, the Advisories style)
     with two short answers. $id = a unique template id, $title, $meaning, $todo. --}}
<button type="button" class="mfd-learn" data-ic-template="{{ $id }}" data-ic-title="{{ $title }}" data-ic-kicker="{{ __('farmer.learn.kicker') }}"
    data-ic-icon="info" data-ic-size="compact" data-ic-close-label="{{ __('farmer.popup.close') }}" data-no-loader
    aria-haspopup="dialog" aria-label="{{ __('farmer.today.learn_more') }} {{ $title }}" title="{{ __('farmer.today.learn_more') }}">?</button>
<template id="{{ $id }}">
    <div class="fad-m-section"><h3>{{ __('farmer.learn.meaning') }}</h3><p class="fad-m-body">{{ $meaning }}</p></div>
    <div class="fad-m-section"><h3>{{ __('farmer.learn.todo') }}</h3><p class="fad-m-body">{{ $todo }}</p></div>
</template>
