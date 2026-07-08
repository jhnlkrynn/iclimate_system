@props(['value'])

<label {{ $attributes->merge(['class' => 'block font-semibold text-sm text-[#1B2B23]']) }}>
    {{ $value ?? $slot }}
</label>
