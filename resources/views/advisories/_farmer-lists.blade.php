@if($advisoryCheck['unavailable'])
    <div class="fad-empty" role="status">
        <p class="fad-lead">Latest Advisories Temporarily Unavailable</p>
        <p class="fad-body">We could not check the latest online advisories. Saved advisories are still shown.</p>
        <p class="fad-support">Last successful check: {{ $advisoryCheck['last_successful_check']?->copy()->timezone(\App\Support\BusinessClock::timezone())->format('g:i A') ?? 'Not available' }}</p>
        <form method="POST" action="{{ route('planting-advisories.refresh-pagasa') }}" data-loading="true" data-fad-refresh>
            @csrf
            <button class="fad-btn is-quiet" type="submit" data-loading-text="Trying again...">Try Again</button>
        </form>
    </div>
@endif

<section class="fad-list" aria-labelledby="myBarangayAdvisoriesTitle">
    <div class="fad-list-head">
        <div>
            <h2 id="myBarangayAdvisoriesTitle">My Barangay</h2>
            @if($farmerBarangay)
                <p class="fad-support">{{ $farmerBarangay }}</p>
            @else
                <p class="fad-support">Add your barangay to receive local advisories.</p>
                <a class="fad-btn is-quiet is-compact" href="{{ route('profile.edit') }}">Update Profile</a>
            @endif
        </div>
        @if($myBarangayAdvisories->count())<p class="fad-support">{{ $myBarangayAdvisories->count() }} {{ $myBarangayAdvisories->count() === 1 ? 'advisory' : 'advisories' }}</p>@endif
    </div>
    @if($myBarangayAdvisories->count())
        <ul class="fad-cards">
            @foreach($myBarangayAdvisories as $advisory)
                @include('advisories._farmer-card', ['advisory' => $advisory])
            @endforeach
        </ul>
    @else
        <div class="fad-empty"><p class="fad-lead">{{ $farmerBarangay ? 'No Active Advisories' : 'Complete Your Farm Location' }}</p><p class="fad-body">{{ $farmerBarangay ? 'There are no weather or farming advisories for your barangay right now.' : 'Add your barangay so iClimate can show advisories for your area.' }}</p>@if($farmerBarangay)<p class="fad-support">Last checked: {{ $advisoryCheck['checked_at']?->copy()->timezone(\App\Support\BusinessClock::timezone())->format('g:i A') ?? 'Not checked yet' }}</p>@endif</div>
    @endif
</section>

<section class="fad-list" aria-labelledby="lianWideAdvisoriesTitle">
    <div class="fad-list-head">
        <div>
            <h2 id="lianWideAdvisoriesTitle">Lian-wide Advisories</h2>
            <p class="fad-support">Active advice for Lian, Batangas.</p>
        </div>
        @if($lianWideAdvisories->count())<p class="fad-support">{{ $lianWideAdvisories->count() }} {{ $lianWideAdvisories->count() === 1 ? 'advisory' : 'advisories' }}</p>@endif
    </div>
    @if($lianWideAdvisories->count())
        <ul class="fad-cards">
            @foreach($lianWideAdvisories as $advisory)
                @include('advisories._farmer-card', ['advisory' => $advisory])
            @endforeach
        </ul>
    @else
        <div class="fad-empty"><p class="fad-lead">No Active Advisories for Lian</p><p class="fad-body">There are no active Lian-wide advisories right now.</p><p class="fad-support">Last checked: {{ $advisoryCheck['checked_at']?->copy()->timezone(\App\Support\BusinessClock::timezone())->format('g:i A') ?? 'Not checked yet' }}</p></div>
    @endif
</section>
