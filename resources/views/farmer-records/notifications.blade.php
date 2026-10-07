<x-app-layout>
    @php
        $tz = \App\Support\BusinessClock::timezone();
        $readFilter = request('is_read');
        $tabs = ['' => 'All', '0' => 'Unread', '1' => 'Read'];
        $tabUrl = fn ($value) => route('notifications.index', array_filter(request()->except(['is_read', 'page']) + ['is_read' => $value], fn ($v) => $v !== '' && $v !== null));
        $typeIcon = ['Advisory' => 'advisory', 'Warning' => 'alert', 'Announcement' => 'bell'];
        $typeTone = ['Advisory' => 'is-info', 'Warning' => 'is-watch', 'Announcement' => 'is-muted'];
    @endphp

    <div class="fad frec">
        <header class="fad-hero">
            <div class="fad-hero-text">
                <span class="fad-kicker">Farmer Notifications</span>
                <h1>Notifications</h1>
                <p>Updates, advisories and warnings sent to you by the Municipal Agriculture Office.</p>
            </div>
            @if($unreadCount > 0)
                <div class="fad-hero-actions">
                    <form method="POST" action="{{ route('notifications.mark-all-read') }}" data-loading="true">
                        @csrf
                        <button class="fad-btn is-light" type="submit" data-loading-text="Updating...">@include('dashboards.mao.icon', ['name' => 'check'])<span>Mark All as Read</span></button>
                    </form>
                </div>
            @endif
        </header>

        <nav class="frec-tabs" aria-label="Show notifications">
            @foreach($tabs as $value => $label)
                <a href="{{ $tabUrl($value) }}" @class(['frec-tab', 'is-active' => (string) $readFilter === (string) $value]) @if((string) $readFilter === (string) $value) aria-current="page" @endif>
                    {{ $label }}
                    @if($value === '0' && $unreadCount > 0)<span class="fad-count" aria-label="{{ $unreadCount }} unread">{{ $unreadCount }}</span>@endif
                    @if($value === '')<span class="frec-tab-count">{{ $totalCount }}</span>@endif
                </a>
            @endforeach
        </nav>

        @include('farmer-records._filters', ['placeholder' => 'Search titles and messages', 'hidden' => ['is_read']])

        <section class="frec-panel" aria-labelledby="frecListTitle">
            <div class="frec-panel-head is-row">
                <h2 id="frecListTitle">{{ $tabs[(string) $readFilter] ?? 'All' }} Notifications</h2>
                <p class="fad-support">Showing {{ $records->firstItem() ?? 0 }}–{{ $records->lastItem() ?? 0 }} of {{ $records->total() }}</p>
            </div>
            @if($records->count())
                <ul class="frec-notes">
                    @foreach($records as $note)
                        @php $when = $note->created_at?->copy()->timezone($tz); @endphp
                        <li @class(['frec-noteitem', 'is-unread' => ! $note->is_read]) data-ic-notif-row>
                            <span class="frec-note-icon" data-type="{{ strtolower((string) $note->type) }}">@include('dashboards.mao.icon', ['name' => $typeIcon[$note->type] ?? 'bell'])</span>
                            <div class="frec-note-body">
                                <div class="frec-note-tags">
                                    @if(! $note->is_read)<span class="fad-badge is-good">@include('dashboards.mao.icon', ['name' => 'info'])<span>New</span></span>@else<span class="fad-badge is-muted">Read</span>@endif
                                    @if($note->type)<span class="fad-badge {{ $typeTone[$note->type] ?? 'is-muted' }}">{{ $note->type }}</span>@endif
                                </div>
                                <h3 class="frec-note-title"><a href="{{ route('notifications.show', $note) }}" data-ic-view data-ic-size="normal" data-ic-notif-item aria-haspopup="dialog">{{ $note->title }}</a></h3>
                                @if($note->message)<p class="frec-note-text">{{ \Illuminate\Support\Str::limit($note->message, 180) }}</p>@endif
                                @if($when)<p class="fad-meta"><time datetime="{{ $note->created_at->toIso8601String() }}">{{ $when->format('M j, Y · g:i A') }}</time> · {{ $when->diffForHumans() }}</p>@endif
                            </div>
                            <div class="frec-note-actions">
                                {{-- Farmer – Naicel: a missing-info notification opens the page where it is added. --}}
                                @if($setupLink = \App\Services\Farmer\FarmerMissingInfoNotifier::linkFor($note->category, \App\Support\FarmerLanguage::resolve(request()), auth()->user()))
                                    <a class="fad-btn is-primary is-compact" href="{{ $setupLink['url'] }}" data-notif-setup>{{ $setupLink['label'] }}</a>
                                @endif
                                @if($note->type === 'Advisory')
                                    {{-- An advisory alert only points to the Advisories page, where the advice itself is read. --}}
                                    <a class="fad-btn is-primary is-compact" href="{{ route('planting-advisories.index') }}" data-notif-advisories>{{ __('farmer.advisories_page.open', [], \App\Support\FarmerLanguage::resolve(request())) }}</a>
                                @endif
                                <a class="fad-btn is-quiet is-compact" href="{{ route('notifications.show', $note) }}" data-ic-view data-ic-size="normal" data-ic-notif-item aria-haspopup="dialog">Open</a>
                                @if(! $note->is_read)
                                    <form method="POST" action="{{ route('notifications.mark-read', $note) }}" data-loading="true">
                                        @csrf @method('PATCH')
                                        <button class="fad-btn is-quiet is-compact" type="submit" data-loading-text="Updating...">Mark as Read</button>
                                    </form>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
                @if($records->hasPages())<div class="fad-pages">{{ $records->links() }}</div>@endif
            @elseif(($search ?? '') !== '' || request()->filled('type') || request()->filled('is_read'))
                <div class="fad-empty">
                    <p class="fad-lead">No notifications match.</p>
                    <p class="fad-body">Try another tab, type or search.</p>
                    <a class="fad-btn is-quiet" href="{{ route('notifications.index') }}">Show All Notifications</a>
                </div>
            @else
                <div class="fad-empty">
                    <p class="fad-lead">No notifications.</p>
                    <p class="fad-body">You have no farm notifications to review. Updates from the Municipal Agriculture Office will appear here.</p>
                </div>
            @endif
        </section>
    </div>
</x-app-layout>
