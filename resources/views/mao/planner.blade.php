<x-app-layout>
    {{-- MAO Planner (MaoPlannerController): the MAO's activities as a list sorted by date (not a calendar grid), an Add
         Activity form, and the farmers' planting and harvest dates as a second, read-only list. Staff design system
         (.mfd / .itd / .itp, it-pages.css). The page name follows the MAO's saved language (lang/*/mao.php). --}}
    <div class="mfd itd itp mpl">
        <x-it.page-header kicker="Municipal Agriculture Office" :title="__('mao.planner.title', [], $locale)" lede="Plan MAO activities by date and barangay, and see what farmers planted and harvested." />

        <details class="ic-collapse" @if ($errors->any()) open @endif>
            <summary><span class="ic-collapse-title">+ Add Activity</span></summary>
            <div class="mfd-card">@include('mao._activity-form', ['activity' => null, 'prefix' => 'new'])</div>
        </details>

        <section class="itp-section" aria-labelledby="mplActivitiesTitle">
            <div class="itp-section-head">
                <h2 id="mplActivitiesTitle">MAO Activities</h2>
                <p>Sorted by date. An activity for All Barangays shows in every barangay's list.</p>
            </div>
            @if (! $activitiesAvailable)
                <div class="fad-empty is-watch" role="status">
                    <p class="fad-lead">MAO planning is temporarily unavailable.</p>
                    <p class="fad-body">The planning database update has not reached this environment yet. Farmer activity records remain available below.</p>
                </div>
            @else
            <form class="itd-filters" method="GET" action="{{ route('mao.planner.index') }}">
                <label>Barangay
                    <select name="barangay" onchange="this.form.submit()">
                        <option value="">All</option>
                        @foreach ($barangays as $barangay)<option value="{{ $barangay }}" @selected(($filters['barangay'] ?? '') === $barangay)>{{ $barangay }}</option>@endforeach
                    </select>
                </label>
                <label>Activity type
                    <select name="type" onchange="this.form.submit()">
                        <option value="">All</option>
                        @foreach ($types as $type)<option value="{{ $type }}" @selected(($filters['type'] ?? '') === $type)>{{ $type }}</option>@endforeach
                    </select>
                </label>
                @if (! empty($filters['farmer_barangay']))<input type="hidden" name="farmer_barangay" value="{{ $filters['farmer_barangay'] }}">@endif
                <noscript><button class="btn btn-primary btn-sm" type="submit">Apply</button></noscript>
            </form>

            @forelse ($activities as $activity)
                <article class="mfd-card mpl-item" data-mao-activity="{{ $activity->id }}">
                    <div class="mpl-date" aria-hidden="true"><strong>{{ $activity->starts_on->format('j') }}</strong><span>{{ $activity->starts_on->format('M Y') }}</span></div>
                    <div class="mpl-body">
                        <h3 class="itp-card-title">{{ $activity->summary() }}</h3>
                        <p class="mpl-title">{{ $activity->title }}</p>
                        @if (filled($activity->notes))<p class="mpl-notes">{{ $activity->notes }}</p>@endif
                        <p class="mpl-by">Added by {{ $activity->user?->name ?? 'MAO' }}</p>
                    </div>
                    @if ($activity->user_id === auth()->id())
                        <div class="mpl-actions">
                            <details class="mpl-edit">
                                <summary class="btn btn-outline-primary btn-sm">Edit</summary>
                                @include('mao._activity-form', ['activity' => $activity, 'prefix' => 'edit'.$activity->id])
                            </details>
                            <form method="POST" action="{{ route('mao.planner.destroy', $activity) }}" data-loading="true" data-ic-confirm="Delete this activity? This cannot be undone.">
                                @csrf @method('DELETE')
                                <button class="btn btn-outline-danger btn-sm" type="submit" data-loading-text="Deleting...">Delete</button>
                            </form>
                        </div>
                    @endif
                </article>
            @empty
                <x-no-data note="No MAO activity matches. Use Add Activity to plan one." />
            @endforelse
            @if ($activities->hasPages())<div>{{ $activities->links() }}</div>@endif
            @endif
        </section>

        <section class="itp-section" aria-labelledby="mplFarmerTitle">
            <div class="itp-section-head">
                <h2 id="mplFarmerTitle">Farmer Activities</h2>
                <p>What farmers planted and harvested, from their own records. View only.</p>
            </div>
            <form class="itd-filters" method="GET" action="{{ route('mao.planner.index') }}#mplFarmerTitle">
                <label>Barangay
                    <select name="farmer_barangay" onchange="this.form.submit()">
                        <option value="">All</option>
                        @foreach ($barangays as $barangay)<option value="{{ $barangay }}" @selected(($filters['farmer_barangay'] ?? '') === $barangay)>{{ $barangay }}</option>@endforeach
                    </select>
                </label>
                @foreach (['barangay', 'type'] as $kept)@if (! empty($filters[$kept]))<input type="hidden" name="{{ $kept }}" value="{{ $filters[$kept] }}">@endif @endforeach
                <noscript><button class="btn btn-primary btn-sm" type="submit">Apply</button></noscript>
            </form>

            @if ($farmerActivities->isEmpty())
                <x-no-data note="No farmer has saved a planting or harvest date for this barangay yet." />
            @else
                <div class="itd-table-wrap">
                    <table class="itd-table" data-farmer-activities>
                        <thead><tr><th>Date</th><th>Activity</th><th>Farmer</th><th>Barangay</th><th>Farm</th><th class="itd-num">Area</th></tr></thead>
                        <tbody>
                            @foreach ($farmerActivities as $row)
                                <tr>
                                    <td class="itd-nowrap">{{ $row['date']->format('M j, Y') }}</td>
                                    <td><span class="mfd-pill {{ $row['kind'] === 'Planted' ? 'is-good' : ($row['kind'] === 'Harvested' ? 'is-info' : 'is-unk') }}">{{ $row['kind'] }}</span></td>
                                    <td>{{ $row['farmer'] }}</td>
                                    <td>{{ $row['barangay'] }}</td>
                                    <td>{{ $row['farm'] }}</td>
                                    <td class="itd-num">@if ($row['hectares']){{ rtrim(rtrim(number_format($row['hectares'], 2), '0'), '.') }} ha @endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    <style>
        .mpl-form { display: grid; gap: 16px; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr)); margin: 0; }
        .mpl-field { display: grid; gap: 6px; min-width: 0; }
        .mpl-field.is-wide { grid-column: 1 / -1; }
        .mpl-item { display: grid; grid-template-columns: 72px minmax(0, 1fr); gap: 12px 16px; align-items: start; }
        .mpl-date { display: grid; justify-items: center; padding: 8px 4px; border: 1px solid var(--rule); border-radius: 6px; background: var(--subtle); }
        .mpl-date strong { font-size: 1.75rem; font-weight: 700; line-height: 1.1; }
        .mpl-date span { font-size: .8125rem; color: var(--muted); }
        .mpl-body { display: grid; gap: 4px; min-width: 0; }
        .mpl-title { font-weight: 600; }
        .mpl-notes { white-space: pre-line; color: var(--muted); }
        .mpl-by { font-size: .8125rem !important; color: var(--muted); }
        .mpl-actions { grid-column: 1 / -1; display: flex; flex-wrap: wrap; align-items: flex-start; gap: 8px; }
        .mpl-edit[open] { flex: 1 1 100%; }
        .mpl-edit > summary { list-style: none; cursor: pointer; }
        .mpl-edit > summary::-webkit-details-marker { display: none; }
        .mpl-edit[open] > summary { margin-bottom: 12px; }
    </style>
</x-app-layout>
