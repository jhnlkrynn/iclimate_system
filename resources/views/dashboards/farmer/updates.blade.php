{{-- "Reminders & Updates": short reminders, the newest community post, and the unread messages and notifications.
     Each count is read the way its own page reads it, and each links there. Nothing from a thread is shown here. --}}
@php
    $reminders = $f['reminders'];
    $post = $f['community'];
    $messages = $f['messages'];
    $unread = $f['unread'];
@endphp
<section class="mfd-card mfd-updates" aria-labelledby="mfdUpdatesTitle" data-mfd-updates>
    <div class="mfd-card-head"><h2 id="mfdUpdatesTitle">{{ __('farmer.updates.title') }}</h2></div>

    <h3 class="mfd-sub">{{ __('farmer.updates.reminders') }}</h3>
    @if ($reminders === [])
        <p class="mfd-note is-good">@include('dashboards.mao.icon', ['name' => 'check'])<span>{{ __('farmer.updates.none') }}</span></p>
    @else
        <ul class="mfd-reminders">
            @foreach ($reminders as $item)
                <li data-reminder="{{ $item['key'] }}">
                    @include('dashboards.mao.icon', ['name' => $item['icon']])
                    <a href="{{ $item['href'] }}" @if (! empty($item['anchor'])) data-no-loader data-fast-nav-bound="true" @endif>{{ $item['text'] }}</a>
                </li>
            @endforeach
        </ul>
    @endif

    <div class="mfd-update-grid">
        <div class="mfd-update" data-update="community">
            <span class="mfd-kpi-label">@include('dashboards.mao.icon', ['name' => 'farmers']){{ __('farmer.updates.community') }}</span>
            @if ($post)
                <strong>{{ $post['title'] }}</strong>
                @if ($post['summary'] && $post['summary'] !== $post['title'])<p class="mfd-muted">{{ $post['summary'] }}</p>@endif
                <p class="mfd-adv-meta">{{ __('farmer.updates.community_by', ['author' => $post['author'], 'date' => $post['when']?->format('M j') ?? '']) }}</p>
            @else
                <p class="mfd-muted">{{ __('farmer.updates.community_none') }}</p>
            @endif
            <a class="mfd-link" href="{{ route('community-feed.index') }}">{{ __('farmer.updates.view_community') }}</a>
        </div>
        <div class="mfd-update" data-update="messages">
            <span class="mfd-kpi-label">@include('dashboards.mao.icon', ['name' => 'message']){{ __('farmer.updates.messages') }}</span>
            @if ($messages !== null)
                <strong @class(['mfd-count', 'is-new' => $messages > 0])>{{ trans_choice('farmer.updates.messages_count', $messages, ['count' => $messages]) }}</strong>
            @else
                <p class="mfd-muted">{{ __('farmer.updates.messages_unknown') }}</p>
            @endif
            <a class="mfd-link" href="{{ route('messages.index') }}">{{ __('farmer.updates.view_messages') }}</a>
        </div>
        <div class="mfd-update" data-update="notifications">
            <span class="mfd-kpi-label">@include('dashboards.mao.icon', ['name' => 'bell']){{ __('farmer.updates.notifications') }}</span>
            @if ($unread !== null)
                <strong @class(['mfd-count', 'is-new' => $unread > 0])>{{ trans_choice('farmer.updates.notifications_count', $unread, ['count' => $unread]) }}</strong>
            @endif
            <a class="mfd-link" href="{{ route('notifications.index') }}">{{ __('farmer.updates.view_notifications') }}</a>
        </div>
    </div>
</section>
