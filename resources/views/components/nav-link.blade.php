@props(['active'])

@php
$classes = ($active ?? false)
            ? 'inline-flex items-center px-1 pt-1 border-b-2 border-[#52B788] text-sm font-semibold leading-5 text-[#1B2B23] focus:outline-none focus:border-[#2D6A4F] transition duration-150 ease-in-out'
            : 'inline-flex items-center px-1 pt-1 border-b-2 border-transparent text-sm font-medium leading-5 text-[#5A7A64] hover:text-[#1B2B23] hover:border-[#95D5B2] focus:outline-none focus:text-[#1B2B23] focus:border-[#95D5B2] transition duration-150 ease-in-out';
@endphp

<a {{ $attributes->merge(['class' => $classes]) }}>
    {{ $slot }}
</a>
