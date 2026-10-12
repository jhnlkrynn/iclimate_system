{{-- Panel revision 4.4 C: who created, edited, verified or approved this record, and when (App\Support\MaoStamp). --}}
@php $stampLines = \App\Support\MaoStamp::lines($record ?? null); @endphp
@if ($stampLines !== [])
    <ul class="record-stamps" aria-label="Record history" data-record-stamps>
        @foreach ($stampLines as $line)
            <li>
                <span class="record-stamp-label">{{ $line['label'] }}:</span>
                <strong>{{ $line['name'] }}</strong>@if ($line['position'])<span class="record-stamp-position"> ({{ $line['position'] }})</span>@endif
                @if ($line['at'])<span class="record-stamp-at"> on {{ $line['at'] }}</span>@endif
            </li>
        @endforeach
    </ul>
@endif
