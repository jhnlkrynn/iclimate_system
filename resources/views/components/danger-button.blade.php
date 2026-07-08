<button {{ $attributes->merge(['type' => 'submit', 'class' => 'inline-flex items-center px-4 py-2 bg-[#D85B45] border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-normal shadow-sm hover:bg-[#bf4633] hover:-translate-y-0.5 active:bg-[#9f3728] focus:outline-none focus:ring-2 focus:ring-[#D85B45]/35 focus:ring-offset-2 transition ease-in-out duration-150']) }}>
    {{ $slot }}
</button>
