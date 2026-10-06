{{-- Read-only Announcements for farmers: the MAO's community posts, newest first. No reactions, comments or posting
     (the full Community Feed stays with MAO and IT). Worded in the farmer's own language. --}}
@php $lang = \App\Support\FarmerLanguage::resolve(request()); @endphp
<section class="fad-list fad-announcements" id="announcements" aria-labelledby="fadAnnouncementsTitle">
    <div class="fad-list-head">
        <h2 id="fadAnnouncementsTitle">{{ __('farmer.announcements.title', [], $lang) }}</h2>
        <p class="fad-support">{{ __('farmer.announcements.intro', [], $lang) }}</p>
    </div>
    @forelse ($announcements as $post)
        <article class="fad-card fad-announcement">
            <h3 class="fad-lead">{{ $post->title }}</h3>
            <p class="fad-meta">
                <span>{{ __('farmer.announcements.by', [], $lang) }}</span>
                <span>{{ $post->created_at?->copy()->timezone('Asia/Manila')->format('M j, Y') }}</span>
                @if ($post->event_date)<span>{{ __('farmer.announcements.event', ['date' => $post->event_date->copy()->timezone('Asia/Manila')->format('M j, Y')], $lang) }}</span>@endif
            </p>
            <p class="fad-body">{{ \Illuminate\Support\Str::limit((string) $post->body, 400) }}</p>
        </article>
    @empty
        <x-no-data :label="__('farmer.announcements.none', [], $lang)" />
    @endforelse
</section>
