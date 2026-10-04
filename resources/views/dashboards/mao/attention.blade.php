@php $items = $mao['attention']; @endphp
@if (count($items) === 0)
    <section class="mtd-card mtd-attention is-clear" aria-label="MAO Attention Center" data-mao-attention>
        <p class="mtd-allgood">@include('dashboards.mao.icon', ['name' => 'check']) No urgent agricultural actions at this time.</p>
    </section>
@else
    <section class="mtd-card mtd-attention" aria-labelledby="mtdAttentionTitle" data-mao-attention>
        <div class="mtd-card-head">
            <h2 id="mtdAttentionTitle">MAO Attention Center</h2>
            <span class="mtd-count">{{ count($items) }}</span>
        </div>
        <ul class="mtd-attn-list">
            @foreach ($items as $item)
                <li class="mtd-attn is-{{ $item['severity'] }}">
                    <span class="mtd-attn-icon">@include('dashboards.mao.icon', ['name' => $item['icon']])</span>
                    <div class="mtd-attn-body">
                        <h3>{{ $item['title'] }}</h3>
                        <span>{{ $item['detail'] }}</span>
                    </div>
                    <a class="mtd-btn" href="{{ $item['href'] }}">{{ $item['action'] }}</a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
