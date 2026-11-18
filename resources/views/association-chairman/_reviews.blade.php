@if ($reviews->isNotEmpty())
<section class="my-3" aria-labelledby="associationReviews">
    <h2 id="associationReviews" class="h5">MAO reviews and revision requests</h2>
    @foreach ($reviews as $review)
        <article class="border rounded p-3 mb-2">
            <strong>{{ str_replace('_', ' ', $review->action) }}</strong>
            <span class="text-muted">{{ $review->reviewer?->name }} · {{ $review->created_at?->timezone('Asia/Manila')->format('M d, Y H:i') }}</span>
            @if ($review->notes)<p class="mb-0">{{ $review->notes }}</p>@endif
        </article>
    @endforeach
</section>
@endif
