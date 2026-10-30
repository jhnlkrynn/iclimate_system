{{-- Who it’s for (surface): all three user roles side by side so visitors can compare them at a glance. Information only:
     no hover state. --}}
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
<section id="roles" data-section aria-labelledby="roles-title" class="lp-section lp-section-surface">
    <div class="lp-wrap">
        <x-landing-heading id="roles-title" eyebrow="Who it’s for" lead="Each account sees the tools for its own work.">
            One system,<br class="max-sm:hidden"> three kinds of account
        </x-landing-heading>

        <div class="mt-12 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($roles as $key => $role)
                <article data-reveal class="lp-role-card lp-card flex h-full flex-col sm:p-8">
                    <h3 class="lp-card-title">{{ $role['title'] }}</h3>
                    <p class="mt-3 text-base leading-relaxed text-muted">{{ $role['text'] }}</p>
                    <ul class="mt-6 flex list-disc flex-col gap-2 border-t border-line pl-5 pt-6 marker:text-sage-500">
                        @foreach ($role['features'] as $feature)
                            <li class="text-base leading-snug text-ink">{{ $feature }}</li>
                        @endforeach
                    </ul>
                </article>
            @endforeach
        </div>
    </div>
</section>
