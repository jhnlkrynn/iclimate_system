{{-- The one layout of every iClimate email (account emails, password reset): branded header, the message, and how to
     reach the Municipal Agriculture Office in the footer (App\Support\MaoContact). --}}
<x-mail::layout>
{{-- Header --}}
<x-slot:header>
<x-mail::header :url="config('app.url')">
{{ config('app.name') }}
</x-mail::header>
</x-slot:header>

{{-- Body --}}
{!! $slot !!}

{{-- Subcopy --}}
@isset($subcopy)
<x-slot:subcopy>
<x-mail::subcopy>
{!! $subcopy !!}
</x-mail::subcopy>
</x-slot:subcopy>
@endisset

{{-- Footer --}}
<x-slot:footer>
<x-mail::footer>
**{{ \App\Support\MaoContact::OFFICE }} of Lian**<br>
{{ \App\Support\MaoContact::ADDRESS }}<br>
[{{ \App\Support\MaoContact::EMAIL }}](mailto:{{ \App\Support\MaoContact::EMAIL }}) · {{ \App\Support\MaoContact::HOURS }}

© {{ date('Y') }} iClimate · MAO Lian
</x-mail::footer>
</x-slot:footer>
</x-mail::layout>
