{{-- Shown where a card has no saved data at all yet (for example before the first scheduled weather update):
     a calm "Walang datos pa" instead of an error or an empty card. --}}
@props(['note' => null, 'label' => 'Walang datos pa'])
<div {{ $attributes->merge(['class' => 'ic-no-data', 'role' => 'status']) }}>
    <strong>{{ $label }}</strong>
    @if ($note)<span>{{ $note }}</span>@endif
</div>
