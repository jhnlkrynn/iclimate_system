{{-- Farmer Dashboard: "Is <farm> part of a farmers' association?" for one farm without an answer
     (FarmAssociations::promptFarm), with the Add Farm choices: an association of that farm's barangay, "isn't listed"
     (goes to the MAO) or "not a member". "Later" hides it for 7 days (FarmAssociationPromptController). $prompt --}}
@php
    $farm = $prompt['farm'];
    $options = $prompt['options'];
    $old = fn (string $key, $default = null) => (int) old('farm_id') === (int) $farm->id ? old($key, $default) : $default;
    $status = $old('association_status');
@endphp
<section class="fh-card fh-assoc" aria-labelledby="fhAssocTitle" data-association-prompt="{{ $farm->id }}">
    <h2 id="fhAssocTitle" class="fh-assoc-title">{{ __('farms.association.prompt.title', ['name' => $farm->farm_name]) }}</h2>
    <p class="fh-words">{{ __('farms.association.prompt.intro', ['barangay' => $farm->barangay ? \App\Services\Risk\HeatMapPanel::displayName($farm->barangay) : __('farms.no_barangay')]) }}</p>

    @if ($errors->any() && (int) old('farm_id') === (int) $farm->id)
        <p class="fh-assoc-error" role="alert">{{ $errors->first() }}</p>
    @endif

    <form method="POST" action="{{ route('farmer.association-prompt.store') }}" class="fh-assoc-form" data-assoc-form>
        @csrf
        <input type="hidden" name="farm_id" value="{{ $farm->id }}">
        <fieldset class="fh-assoc-choices">
            <legend class="visually-hidden">{{ __('farms.association.question') }}</legend>
            @if ($options->isNotEmpty())
                <label class="fh-assoc-choice"><input type="radio" name="association_status" value="member" @checked($status === 'member') data-assoc-answer> <span>{{ __('farms.association.answers.member') }}</span></label>
            @endif
            <label class="fh-assoc-choice"><input type="radio" name="association_status" value="pending_other" @checked($status === 'pending_other') data-assoc-answer> <span>{{ __('farms.association.answers.pending_other') }}</span></label>
            <label class="fh-assoc-choice"><input type="radio" name="association_status" value="not_member" @checked($status === 'not_member') data-assoc-answer> <span>{{ __('farms.association.answers.not_member') }}</span></label>
        </fieldset>

        @if ($options->isNotEmpty())
            <div class="fh-assoc-field" data-assoc-panel="member">
                <label for="fhAssocPick">{{ __('farms.association.pick') }}</label>
                <select id="fhAssocPick" name="farmer_organization_id">
                    <option value="">{{ __('farms.association.pick_choose') }}</option>
                    @foreach ($options as $option)
                        <option value="{{ $option['id'] }}" @selected((string) $old('farmer_organization_id') === (string) $option['id'])>{{ $option['name'] }}</option>
                    @endforeach
                </select>
            </div>
        @endif
        <div class="fh-assoc-field" data-assoc-panel="pending_other">
            <label for="fhAssocOther">{{ __('farms.association.other_name') }}</label>
            <input id="fhAssocOther" name="association_other_name" type="text" maxlength="120" value="{{ $old('association_other_name') }}">
            <span class="fh-assoc-hint">{{ __('farms.association.other_hint') }}</span>
        </div>

        <div class="fh-assoc-actions">
            <button type="submit" class="mfd-btn">{{ __('farms.association.prompt.save') }}</button>
            <button type="submit" class="mfd-btn is-quiet" form="fhAssocLater">{{ __('farms.association.prompt.later') }}</button>
        </div>
    </form>
    <form method="POST" action="{{ route('farmer.association-prompt.later') }}" id="fhAssocLater">@csrf</form>
</section>
<style>
    .fh-assoc { display: grid; gap: 10px; border: 2px solid var(--brand, var(--sage-700)); }
    .fh-assoc-title { margin: 0; font-size: 1.25rem; line-height: 1.3; font-weight: 700; color: var(--ink, var(--palette-ink)); }
    .fh-assoc-form { display: grid; gap: 12px; }
    .fh-assoc-choices { display: grid; gap: 8px; margin: 0; padding: 0; border: 0; }
    .fh-assoc-choice { display: flex; align-items: center; gap: 10px; min-height: 48px; padding: 10px 14px; border: 1px solid var(--sage-100); border-radius: 8px; background: #fff; font-weight: 600; cursor: pointer; }
    .fh-assoc-choice:has(input:checked) { border-color: var(--brand, var(--sage-700)); background: var(--sage-50); }
    .fh-assoc-choice input { width: 18px; height: 18px; accent-color: var(--brand, var(--sage-700)); }
    .fh-assoc-field { display: grid; gap: 6px; }
    .fh-assoc-field label { font-weight: 600; }
    .fh-assoc-field :is(select, input) { min-height: 46px; padding: 8px 12px; border: 1px solid var(--sage-200); border-radius: 8px; font: inherit; width: 100%; }
    .fh-assoc-hint { font-size: .9rem; color: var(--muted, #55675c); }
    .fh-assoc-error { margin: 0; padding: 10px 12px; border-radius: 8px; background: #fdecea; color: #8a1f17; font-weight: 600; }
    .fh-assoc-actions { display: flex; flex-wrap: wrap; gap: 8px; }
    .fh-assoc [hidden] { display: none !important; }
    @media (max-width: 480px) { .fh-assoc-actions .mfd-btn { flex: 1 1 100%; } }
</style>
<script>
    (() => {
        // Only the field for the chosen answer is shown (all of them stay usable without scripts).
        const form = document.querySelector('[data-assoc-form]');
        if (!form || form.dataset.bound) return;
        form.dataset.bound = '1';
        const show = () => {
            const chosen = form.querySelector('[data-assoc-answer]:checked')?.value || '';
            form.querySelectorAll('[data-assoc-panel]').forEach((panel) => { panel.hidden = panel.dataset.assocPanel !== chosen; });
        };
        form.addEventListener('change', show);
        show();
    })();
</script>
