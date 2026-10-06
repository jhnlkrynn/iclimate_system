{{-- 1. "Mensahe Ngayon": the one main message for today (FarmerDashboardService::message). --}}
@php $m = $f['message']; @endphp
<section class="fh-message is-{{ $m['tone'] }}" aria-labelledby="fhMessageTitle" data-fh-message="{{ $m['key'] }}">
    <span class="fh-message-icon" aria-hidden="true">@include('dashboards.mao.icon', ['name' => $m['icon']])</span>
    <div>
        <h2 id="fhMessageTitle" class="fh-kicker">{{ __('farmer.home.message.title') }}</h2>
        <p class="fh-message-text">{{ $m['text'] }}</p>
    </div>
</section>
