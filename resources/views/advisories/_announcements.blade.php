{{-- Read-only Announcements for farmers: the MAO's community posts, newest first. No reactions, comments or posting
     (the full Community Feed stays with MAO and IT). Worded in the farmer's own language. --}}
@php $lang = \App\Support\FarmerLanguage::resolve(request()); @endphp
{{-- Farmer – Naicel (2026-10): with no announcements the section is left out (no "none yet" box). --}}
@if ($announcements->isNotEmpty())
<section class="fad-list fad-announcements" id="announcements" aria-labelledby="fadAnnouncementsTitle">
    <div class="fad-list-head">
        <h2 id="fadAnnouncementsTitle">{{ __('farmer.announcements.title', [], $lang) }}</h2>
        <p class="fad-support">{{ __('farmer.announcements.intro', [], $lang) }}</p>
    </div>
    @foreach ($announcements as $post)
        <article class="fad-card fad-announcement">
            <h3 class="fad-lead">{{ $post->title }}</h3>
            <p class="fad-meta">
                <span>{{ __('farmer.announcements.by', [], $lang) }}</span>
                <span>{{ $post->created_at?->copy()->timezone('Asia/Manila')->format('M j, Y') }}</span>
                @if ($post->event_date)<span>{{ __('farmer.announcements.event', ['date' => $post->event_date->copy()->timezone('Asia/Manila')->format('M j, Y')], $lang) }}</span>@endif
            </p>
            <p class="fad-body">{{ \Illuminate\Support\Str::limit((string) $post->body, 400) }}</p>
            @if ($post->media->isNotEmpty())
                <div class="fad-announcement-media" aria-label="Attachments">
                    @foreach ($post->media as $media)
                        @php $mediaUrl = $media->url(); @endphp
                        @if ($mediaUrl && $media->media_type === 'image')
                            <img src="{{ $mediaUrl }}" alt="{{ $media->original_name }}" loading="lazy">
                        @elseif ($mediaUrl && $media->media_type === 'video')
                            <video controls src="{{ $mediaUrl }}"></video>
                        @elseif ($mediaUrl)
                            <a class="fad-link" href="{{ $mediaUrl }}" target="_blank" rel="noopener">{{ $media->original_name }}</a>
                        @else
                            <span class="fad-support" role="status">Attachment unavailable: {{ $media->original_name }}</span>
                        @endif
                    @endforeach
                </div>
            @endif
        </article>
    @endforeach
</section>
@endif
