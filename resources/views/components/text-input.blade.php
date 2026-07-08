@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-[#D4EDDA] bg-white text-[#1B2B23] placeholder-[#9DB3A4] focus:border-[#52B788] focus:ring-[#52B788]/25 rounded-md shadow-sm']) }}>
