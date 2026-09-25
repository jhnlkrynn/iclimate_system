<button {{ $attributes->merge(['type' => 'submit', 'class' => 'btn btn-danger d-inline-flex align-items-center gap-2 px-4']) }}>
    {{ $slot }}
</button>
