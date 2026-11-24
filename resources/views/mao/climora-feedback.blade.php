<x-app-layout>
    <x-slot:title>Climora Feedback</x-slot:title>
    <div class="mfd itd itp" data-mao-climora-feedback>
        <x-shared.page-header
            kicker="Municipal Agriculture Office"
            title="Climora AI Feedback"
            :lede="$isFiltered
                ? 'Unhelpful (👎) Climora answers from farmers in your assigned barangays: '.implode(', ', $scopedBarangays ?? []).'.'
                : 'All unhelpful (👎) Climora answers across Lian, Batangas.'" />

        <section class="mfd-card p-0" aria-label="Unhelpful Climora answers">
            @if ($items->isEmpty())
                <div class="p-4 text-muted">
                    No unhelpful (👎) Climora answers have been reported{{ $isFiltered ? ' in your assigned barangays' : '' }} yet.
                </div>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Farmer</th>
                                <th>Barangay</th>
                                <th>Question</th>
                                <th>Climora Answer</th>
                                <th>Reason</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($items as $item)
                                @php
                                    $farmer = $item->user;
                                    $barangay = $farmer?->farms?->first()?->barangay
                                        ?? $farmer?->farmerProfile?->barangay
                                        ?? $farmer?->barangay
                                        ?? '—';
                                @endphp
                                <tr>
                                    <td class="text-nowrap small text-muted">
                                        {{ ($item->feedback_at ?? $item->created_at)?->timezone(\App\Support\BusinessClock::timezone())->format('M j, Y g:i A') }}
                                    </td>
                                    <td>{{ $farmer?->name ?? 'Unknown' }}</td>
                                    <td>{{ $barangay }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($item->question, 140) }}</td>
                                    <td>{{ \Illuminate\Support\Str::limit($item->answer, 180) }}</td>
                                    <td>{{ $item->feedback_reason ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                @if ($items->hasPages())
                    <div class="p-3 border-top">
                        {{ $items->links() }}
                    </div>
                @endif
            @endif
        </section>
    </div>
</x-app-layout>
