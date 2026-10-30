{{-- Plain-text part of every iClimate email: the message, then how to reach the office. --}}
iClimate · {{ \App\Support\MaoContact::OFFICE }} of Lian

{!! $slot !!}
@isset($subcopy)

{!! $subcopy !!}
@endisset

--
{{ \App\Support\MaoContact::OFFICE }} of Lian
{{ \App\Support\MaoContact::ADDRESS }}
{{ \App\Support\MaoContact::EMAIL }} · {{ \App\Support\MaoContact::HOURS }}
