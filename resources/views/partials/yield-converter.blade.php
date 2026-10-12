{{-- Panel revision 1.2: enter a harvest in t/ha, total tons, kilograms or sacks; the t/ha field ($target, a CSS
     selector inside the same form) is filled as total tons ÷ area harvested (the $area field). The server stores t/ha.
     $converterWrap / $converterInput / $converterHelp: the form's own classes. --}}
@php
    $cvId = 'yc'.substr(md5(($target ?? '').($area ?? '')), 0, 6);
    $cvFil = app()->getLocale() === 'fil';
@endphp
<div class="{{ $converterWrap ?? '' }} yield-converter" data-yield-converter data-sack-kg="{{ \App\Support\YieldUnit::sackKg() }}"
     data-target-selector="{{ $target }}" data-area-selector="{{ $area }}"
     data-text-need-area="{{ $cvFil ? 'Ilagay ang laki ng inani (ektarya) para makuha ang t/ha.' : 'Enter the area harvested (hectares) to work out t/ha.' }}">
    <p class="yield-converter-title">{{ $cvFil ? 'O ilagay ang kabuuang ani mo:' : 'Or enter your total harvest:' }}</p>
    <div class="yield-converter-row">
        <label class="visually-hidden" for="{{ $cvId }}-amount">{{ $cvFil ? 'Kabuuang ani' : 'Total harvest' }}</label>
        <input class="{{ $converterInput ?? '' }}" id="{{ $cvId }}-amount" type="number" min="0" step="0.01" inputmode="decimal" data-yield-amount placeholder="{{ $cvFil ? 'Dami' : 'Amount' }}">
        <label class="visually-hidden" for="{{ $cvId }}-unit">{{ $cvFil ? 'Yunit' : 'Unit' }}</label>
        <select class="{{ $converterInput ?? '' }}" id="{{ $cvId }}-unit" data-yield-unit>
            @foreach (\App\Support\YieldUnit::inputUnits() as $unit => $unitLabel)
                @continue($unit === \App\Support\YieldUnit::T_PER_HA)
                <option value="{{ $unit }}">{{ $unitLabel }}</option>
            @endforeach
        </select>
    </div>
    <p class="{{ $converterHelp ?? '' }} yield-converter-result" data-yield-result aria-live="polite"></p>
    <p class="{{ $converterHelp ?? '' }}">{{ $cvFil
        ? 'Ani (t/ha) = kabuuang ani sa tonelada ÷ laki ng inani sa ektarya. 1 sako = '.rtrim(rtrim(number_format(\App\Support\YieldUnit::sackKg(), 1), '0'), '.').' kg.'
        : 'Yield (t/ha) = total harvest in tons ÷ area harvested in hectares. 1 sack = '.rtrim(rtrim(number_format(\App\Support\YieldUnit::sackKg(), 1), '0'), '.').' kg.' }}</p>
</div>
