<x-app-layout>
    <x-slot:title>Undeclared Farms</x-slot:title>
    {{-- FarmAssociationUndeclaredController: farms whose association question has no answer yet, per barangay. Tick farms
         of one barangay and "Set association" to answer for them (marked "Set by MAO"; the farmer can change it; no
         notification). --}}
    <div class="mfd itd itp">
        <x-it.page-header kicker="Municipal Agriculture Office" title="Undeclared Farms" lede="Farms whose farmer has not said yet whether the farm is part of a farmers' association. You can answer for several farms of one barangay at once; the farmer sees “Set by MAO” and can still change it." />

        @include('partials.manuscript-tabs', ['group' => 'farmers', 'label' => 'Farmers'])

        <section class="itp-section" aria-labelledby="udCountsTitle">
            <div class="itp-section-head"><h2 id="udCountsTitle">By barangay</h2></div>
            @if ($counts->isEmpty())
                <div class="fad-empty" role="status"><p class="fad-lead">No farms in your barangays yet.</p></div>
            @else
                <div class="table-responsive mfd-card p-0">
                    <table class="table table-sm align-middle mb-0" data-undeclared-counts>
                        <thead><tr><th>Barangay</th><th class="text-end">Declared</th><th class="text-end">Not declared</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($counts as $row)
                                <tr data-barangay-count="{{ $row['barangay'] }}">
                                    <td>{{ $row['barangay'] }}</td>
                                    <td class="text-end">{{ number_format($row['declared']) }}</td>
                                    <td class="text-end"><strong>{{ number_format($row['undeclared']) }}</strong></td>
                                    <td class="text-end">@if ($row['undeclared'] > 0)<a href="{{ route('mao.farm-associations.undeclared', ['barangay' => $row['barangay']]) }}">Show</a>@endif</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <form class="itd-filters my-3" method="GET" action="{{ route('mao.farm-associations.undeclared') }}">
            <label>Barangay
                <select name="barangay" onchange="this.form.submit()">
                    <option value="">All</option>
                    @foreach ($barangays as $name)<option value="{{ $name }}" @selected($barangay === $name)>{{ $name }}</option>@endforeach
                </select>
            </label>
            <noscript><button class="btn btn-primary btn-sm" type="submit">Apply</button></noscript>
        </form>

        <section class="itp-section" aria-labelledby="udListTitle">
            <div class="itp-section-head">
                <h2 id="udListTitle">Not declared{{ $barangay ? ' in '.$barangay : '' }} <span class="ic-tab-count">{{ number_format($farms->total()) }}</span></h2>
                <p>{{ $barangay ? 'Tick the farms, then choose their association.' : 'Choose one barangay to set associations: a farm can only join an association of its own barangay.' }}</p>
            </div>

            @if ($farms->isEmpty())
                <div class="fad-empty" role="status"><p class="fad-lead">Every farm here has an answer.</p></div>
            @else
                <form method="POST" action="{{ route('mao.farm-associations.undeclared.update') }}" data-loading="true" data-undeclared-form>
                    @csrf
                    @if ($canSet)
                        <div class="d-flex flex-wrap align-items-end gap-2 mb-3" data-undeclared-set>
                            <label class="form-label small mb-0">Set association
                                <select class="form-select form-select-sm" name="answer" required>
                                    <option value="">Choose…</option>
                                    @foreach ($choices as $choice)<option value="org:{{ $choice['id'] }}">{{ $choice['name'] }}</option>@endforeach
                                    <option value="not_member">Not a member</option>
                                </select>
                            </label>
                            <button class="btn btn-primary btn-sm" type="submit" data-loading-text="Saving...">Set for ticked farms</button>
                        </div>
                    @elseif ($barangay === null)
                        <p class="small text-muted mb-3" data-undeclared-filter-first>Filter the list to one barangay first to set an association.</p>
                    @endif
                    <div class="table-responsive mfd-card p-0">
                        <table class="table table-sm align-middle mb-0" data-undeclared-farms>
                            <thead><tr>
                                <th>@if ($canSet)<input type="checkbox" class="form-check-input" aria-label="Tick every farm on this page" data-undeclared-all>@endif</th>
                                <th>Farmer</th><th>Farm</th><th>Barangay</th><th>Farm type</th><th>Size</th>
                            </tr></thead>
                            <tbody>
                                @foreach ($farms as $farm)
                                    <tr data-undeclared-farm="{{ $farm->id }}">
                                        <td>@if ($canSet)<input type="checkbox" class="form-check-input" name="farm_ids[]" value="{{ $farm->id }}" aria-label="Tick {{ $farm->farm_name }}" @checked(in_array((string) $farm->id, (array) old('farm_ids', []), true))>@endif</td>
                                        <td>{{ $farm->user?->name ?? '—' }}</td>
                                        <td>{{ $farm->farm_name }}</td>
                                        <td>{{ $farm->barangay ?: '—' }}</td>
                                        <td>{{ $farm->farm_type ?: '—' }}</td>
                                        <td>{{ \App\Support\FarmSize::describe($farm)['text'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </form>
                <div class="mt-3">{{ $farms->links() }}</div>
            @endif
        </section>
    </div>
    <script>
        (() => {
            const all = document.querySelector('[data-undeclared-all]');
            if (!all) return;
            all.addEventListener('change', () => {
                document.querySelectorAll('[data-undeclared-form] input[name="farm_ids[]"]').forEach((box) => { box.checked = all.checked; });
            });
        })();
    </script>
</x-app-layout>
