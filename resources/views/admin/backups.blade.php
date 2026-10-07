<x-app-layout>
    <x-slot:title>Backups</x-slot:title>
    {{-- Manuscript – Naicel (2026-10-08), Phase 8: BackupController. --}}
    @php
        $tz = \App\Support\BusinessClock::timezone();
        $mb = fn (?int $bytes) => $bytes === null ? '—' : number_format($bytes / 1048576, 1).' MB';
    @endphp
    <div class="mfd itd itp" data-backups-page>
        <x-it.page-header kicker="IT Personnel" title="Backups" lede="Back up the database now, download a backup, or restore one. A backup also runs every day at 03:15.">
            <form method="POST" action="{{ route('admin.backups.store') }}" data-loading="true">
                @csrf
                <button class="btn btn-primary btn-sm" type="submit" data-loading-text="Starting...">Back up now</button>
            </form>
        </x-it.page-header>
        @include('partials.manuscript-tabs', ['group' => 'settings', 'label' => 'Settings'])

        @if ($last)
            <div @class(['alert', 'alert-success' => $last['state'] === 'done', 'alert-danger' => $last['state'] === 'failed', 'alert-info' => $last['state'] === 'running']) data-backup-last>
                <strong>Last {{ $last['action'] }}:</strong> {{ $last['state'] === 'running' ? 'running…' : ($last['message'] ?? '') }}
                <span class="small">({{ \Illuminate\Support\Carbon::parse($last['at'])->timezone($tz)->format('M j, g:i A') }})</span>
            </div>
        @endif

        <div class="itp-grid mb-3" style="grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));">
            <div class="mfd-card"><span class="small text-muted">Status</span><div class="h5 mb-0">{{ ['ok' => 'Up to date', 'stale' => 'Out of date', 'missing' => 'No backup', 'unavailable' => 'Folder unreadable'][$status['state']] ?? $status['state'] }}</div></div>
            <div class="mfd-card"><span class="small text-muted">Latest backup</span><div class="h5 mb-0">{{ $status['last']?->copy()->timezone($tz)->format('M j, g:i A') ?? '—' }}</div></div>
            <div class="mfd-card"><span class="small text-muted">Backups kept</span><div class="h5 mb-0">{{ $status['count'] }} <span class="small text-muted">({{ $retentionDays }} days)</span></div></div>
        </div>

        <section class="itp-section" aria-labelledby="bkHistory">
            <div class="itp-section-head"><h2 id="bkHistory">Backup history</h2><p>Restore replaces the whole database with that backup. A safety backup of the current database is made first.</p></div>
            @if ($files->isEmpty())
                <div class="fad-empty" role="status"><p class="fad-lead">No backup yet.</p></div>
            @else
                <div class="table-responsive mfd-card p-0">
                    <table class="table table-sm align-middle mb-0" data-backup-files>
                        <thead><tr><th>Backup</th><th>Made</th><th>Size</th><th class="text-end"></th></tr></thead>
                        <tbody>
                            @foreach ($files as $file)
                                <tr>
                                    <td class="font-monospace small">{{ $file['name'] }}</td>
                                    <td class="small">{{ \Illuminate\Support\Carbon::createFromTimestamp($file['time'])->timezone($tz)->format('M j, Y g:i A') }}</td>
                                    <td class="small">{{ $mb($file['size']) }}</td>
                                    <td class="text-end">
                                        <div class="d-flex gap-2 justify-content-end flex-wrap">
                                            <a class="btn btn-outline-secondary btn-sm" href="{{ route('admin.backups.download', $file['name']) }}" data-fast-nav-bound="true">Download</a>
                                            <details>
                                                <summary class="btn btn-outline-danger btn-sm">Restore</summary>
                                                <form class="text-start mt-2" method="POST" action="{{ route('admin.backups.restore', $file['name']) }}" style="min-width: 260px;" data-loading="true">
                                                    @csrf
                                                    <label class="form-label small">Type <strong>RESTORE</strong></label>
                                                    <input class="form-control form-control-sm" name="confirm" autocomplete="off" required>
                                                    <label class="form-label small mt-1">Your password</label>
                                                    <input class="form-control form-control-sm" type="password" name="password" autocomplete="current-password" required>
                                                    <button class="btn btn-danger btn-sm mt-2" type="submit" data-loading-text="Starting...">Restore this backup</button>
                                                </form>
                                            </details>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($errors->any())<div class="alert alert-danger mt-2">{{ $errors->first() }}</div>@endif
            @endif
        </section>

        <section class="itp-section" aria-labelledby="bkVerify">
            <div class="itp-section-head"><h2 id="bkVerify">Daily checks</h2><p>Each night the newest backup is opened and checked (iclimate:verify-backup).</p></div>
            @if ($verifications->isEmpty())
                <p class="small text-muted">No check recorded yet.</p>
            @else
                <div class="table-responsive mfd-card p-0">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Checked</th><th>Backup</th><th>Result</th></tr></thead>
                        <tbody>
                            @foreach ($verifications as $v)
                                <tr><td class="small">{{ $v->verified_at?->copy()->timezone($tz)->format('M j, Y g:i A') }}</td><td class="small font-monospace">{{ $v->backup_filename }}</td><td><span @class(['badge', 'text-bg-success' => $v->status === 'passed', 'text-bg-danger' => $v->status !== 'passed'])>{{ ucfirst((string) $v->status) }}</span>@if ($v->failure_reason) <span class="small">{{ $v->failure_reason }}</span>@endif</td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
