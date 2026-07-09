<button {{ $attributes->merge(['type' => 'button', 'class' => 'inline-flex items-center px-5 py-2.5 bg-white border border-[#D4EDDA] rounded-full font-semibold text-xs text-[#1B2B23] uppercase tracking-wide shadow-sm hover:bg-[#F0F7F4] hover:-translate-y-0.5 focus:outline-none focus:ring-2 focus:ring-[#52B788]/35 focus:ring-offset-2 disabled:opacity-25 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
