<x-app-layout>
    <x-slot:title>Association Reports</x-slot:title>
    <div class="mfd itd itp">
        <x-shared.page-header
            kicker="Municipal Agriculture Office"
            title="Farmers Associations — Rice Yield Monitoring"
            lede="Create reporting periods, assign association chairmen, and review rice yield reports."
        />

        @include('partials.manuscript-tabs', ['group' => 'approvals', 'label' => 'Approvals and reports'])

        @if (session('status'))
            <div class="alert alert-success mb-4" role="status">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger mb-4" role="alert">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="mfd-card mb-4" aria-labelledby="arCreatePeriodTitle">
            <div class="itp-section-head mb-3">
                <h2 id="arCreatePeriodTitle" class="h5 mb-1">Create Reporting Period</h2>
                <p class="mb-0">Open a new yield submission window with a deadline for association chairmen.</p>
            </div>
            <form method="POST" action="{{ route('mao.association-reports.periods.store') }}" class="row g-3 align-items-end">
                @csrf
                <div class="col-md-3">
                    <label for="periodKey" class="form-label">Period key</label>
                    <input id="periodKey" class="form-control" name="period_key" placeholder="e.g. 2026-WS" required maxlength="80">
                </div>
                <div class="col-md-4">
                    <label for="periodLabel" class="form-label">Reporting period</label>
                    <input id="periodLabel" class="form-control" name="label" placeholder="e.g. Wet Season 2026" required maxlength="160">
                </div>
                <div class="col-md-2">
                    <label for="reportYear" class="form-label">Year</label>
                    <input id="reportYear" class="form-control" type="number" name="reporting_year" value="{{ now()->year }}" min="2000" max="2200" required>
                </div>
                <div class="col-md-3">
                    <label for="reportDue" class="form-label">Deadline</label>
                    <input id="reportDue" class="form-control" type="datetime-local" name="due_at" required>
                </div>
                <div class="col-12 d-flex justify-content-start">
                    <button class="btn btn-primary" type="submit" data-loading-text="Creating...">Create reporting period</button>
                </div>
            </form>
        </section>

        @if ($periods->isNotEmpty())
            <form method="GET" action="{{ route('mao.association-reports.index') }}" class="itd-filters mb-4" data-period-picker>
                <label for="periodPicker">Reporting period
                    <select id="periodPicker" name="period" class="form-select" data-autosubmit>
                        @foreach ($periods as $option)
                            <option value="{{ $option->id }}" @selected($period?->id === $option->id)>
                                {{ $option->label }} — due {{ $option->due_at->format('M j, Y') }}{{ $option->due_at->isPast() ? ' (overdue)' : '' }}
                            </option>
                        @endforeach
                    </select>
                </label>
                <noscript><button class="btn btn-primary btn-sm" type="submit">Show</button></noscript>
            </form>
        @endif

        <section class="itp-section" aria-labelledby="arAssociationsTitle">
            <div class="itp-section-head">
                <h2 id="arAssociationsTitle">Associations <span class="ic-tab-count">{{ $associations->count() }}</span></h2>
                <p>{{ $associations->count() }} associations. MAO acceptance is separate from research verification.</p>
            </div>

            <div class="table-responsive mfd-card p-0 ar-table-wrap">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Barangay</th>
                            <th>Association</th>
                            <th>Chairman</th>
                            <th>Report</th>
                            <th class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($associations as $association)
                            @php($report = $association->yieldReports->first())
                            @php($chairman = $association->chairmanAssignments->first()?->user?->name ?? ($association->chairman_name ?: 'Unverified'))
                            @php($canonicalBrgy = \App\Support\LianBarangays::canonicalName($association->barangay) ?? '')
                            @php($barangayFarmers = $farmersByBarangay->get($canonicalBrgy, collect()))
                            <tr>
                                <td>{{ $association->barangay }}</td>
                                <td><strong>{{ $association->name }}</strong></td>
                                <td>
                                    <span @class(['text-muted' => $chairman === 'Unverified'])>{{ $chairman }}</span>
                                </td>
                                <td>
                                    @if ($report)
                                        <span class="mfd-pill is-good">{{ $report->status }}</span>
                                    @else
                                        <span class="mfd-pill is-unk">Not Submitted</span>
                                    @endif
                                </td>
                                <td class="text-end">
                                    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-end">
                                        @if ($report)
                                            <a class="btn btn-outline-primary btn-sm" href="{{ route('mao.association-reports.reports.show', $report) }}">View</a>
                                        @endif
                                        <form method="POST" action="{{ route('mao.association-reports.associations.assign', $association) }}" class="d-inline-flex flex-wrap gap-2 align-items-center justify-content-end">
                                            @csrf
                                            @if ($barangayFarmers->isEmpty())
                                                <span class="small text-muted">No registered farmers in {{ $association->barangay }}</span>
                                            @else
                                                <div class="ar-farmer-combo" data-farmer-combo data-farmers='@json($barangayFarmers)'>
                                                    <input type="hidden" name="user_id" value="" data-farmer-id>
                                                    <label class="visually-hidden" for="chairman-search-{{ $association->id }}">Search farmer in {{ $association->barangay }}</label>
                                                    <input
                                                        id="chairman-search-{{ $association->id }}"
                                                        type="search"
                                                        class="form-control form-control-sm"
                                                        placeholder="Search farmer in {{ $association->barangay }}..."
                                                        data-farmer-input
                                                        style="width: 230px;"
                                                        autocomplete="off"
                                                        role="combobox"
                                                        aria-autocomplete="list"
                                                        aria-expanded="false"
                                                        aria-controls="chairman-list-{{ $association->id }}"
                                                        required
                                                    >
                                                    <ul id="chairman-list-{{ $association->id }}" class="ar-farmer-menu" role="listbox" hidden></ul>
                                                </div>
                                                <button class="btn btn-primary btn-sm" type="submit">Assign chairman</button>
                                            @endif
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <style>
        .ar-table-wrap { overflow: visible !important; }
        @media (max-width: 991.98px) { .ar-table-wrap { overflow-x: auto !important; } }
        .ar-farmer-combo { position: relative; }
        .ar-farmer-menu {
            position: absolute;
            top: calc(100% + 4px);
            left: 0;
            width: 100%;
            z-index: 1080;
            margin: 0;
            padding: 4px;
            list-style: none;
            background: #fff;
            border: 1px solid var(--rule, #d0d7de);
            border-radius: 6px;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .12);
            max-height: 220px;
            overflow-y: auto;
            min-width: 230px;
            text-align: left;
        }
        .ar-farmer-option {
            display: block;
            width: 100%;
            padding: 7px 10px;
            border: 0;
            border-radius: 4px;
            background: transparent;
            color: var(--ink, #1f2937);
            font-size: .875rem;
            text-align: left;
            cursor: pointer;
        }
        .ar-farmer-option:hover,
        .ar-farmer-option.is-active {
            background: var(--good-bg, #ecfdf3);
            color: var(--brand-ink, #14532d);
        }
        .ar-farmer-empty {
            padding: 8px 10px;
            font-size: .8125rem;
            color: var(--muted, #6b7280);
        }
    </style>

    <script @nonce>
        document.addEventListener('DOMContentLoaded', () => {
            let activeCombo = null;

            const closeMenu = (combo) => {
                if (!combo) return;
                const menu = combo.querySelector('.ar-farmer-menu');
                const input = combo.querySelector('[data-farmer-input]');
                if (menu) menu.hidden = true;
                if (input) input.setAttribute('aria-expanded', 'false');
                if (activeCombo === combo) activeCombo = null;
            };

            const positionMenu = () => {};

            const renderMatches = (combo) => {
                const input = combo.querySelector('[data-farmer-input]');
                const hidden = combo.querySelector('[data-farmer-id]');
                const menu = combo.querySelector('.ar-farmer-menu');
                if (!input || !hidden || !menu) return;

                let farmers = [];
                try {
                    farmers = JSON.parse(combo.dataset.farmers || '[]');
                } catch {
                    farmers = [];
                }

                const term = input.value.trim().toLowerCase();
                const matches = farmers
                    .filter((farmer) => term === '' || farmer.name.toLowerCase().includes(term))
                    .slice(0, 30);

                menu.innerHTML = '';
                if (matches.length === 0) {
                    const empty = document.createElement('li');
                    empty.className = 'ar-farmer-empty';
                    empty.textContent = 'No matching farmer in this barangay';
                    menu.appendChild(empty);
                } else {
                    matches.forEach((farmer, idx) => {
                        const li = document.createElement('li');
                        const btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'ar-farmer-option' + (idx === 0 ? ' is-active' : '');
                        btn.setAttribute('role', 'option');
                        btn.dataset.id = String(farmer.id);
                        btn.dataset.name = farmer.name;
                        btn.textContent = farmer.name;
                        li.appendChild(btn);
                        menu.appendChild(li);
                    });
                }

                if (activeCombo && activeCombo !== combo) closeMenu(activeCombo);
                activeCombo = combo;
                menu.hidden = false;
                input.setAttribute('aria-expanded', 'true');
                positionMenu(combo);
            };

            const selectOption = (combo, button) => {
                const input = combo.querySelector('[data-farmer-input]');
                const hidden = combo.querySelector('[data-farmer-id]');
                if (!input || !hidden || !button) return;
                hidden.value = button.dataset.id || '';
                input.value = button.dataset.name || '';
                input.setCustomValidity('');
                closeMenu(combo);
            };

            document.addEventListener('input', (event) => {
                const input = event.target.closest('[data-farmer-input]');
                if (!input) return;
                const combo = input.closest('[data-farmer-combo]');
                const hidden = combo?.querySelector('[data-farmer-id]');
                if (hidden) hidden.value = '';
                input.setCustomValidity('');
                if (combo) renderMatches(combo);
            });

            document.addEventListener('focusin', (event) => {
                const input = event.target.closest('[data-farmer-input]');
                if (!input) return;
                const combo = input.closest('[data-farmer-combo]');
                if (combo) renderMatches(combo);
            });

            document.addEventListener('mousedown', (event) => {
                const option = event.target.closest('.ar-farmer-option');
                if (option) {
                    event.preventDefault();
                    const combo = option.closest('[data-farmer-combo]');
                    if (combo) selectOption(combo, option);
                    return;
                }
                if (activeCombo && !event.target.closest('[data-farmer-combo]')) {
                    closeMenu(activeCombo);
                }
            });

            document.addEventListener('keydown', (event) => {
                const input = event.target.closest('[data-farmer-input]');
                if (!input) return;
                const combo = input.closest('[data-farmer-combo]');
                const menu = combo?.querySelector('.ar-farmer-menu');
                if (!combo || !menu) return;

                if (event.key === 'Escape') {
                    closeMenu(combo);
                    return;
                }

                const options = Array.from(menu.querySelectorAll('.ar-farmer-option'));
                if (options.length === 0) return;

                const currentIdx = options.findIndex((o) => o.classList.contains('is-active'));
                if (event.key === 'ArrowDown') {
                    event.preventDefault();
                    if (menu.hidden) renderMatches(combo);
                    const next = options[(currentIdx + 1) % options.length];
                    options.forEach((o) => o.classList.remove('is-active'));
                    next.classList.add('is-active');
                    next.scrollIntoView({ block: 'nearest' });
                } else if (event.key === 'ArrowUp') {
                    event.preventDefault();
                    const prev = options[(currentIdx - 1 + options.length) % options.length];
                    options.forEach((o) => o.classList.remove('is-active'));
                    prev.classList.add('is-active');
                    prev.scrollIntoView({ block: 'nearest' });
                } else if (event.key === 'Enter' && !menu.hidden) {
                    event.preventDefault();
                    const chosen = options[currentIdx >= 0 ? currentIdx : 0];
                    if (chosen) selectOption(combo, chosen);
                }
            });

            document.addEventListener('submit', (event) => {
                const form = event.target.closest('form');
                const combo = form?.querySelector('[data-farmer-combo]');
                if (!combo) return;
                const hidden = combo.querySelector('[data-farmer-id]');
                const input = combo.querySelector('[data-farmer-input]');
                if (hidden && input && !hidden.value) {
                    event.preventDefault();
                    input.setCustomValidity('Please choose a farmer from the dropdown list.');
                    input.reportValidity();
                    renderMatches(combo);
                }
            });

            window.addEventListener('scroll', () => {
                if (activeCombo) positionMenu(activeCombo);
            }, true);
            window.addEventListener('resize', () => {
                if (activeCombo) positionMenu(activeCombo);
            });
        });
    </script>
</x-app-layout>
