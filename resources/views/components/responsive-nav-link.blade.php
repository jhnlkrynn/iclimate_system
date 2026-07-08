@props(['active'])

@php
$classes = ($active ?? false)
            ? 'block w-full ps-3 pe-4 py-2 border-l-4 border-[#52B788] text-start text-base font-semibold text-[#1A3A2A] bg-[#F0F7F4] focus:outline-none focus:text-[#122B20] focus:bg-[#D8F3DC] focus:border-[#2D6A4F] transition duration-150 ease-in-out'
            : 'block w-full ps-3 pe-4 py-2 border-l-4 border-transparent text-start text-base font-medium text-[#5A7A64] hover:text-[#1B2B23] hover:bg-[#F0F7F4] hover:border-[#95D5B2] focus:outline-none focus:text-[#1B2B23] focus:bg-[#F0F7F4] focus:border-[#95D5B2] transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
