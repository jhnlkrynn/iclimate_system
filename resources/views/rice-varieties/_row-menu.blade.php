{{-- IT Personnel: the "⋯" menu of a variety row (Edit, Deactivate / Activate). Arrow keys move, Esc closes
     (resources/js/rice-varieties.js); a click inside it never opens the details pop-up (data-rv-stop). --}}
<div class="rv-menu" data-rv-menu data-rv-stop>
    <button type="button" class="rv-menu-btn" id="rvMenuBtn-{{ $variety->id }}" aria-haspopup="menu" aria-expanded="false" aria-controls="rvMenu-{{ $variety->id }}"
            aria-label="{{ __('rice_varieties.it.menu', ['name' => $variety->variety_name]) }}" data-rv-menu-btn>
        <svg viewBox="0 0 20 20" aria-hidden="true"><circle cx="4.5" cy="10" r="1.6"/><circle cx="10" cy="10" r="1.6"/><circle cx="15.5" cy="10" r="1.6"/></svg>
    </button>
    <div class="rv-menu-list" id="rvMenu-{{ $variety->id }}" role="menu" aria-labelledby="rvMenuBtn-{{ $variety->id }}" hidden>
        <a role="menuitem" tabindex="-1" href="{{ route('rice-varieties.edit', $variety) }}" data-rv-menu-edit>{{ __('rice_varieties.it.edit_short') }}</a>
        <form method="POST" action="{{ route('rice-varieties.toggle', $variety) }}">
            @csrf
            @method('PATCH')
            <button type="submit" role="menuitem" tabindex="-1" data-rv-toggle="{{ $variety->id }}">{{ $variety->is_active ? __('rice_varieties.it.deactivate') : __('rice_varieties.it.activate') }}</button>
        </form>
    </div>
</div>
