{{-- One entry inside a calendar day: title (two lines at most) and its kind in words, never colour alone.
     The summary is only shown in the phone agenda view, where there is room for it. --}}
<a href="{{ $event['url'] }}" class="fcal-event" data-kind="{{ $kind }}" title="{{ $event['title'] }}">
    <span class="fcal-event-title">{{ $event['title'] }}</span>
    <span class="fcal-event-summary">{{ $event['summary'] }}</span>
    <span class="fad-badge fcal-kind" data-kind="{{ $kind }}">{{ $event['type_label'] }}</span>
</a>
