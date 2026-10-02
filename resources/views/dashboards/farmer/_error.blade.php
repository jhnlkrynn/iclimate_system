{{-- A card's own "service is down" state: the other cards keep working. $retry = the slot to rebuild, $title = the card title, $reason = optional extra line. --}}
<section class="mfd-card mfd-card-state" aria-labelledby="mfdErr{{ $retry }}" data-mfd-state="error">
    <div class="mfd-card-head"><h2 id="mfdErr{{ $retry }}">{{ $title }}</h2></div>
    <div class="mfd-empty is-error" role="status">
        @include('dashboards.mao.icon', ['name' => 'danger'])
        <div>
            <p><strong>{{ __('farmer.state.error') }}</strong></p>
            @if (! empty($reason))<p class="mfd-muted">{{ $reason }}</p>@endif
        </div>
        <button type="button" class="mfd-btn is-quiet" data-mfd-retry="{{ $retry }}">@include('dashboards.mao.icon', ['name' => 'refresh']){{ __('farmer.state.retry') }}</button>
    </div>
</section>
