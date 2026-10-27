<x-app-layout>
    {{-- IT Personnel: add or edit a rice variety (RiceVarietyController::store / update). Grouped like the Variety
         Details pop-up. Saved varieties are "manual", so a later NSIC import never overwrites them. A reaction imported
         as several results (by site or stem borer kind) can be kept as it is ("Keep imported"). --}}
    @include('rice-varieties._fonts')
    @php
        $v = $variety;
        $err = fn (string $f) => $errors->has($f) ? ' is-invalid' : '';
        $val = fn (string $f) => old($f, $v->{$f});
        $codes = \App\Models\RiceVariety::REACTION_CODES;
        $reaction = function (string $group, string $key) use ($v): array {
            $stored = $v->reactions[$group][$key] ?? null;
            $entries = $stored['entries'] ?? [];
            $single = count($entries) === 1 && $entries[0]['label'] === null && in_array($entries[0]['code'], \App\Models\RiceVariety::REACTION_CODES, true);

            return ['raw' => $stored['raw'] ?? null, 'value' => $stored === null ? 'none' : ($single ? $entries[0]['code'] : 'keep')];
        };
    @endphp

    <div class="rv-page rv-it" data-rv-page>
        <header class="rv-it-head">
            <div>
                <a class="rv-back" href="{{ route('rice-varieties.index') }}">← {{ __('rice_varieties.it.back') }}</a>
                <h1>{{ $v->exists ? __('rice_varieties.it.edit') : __('rice_varieties.it.add') }}</h1>
                @if ($v->exists)<p class="rv-mono">{{ $v->nsic_reg_no }} · {{ __('rice_varieties.it.source.'.$v->source) }}</p>@endif
            </div>
        </header>

        @if ($errors->any())
            <div class="rv-flash is-error" role="alert">
                <strong>Please check the highlighted fields.</strong>
                <ul>@foreach ($errors->all() as $message)<li>{{ $message }}</li>@endforeach</ul>
            </div>
        @endif

        <form method="POST" action="{{ $action }}" class="rv-form" data-rv-form novalidate>
            @csrf
            @if ($method !== 'POST') @method($method) @endif

            <fieldset class="rv-fieldset">
                <legend>{{ __('rice_varieties.it.sections.identity') }}</legend>
                <div class="rv-grid">
                    @foreach (['nsic_reg_no' => 'text', 'variety_name' => 'text', 'line_name' => 'text', 'owner_breeder' => 'text'] as $field => $type)
                        <label class="rv-field"><span>{{ __('rice_varieties.it.fields.'.$field) }}</span>
                            <input type="{{ $type }}" name="{{ $field }}" value="{{ $val($field) }}" class="rv-input{{ $err($field) }}" @if (in_array($field, ['nsic_reg_no', 'variety_name'], true)) required @endif maxlength="{{ $field === 'nsic_reg_no' ? 120 : 191 }}"></label>
                    @endforeach
                    <label class="rv-field"><span>{{ __('rice_varieties.it.fields.year_approved') }}</span>
                        <input type="number" name="year_approved" value="{{ $val('year_approved') }}" min="1950" max="{{ now()->year + 1 }}" class="rv-input{{ $err('year_approved') }}" required></label>
                    <label class="rv-field"><span>{{ __('rice_varieties.it.fields.variety_type') }}</span>
                        <select name="variety_type" class="rv-input{{ $err('variety_type') }}">
                            <option value="">—</option>
                            @foreach (\App\Models\RiceVariety::TYPES as $type)<option value="{{ $type }}" @selected($val('variety_type') === $type)>{{ $type }}</option>@endforeach
                        </select></label>
                    {{-- Farm type: what IT checks is saved as is; neither checked is "Not specified". --}}
                    @php $farmTypes = old('farm_types', $v->exists ? $v->farmTypes() : []); @endphp
                    <fieldset class="rv-field rv-farm-field" data-rv-farm-field>
                        <legend><span>{{ __('rice_varieties.it.fields.farm_types') }}</span></legend>
                        <div class="rv-checks">
                            @foreach (\App\Models\RiceVariety::FARM_TYPES as $type)
                                <label class="rv-check"><input type="checkbox" name="farm_types[]" value="{{ $type }}" @checked(in_array($type, (array) $farmTypes, true))><span>{{ __('rice_varieties.farm_types.'.$type) }}</span></label>
                            @endforeach
                        </div>
                        <small class="rv-muted">{{ __('rice_varieties.it.fields.farm_types_none') }}</small>
                        @error('farm_types')<small class="rv-error">{{ $message }}</small>@enderror
                        @error('farm_types.*')<small class="rv-error">{{ $message }}</small>@enderror
                    </fieldset>
                </div>
            </fieldset>

            <fieldset class="rv-fieldset">
                <legend>{{ __('rice_varieties.it.sections.yield') }}</legend>
                <div class="rv-grid is-4">
                    @foreach (\App\Models\RiceVariety::COMBOS as $combo)
                        <label class="rv-field"><span>{{ __('rice_varieties.it.combos.'.$combo) }}</span>
                            <input type="number" name="yield_{{ $combo }}" value="{{ $val('yield_'.$combo) }}" min="1" max="20000" step="1" class="rv-input rv-mono{{ $err('yield_'.$combo) }}"></label>
                    @endforeach
                </div>
            </fieldset>

            {{-- Avg / min / max: maturity per combination (+ seed production), height and productive tillers. --}}
            @foreach ([['maturity', __('rice_varieties.it.sections.maturity'), [...\App\Models\RiceVariety::COMBOS, 'seed_production'], 1],
                       ['height', __('rice_varieties.it.fields.height'), \App\Models\RiceVariety::COMBOS, 0.1],
                       ['tillers', __('rice_varieties.it.fields.tillers'), \App\Models\RiceVariety::COMBOS, 0.1]] as [$measure, $title, $combos, $step])
                <fieldset class="rv-fieldset">
                    <legend>{{ $measure === 'maturity' ? $title : __('rice_varieties.it.sections.height_tillers').' · '.$title }}</legend>
                    <div class="rv-triples">
                        @foreach ($combos as $combo)
                            <div class="rv-triple" role="group" aria-label="{{ $title }} {{ $combo === 'seed_production' ? __('rice_varieties.it.fields.seed_production') : __('rice_varieties.it.combos.'.$combo) }}">
                                <span class="rv-triple-name">{{ $combo === 'seed_production' ? __('rice_varieties.it.fields.seed_production') : __('rice_varieties.it.combos.'.$combo) }}</span>
                                @foreach (['' => 'avg', '_min' => 'min', '_max' => 'max'] as $part => $word)
                                    @php $f = $measure.'_'.$combo.$part; @endphp
                                    <label><span>{{ __('rice_varieties.it.fields.'.$word) }}</span><input type="number" name="{{ $f }}" value="{{ $val($f) }}" min="0" step="{{ $step }}" class="rv-input rv-mono{{ $err($f) }}"></label>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    @if ($measure === 'tillers')
                        <label class="rv-field"><span>{{ __('rice_varieties.it.fields.culm_strength') }}</span>
                            <input type="text" name="culm_strength" value="{{ $val('culm_strength') }}" maxlength="80" class="rv-input{{ $err('culm_strength') }}"></label>
                    @endif
                </fieldset>
            @endforeach

            @foreach (['pests' => \App\Models\RiceVariety::PESTS, 'diseases' => \App\Models\RiceVariety::DISEASES] as $group => $keys)
                <fieldset class="rv-fieldset">
                    <legend>{{ __('rice_varieties.it.sections.'.$group) }}</legend>
                    <div class="rv-grid">
                        @foreach ($keys as $key)
                            @php $r = $reaction($group, $key); $chosen = old($group.'.'.$key, $r['value']); @endphp
                            <label class="rv-field"><span>{{ __('rice_varieties.'.$group.'.'.$key) }}</span>
                                <select name="{{ $group }}[{{ $key }}]" class="rv-input{{ $err($group.'.'.$key) }}">
                                    @if ($r['value'] === 'keep')<option value="keep" @selected($chosen === 'keep')>{{ \Illuminate\Support\Str::limit(__('rice_varieties.it.fields.keep', ['value' => $r['raw']]), 70) }}</option>@endif
                                    <option value="none" @selected($chosen === 'none')>{{ __('rice_varieties.it.fields.not_tested') }}</option>
                                    @foreach ($codes as $code)<option value="{{ $code }}" @selected($chosen === $code)>{{ $code }} · {{ __('rice_varieties.reaction_levels.'.\App\Support\RiceVarieties\NsicCell::reactionLevel($code)) }}</option>@endforeach
                                </select></label>
                        @endforeach
                    </div>
                </fieldset>
            @endforeach

            <fieldset class="rv-fieldset">
                <legend>{{ __('rice_varieties.it.sections.grain') }}</legend>
                <label class="rv-field is-narrow"><span>{{ __('rice_varieties.it.fields.amylose_content') }}</span>
                    <input type="number" name="amylose_content" value="{{ $val('amylose_content') }}" min="0" max="40" step="0.1" class="rv-input rv-mono{{ $err('amylose_content') }}"></label>
            </fieldset>

            <fieldset class="rv-fieldset">
                <legend>{{ __('rice_varieties.it.sections.adaptation') }}</legend>
                @foreach (['adaptation', 'remarks'] as $field)
                    <label class="rv-field is-full"><span>{{ __('rice_varieties.it.fields.'.$field) }}</span>
                        <textarea name="{{ $field }}" rows="3" maxlength="5000" class="rv-input{{ $err($field) }}">{{ $val($field) }}</textarea></label>
                @endforeach
            </fieldset>

            <div class="rv-form-actions">
                <button type="submit" class="rv-btn is-primary">{{ __('rice_varieties.it.save') }}</button>
                <a class="rv-btn is-quiet" href="{{ route('rice-varieties.index') }}">{{ __('rice_varieties.modal.close') }}</a>
            </div>
        </form>
    </div>
</x-app-layout>
