<x-app-layout>
    <x-slot:title>Send Alert</x-slot:title>
    {{-- Manuscript – Naicel (2026-10-08), Phase 6: AdvisoryAlertController. Compose an alert, send it now or schedule
         it; the history shows how many farmers got each alert and how many read it. --}}
    @php $tz = \App\Support\BusinessClock::timezone(); @endphp
    <div class="mfd itd itp" data-alerts-page>
        <x-it.page-header kicker="Advisories" title="Send Alert" lede="Warn farmers about a typhoon, flood, drought or extreme heat, or send a farming recommendation, to all farmers or chosen barangays.">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('planting-advisories.index') }}">Back to Advisories</a>
        </x-it.page-header>

        <section class="itp-section" aria-labelledby="alComposeTitle">
            <div class="itp-section-head">
                <h2 id="alComposeTitle">New alert</h2>
                <p>The alert is published as an advisory for each chosen barangay; the farmers there get a bell notice that opens it.</p>
            </div>
            <form class="mfd-card" method="POST" action="{{ route('mao.alerts.store') }}" data-loading="true" data-alert-form>
                @csrf
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label fw-semibold" for="alTitle">Title</label>
                        <input class="form-control @error('title') is-invalid @enderror" id="alTitle" name="title" maxlength="255" required value="{{ old('title') }}" placeholder="e.g. Typhoon signal no. 2: secure your harvest">
                        @error('title')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3"><label class="form-label fw-semibold" for="alType">Type</label>
                        <select class="form-select" id="alType" name="type">@foreach ($types as $key => $label)<option value="{{ $key }}" @selected(old('type') === $key)>{{ $label }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-3"><label class="form-label fw-semibold" for="alSeverity">Severity</label>
                        <select class="form-select" id="alSeverity" name="severity">@foreach ($severities as $key => $label)<option value="{{ $key }}" @selected(old('severity', 'moderate') === $key)>{{ $label }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-6"><label class="form-label fw-semibold" for="alMessage">Message</label>
                        <textarea class="form-control @error('message') is-invalid @enderror" id="alMessage" name="message" rows="4" maxlength="5000" required>{{ old('message') }}</textarea>
                        @error('message')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-6"><label class="form-label fw-semibold" for="alAction">Recommended action</label>
                        <textarea class="form-control" id="alAction" name="recommended_action" rows="4" maxlength="2000">{{ old('recommended_action') }}</textarea>
                    </div>
                    <div class="col-md-6">
                        <span class="form-label fw-semibold d-block">Recipients</span>
                        <label class="form-check"><input class="form-check-input" type="radio" name="recipients" value="all" @checked(old('recipients', 'all') === 'all')> All farmers in Lian</label>
                        <label class="form-check"><input class="form-check-input" type="radio" name="recipients" value="barangays" @checked(old('recipients') === 'barangays')> Farmers in these barangays:</label>
                        <div class="d-flex flex-wrap gap-2 mt-1 small" data-alert-barangays>
                            @foreach ($barangays as $name)
                                <label class="form-check-inline"><input class="form-check-input" type="checkbox" name="barangays[]" value="{{ $name }}" @checked(in_array($name, old('barangays', []), true))> {{ $name }}</label>
                            @endforeach
                        </div>
                        @error('barangays')<div class="text-danger small">Choose at least one barangay.</div>@enderror
                    </div>
                    <div class="col-md-6">
                        <span class="form-label fw-semibold d-block">When</span>
                        <label class="form-check"><input class="form-check-input" type="radio" name="send" value="now" @checked(old('send', 'now') === 'now')> Send now</label>
                        <label class="form-check"><input class="form-check-input" type="radio" name="send" value="later" @checked(old('send') === 'later')> Schedule for</label>
                        <input class="form-control mt-1 @error('scheduled_for') is-invalid @enderror" type="datetime-local" name="scheduled_for" value="{{ old('scheduled_for') }}" aria-label="Scheduled date and time">
                        @error('scheduled_for')<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                </div>
                <button class="btn btn-primary mt-3" type="submit" data-loading-text="Sending...">Send alert</button>
            </form>
        </section>

        <section class="itp-section" aria-labelledby="alHistoryTitle">
            <div class="itp-section-head">
                <h2 id="alHistoryTitle">Sent alerts</h2>
                <p>Delivered: farmers who got the bell notice. Read: farmers who opened it.</p>
            </div>
            @if ($history->isEmpty())
                <div class="fad-empty" role="status"><p class="fad-lead">No alert has been sent yet.</p></div>
            @else
                <div class="table-responsive mfd-card p-0">
                    <table class="table table-sm align-middle mb-0" data-alert-history>
                        <thead><tr><th>Alert</th><th>Recipients</th><th>Sent</th><th>Delivered</th><th>Read</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($history as $row)
                                <tr>
                                    <td><strong>{{ $row['title'] }}</strong><div class="small text-muted">{{ $row['type'] }} · {{ $row['severity'] }}@if ($row['author']) · {{ $row['author'] }}@endif</div></td>
                                    <td class="small">{{ $row['recipients'] }}</td>
                                    <td class="small">
                                        @if (str_contains($row['status'], 'scheduled'))
                                            <span class="badge text-bg-warning">Scheduled</span> {{ $row['scheduled_for']?->copy()->timezone($tz)->format('M j, g:i A') }}
                                        @elseif (str_contains($row['status'], 'archived') && ! $row['sent_at'])
                                            <span class="badge text-bg-light">Cancelled</span>
                                        @else
                                            {{ $row['sent_at']?->copy()->timezone($tz)->format('M j, Y g:i A') ?? '—' }}
                                        @endif
                                    </td>
                                    <td>{{ number_format($row['delivered']) }}</td>
                                    <td>{{ number_format($row['read']) }}@if ($row['read_percent'] !== null) <span class="small text-muted">({{ $row['read_percent'] }}%)</span>@endif</td>
                                    <td class="text-end">
                                        @if (str_contains($row['status'], 'scheduled'))
                                            <form method="POST" action="{{ route('mao.alerts.cancel', $row['batch']) }}" data-loading="true" data-ic-confirm="Cancel this scheduled alert?">
                                                @csrf
                                                <button class="btn btn-outline-danger btn-sm" type="submit">Cancel</button>
                                            </form>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
