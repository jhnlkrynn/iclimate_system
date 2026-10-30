{{-- Who uses iClimate: all three user roles are visible together so visitors can compare them at a glance. --}}
@php
    $roles = [
        'farmers' => [
            'title' => 'Farmer',
            'text' => 'Check conditions, get planting advice and risk alerts, and see seasonal information on the Farmer Dashboard.',
            'features' => ['Climate monitoring', 'Planting guidance', 'Weather risk alerts', 'Notifications', 'Seasonal reports'],
        ],
        'mao' => [
            'title' => 'MAO Staff',
            'text' => 'Manage agricultural records, follow rice production, compare climate and yield, and prepare reports for farmers and the municipality on the MAO Staff Dashboard.',
            'features' => ['Production monitoring', 'Climate analytics', 'Report generation', 'Record validation', 'Heat map monitoring'],
        ],
        'it' => [
            'title' => 'IT Personnel',
            'text' => 'Support user access, keep records organized, watch system activity, and help the MAO keep iClimate secure and dependable from the IT Personnel Dashboard.',
            'features' => ['User access support', 'Activity checks', 'Record upkeep', 'Account support', 'System reliability'],
        ],
    ];
@endphp
<section id="roles" data-section aria-labelledby="roles-title" class="lp-screen py-14 sm:py-16 lg:py-20">
    <div class="lp-wrap"><div class="lp-panel">
        <h2 id="roles-title" data-reveal class="lp-h2">Who uses iClimate</h2>
        <p data-reveal style="--reveal-delay: 60ms" class="mt-3 text-[1.0625rem] leading-[1.4] text-muted">Each account sees the tools for its own work.</p>

        <div class="mt-8 grid gap-5 sm:grid-cols-2 lg:grid-cols-3 lg:gap-6">
            @foreach ($roles as $key => $role)
                <article data-reveal class="lp-role-card flex h-full flex-col rounded-2xl border border-line bg-white p-6 shadow-sm sm:p-7">
                    <h3 class="text-[1.5rem] font-semibold leading-[1.2] tracking-[-0.015em] text-ink sm:text-[1.625rem]">{{ $role['title'] }}</h3>
                    <p class="mt-4 text-[1.0625rem] leading-[1.5] text-muted">{{ $role['text'] }}</p>
                    <ul data-reveal-group class="mt-6 flex flex-col gap-3">
                        @foreach ($role['features'] as $feature)
                            <li data-reveal class="flex items-start gap-2.5 text-base leading-[1.4] text-ink">
                                <x-icon name="check" class="mt-0.5 h-[18px] w-[18px] shrink-0 text-brand" stroke-width="3" />
                                {{ $feature }}
                            </li>
                        @endforeach
                    </ul>
                </article>
            @endforeach
        </div>
    </div></div>
</section>
