{{-- Typhoon Safety Check-Ins for MAO and IT (moved here from the former Operations page, 2026-10). Farmers answer on
     their Home page while a typhoon advisory is in effect; the answers are counted here, and those who need help
     are listed. Outside a typhoon there is nothing to answer, and the section says so. --}}
<section class="adv-section adv-checkins" id="check-ins" aria-labelledby="advCheckInsTitle" data-adv-checkins>
    <div class="fad-list-head">
        <div>
            <h2 id="advCheckInsTitle">Typhoon Safety Check-Ins</h2>
            @if ($checkIns['event'])
                <p class="fad-support">"{{ $checkIns['event']['title'] }}" · {{ $checkIns['event']['severity'] }}</p>
            @endif
        </div>
    </div>
    @if (! $checkIns['event'])
        <p class="fad-support" data-adv-checkins-closed>No typhoon advisory is in effect, so no check-in is open.</p>
    @else
        <dl class="adv-checkin-counts">
            <div class="is-low"><dt>Safe</dt><dd>{{ number_format($checkIns['safe']) }}</dd></div>
            <div class="is-high"><dt>Needs Help</dt><dd data-adv-needs-help>{{ number_format($checkIns['needs_help']->count()) }}</dd></div>
            <div class="is-moderate"><dt>No response yet</dt><dd>{{ number_format($checkIns['no_response']) }}</dd></div>
        </dl>
        @if ($checkIns['needs_help']->isNotEmpty())
            <ul class="adv-list">
                @foreach ($checkIns['needs_help'] as $response)
                    <li class="adv-item is-high">
                        <div class="adv-top">
                            <span class="adv-sev is-high">Needs Help</span>
                            <span class="adv-area">{{ $response->farmer?->name ?? 'Unknown farmer' }}</span>
                            <span class="adv-valid">{{ $response->farmer?->registeredBarangay() ?? 'Barangay not on file' }}</span>
                        </div>
                        <p class="adv-rec">{{ $response->note ?: 'No note was added.' }}</p>
                        <div class="adv-foot">
                            <span class="adv-source">Checked in {{ $response->responded_at?->diffForHumans() }}</span>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    @endif
</section>
