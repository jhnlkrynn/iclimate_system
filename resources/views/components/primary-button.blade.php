<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn btn-primary d-inline-flex align-items-center gap-2 px-4']) }}>
    {{ $slot }}
</button>
