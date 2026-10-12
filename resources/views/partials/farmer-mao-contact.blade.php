{{-- Panel revision 4.4 C: the farmer's assigned MAO personnel, from the farmer's barangay (and sitio, when the MAO
     covers only part of it). "Your Agricultural Technologist: Lodivico A. Cachuela". The Municipal Agriculturist is named
     too, as the office head and the contact for barangays without assigned field personnel. Read from the cache
     (User::maoContactsFor), so the farmer's pages cost no query for it. --}}
@php
    $mcUser = $farmer ?? auth()->user();
    $mcBarangay = $mcUser?->registeredBarangay();
    $mcContacts = \App\Models\User::maoContactsFor($mcBarangay);
    $mcPeople = $mcContacts['people'];
    $mcHead = $mcContacts['head'];
    $mcFil = ($locale ?? app()->getLocale()) === 'fil';
@endphp
@if ($mcBarangay && ($mcPeople !== [] || $mcHead))
    <section class="mao-contact" aria-labelledby="maoContactTitle" data-farmer-mao-contact>
        <h2 id="maoContactTitle" class="mao-contact-title">{{ $mcFil ? 'Ang inyong MAO personnel' : 'Your MAO personnel' }}</h2>
        <p class="mao-contact-note">{{ $mcFil ? 'Para sa Barangay' : 'For Barangay' }} {{ $mcBarangay }}</p>
        <ul class="mao-contact-list">
            @foreach ($mcPeople as $person)
                <li>
                    <span class="mao-contact-role">{{ $mcFil ? 'Ang inyong' : 'Your' }} {{ $person['position'] }}:</span>
                    <strong>{{ $person['name'] }}</strong>
                    @if ($person['contact'])<a class="mao-contact-phone" href="tel:{{ preg_replace('/[^0-9+]/', '', $person['contact']) }}">{{ $person['contact'] }}</a>@endif
                </li>
            @endforeach
            @if ($mcHead)
                <li>
                    <span class="mao-contact-role">{{ $mcHead['position'] }}:</span>
                    <strong>{{ $mcHead['name'] }}</strong>
                </li>
            @endif
        </ul>
        @if (\Illuminate\Support\Facades\Route::has('messages.index'))
            <a class="mfd-btn is-quiet mao-contact-btn" href="{{ route('messages.index') }}">{{ $mcFil ? 'Magpadala ng mensahe sa MAO' : 'Message the MAO' }}</a>
        @endif
    </section>
@endif
