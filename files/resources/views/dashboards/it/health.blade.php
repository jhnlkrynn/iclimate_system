@php $glyph = ['ok' => '✓', 'warn' => '!', 'crit' => '✕']; @endphp
<section class="itd-card" aria-labelledby="itdHealthTitle" data-it-health>
    <div class="itd-card-head">
        <h2 id="itdHealthTitle">System Health <span class="itd-info" tabindex="0" role="note" aria-label="Only services the application can verify are listed. Optional or unconfigured features are not counted here; full diagnostics are on the System Health page." data-tip="Only services the application can verify are listed. Optional or unconfigured features are not counted here; full diagnostics are on the System Health page.">i</span></h2>
        <a class="itd-link" href="{{ route('system-health.index') }}">Full diagnostics</a>
    </div>
    <div class="itd-table-wrap">
        <table class="itd-table">
            <thead><tr><th scope="col">Service</th><th scope="col">Status</th><th scope="col">Last Checked</th></tr></thead>
            <tbody>
                @foreach ($it['services'] as $service)
                    <tr data-service="{{ $service['key'] }}">
                        <td>
                            <strong>{{ $service['name'] }}</strong>
                            <small class="itd-muted d-block">{{ $service['summary'] }}</small>
                        </td>
                        <td><span class="itd-badge is-{{ $service['level'] }}"><span aria-hidden="true">{{ $glyph[$service['level']] }}</span> {{ $service['label'] }}</span></td>
                        <td class="itd-nowrap">{{ $service['checked_at']->diffForHumans() }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</section>
