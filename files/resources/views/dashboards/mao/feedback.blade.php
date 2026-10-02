@php $fb = $mao['feedback']; $labels = ['accurate' => 'Accurate', 'partly' => 'Partly accurate', 'different' => 'Different']; @endphp
@if ($fb)
    <section class="mtd-card" aria-labelledby="mtdFeedbackTitle" data-mao-feedback>
        <div class="mtd-card-head"><h2 id="mtdFeedbackTitle">Farmer Weather Feedback</h2><a class="mtd-link" href="{{ route('mao.operations') }}#farmer-weather-feedback">Details</a></div>
        <div class="mtd-tiles">
            @foreach ($labels as $key => $label)
                <div class="mtd-tile"><span class="mtd-tile-label">{{ $label }}</span><strong>{{ $fb['percentages'][$key] ?? 0 }}<small>%</small></strong></div>
            @endforeach
        </div>
        <p class="mtd-foot">{{ $fb['message'] }} Based on {{ number_format($fb['total']) }} farmer {{ \Illuminate\Support\Str::plural('report', $fb['total']) }}; reports are a guide, not proof.</p>
    </section>
@endif
