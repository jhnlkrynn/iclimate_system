{{-- A farmer in the MAO's "View" pop-up (FarmerProfileController@show, ?modal=1; resources/js/info-modal.js): details and
     farms. No contact number or address, and a field with nothing in it is left out (never "—" or "N/A").
     [Mensahe] opens the existing Messages conversation with this farmer inside the pop-up (crud/index.blade.php). --}}
@php
    $farms = $record->farms->whereNull('archived_at')->values();
    $hectares = fn ($value): ?string => (float) $value > 0 ? rtrim(rtrim(number_format((float) $value, 2), '0'), '.').' ha' : null;
    $details = array_filter([
        'Barangay' => $record->barangay,
        // Panel revision 1.1: every organization the farmer belongs to.
        'Organizations' => $record->organization_names,
        'Farm type' => $record->farm_type,
        'Total farm area' => $record->total_farm_area,
        'Status' => $record->account_status,
        'RSBSA no.' => $record->rsbsa_no,
    ], fn ($value) => filled($value));
@endphp
<header class="fad-m-head ic-m-head">
    <span class="ic-m-icon" aria-hidden="true">@include('dashboards.mao.icon', ['name' => 'farmers'])</span>
    <div class="fad-m-head-text">
        <p class="fad-label">Farmer Profile</p>
        <h2 id="icModalTitle" tabindex="-1">{{ $record->full_name }}</h2>
    </div>
    <button type="button" class="fad-m-x" data-ic-close aria-label="Close farmer profile"><svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M6 6l12 12M18 6 6 18"/></svg></button>
</header>
<div class="fad-m-scroll">
    @if ($details)
        <section class="fad-m-section" aria-labelledby="fpDetails">
            <h3 id="fpDetails">Details</h3>
            <dl class="fad-m-details">
                @foreach ($details as $label => $value)
                    <div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>
                @endforeach
            </dl>
            @include('partials.record-stamps', ['record' => $record])
        </section>
    @endif
    @if ($farms->isNotEmpty())
        <section class="fad-m-section" aria-labelledby="fpFarms" data-farmer-farms>
            <h3 id="fpFarms">{{ $farms->count() === 1 ? 'Farm' : 'Farms ('.$farms->count().')' }}</h3>
            <ul class="fp-farms">
                @foreach ($farms as $farm)
                    <li>
                        <strong>{{ $farm->farm_name ?: 'Farm '.$loop->iteration }}</strong>
                        <span>{{ implode(' · ', array_filter([$farm->barangay, $farm->farm_type, $hectares($farm->farm_size_hectares)])) }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
<footer class="fad-m-foot ic-m-foot">
    <div class="ic-m-actions">
        @if ($record->user_id)
            <button type="button" class="fad-btn" data-mensahe="{{ $record->user_id }}" data-mensahe-name="{{ $record->full_name }}">@include('dashboards.mao.icon', ['name' => 'message'])Mensahe</button>
        @endif
        @if ($canManage)
            <a class="fad-btn is-quiet" href="{{ route('farmer-profiles.edit', $record) }}">Edit</a>
        @endif
    </div>
    <button type="button" class="fad-btn is-quiet" data-ic-close>Close</button>
</footer>
