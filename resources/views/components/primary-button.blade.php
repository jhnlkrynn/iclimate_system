<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-[#1A3A2A] border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-normal shadow-sm hover:bg-[#2D6A4F] hover:-translate-y-0.5 focus:bg-[#2D6A4F] active:bg-[#122B20] focus:outline-none focus:ring-2 focus:ring-[#52B788]/40 focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
