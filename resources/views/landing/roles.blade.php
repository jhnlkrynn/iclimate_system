{{-- Who uses iClimate (Figma: Who uses iClimate): one tab per role; resources/js/landing.js switches the panels. --}}
@php
    $roles = [
        'farmers' => [
            'tab' => 'Rice farmers',
            'text' => 'Check conditions, get planting advice and risk alerts, and see seasonal information on a simple dashboard.',
            'features' => ['Climate monitoring', 'Planting guidance', 'Weather risk alerts', 'Notifications', 'Seasonal reports'],
            'button' => 'Farmer log in',
        ],
        'mao' => [
            'tab' => 'MAO personnel',
            'text' => 'Manage agricultural records, follow rice production, compare climate and yield, and prepare reports for farmers and the municipality.',
            'features' => ['Production monitoring', 'Climate analytics', 'Report generation', 'Record validation', 'Heat map monitoring'],
            'button' => 'MAO log in',
        ],
        'it' => [
            'tab' => 'IT personnel',
            'text' => 'Support user access, keep records organized, watch system activity, and help the MAO keep iClimate secure and dependable.',
            'features' => ['User access support', 'Activity checks', 'Record upkeep', 'Account support', 'System reliability'],
            'button' => 'IT log in',
        ],
    ];
@endphp
<section id="roles" data-section aria-labelledby="roles-title" class="lp-screen bg-surface py-16 lg:py-20">
    <div class="lp-wrap">
        <h2 id="roles-title" data-reveal class="lp-h2">Who uses iClimate</h2>
        <p data-reveal style="--reveal-delay: 60ms" class="mt-3 text-[1.0625rem] leading-[1.4] text-muted">Each account sees the tools for its own work.</p>

        <div role="tablist" aria-label="Who uses iClimate" class="mt-10 flex w-fit max-w-full overflow-x-auto border-b border-line" data-tabs>
            @foreach ($roles as $key => $role)
                <button type="button" role="tab" id="role-tab-{{ $key }}" aria-controls="role-panel-{{ $key }}"
                        aria-selected="{{ $loop->first ? 'true' : 'false' }}" tabindex="{{ $loop->first ? '0' : '-1' }}"
                        class="-mb-px min-h-11 shrink-0 whitespace-nowrap rounded-t border-b-2 border-transparent px-2 py-1.5 text-base leading-[1.4] text-muted transition-colors hover:text-ink aria-selected:border-ink aria-selected:text-ink">
                    {{ $role['tab'] }}
                </button>
            @endforeach
        </div>

        @foreach ($roles as $key => $role)
            <div role="tabpanel" id="role-panel-{{ $key }}" aria-labelledby="role-tab-{{ $key }}" tabindex="0" @unless ($loop->first) hidden @endunless
                 class="mt-6 rounded-2xl border border-line bg-white p-6 sm:p-10">
                {{-- TODO: add a screenshot of this role's dashboard beside the details once one is available (Figma: Screenshot · farmer dashboard). --}}
                <div class="flex max-w-[490px] flex-col items-start gap-5">
                    <h3 class="text-[1.75rem] font-semibold leading-[1.2] tracking-[-0.015em] text-ink">{{ $role['tab'] }}</h3>
                    <p class="text-[1.0625rem] leading-[1.4] text-muted">{{ $role['text'] }}</p>
                    <ul data-reveal-group class="flex flex-col gap-2.5">
                        @foreach ($role['features'] as $feature)
                            <li data-reveal class="flex items-center gap-2.5 text-base leading-[1.4] text-ink">
                                <x-icon name="check" class="h-[18px] w-[18px] text-brand" stroke-width="3" />
                                {{ $feature }}
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('login') }}" class="lp-btn-primary" data-role-login>{{ $role['button'] }}</a>
                </div>
            </div>
        @endforeach
    </div>
</section>
