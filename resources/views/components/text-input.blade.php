@props(['disabled' => false])

<input @disabled($disabled) {{ $attributes->merge(['class' => 'border-sage-100 bg-white text-ink placeholder-sage-300 focus:border-sage-500 focus:ring-sage-500/25 rounded-md shadow-sm']) }}>
