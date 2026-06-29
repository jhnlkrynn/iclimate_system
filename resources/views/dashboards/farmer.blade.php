<x-app-layout>
    <style>
        .farmer-console {
            --fc-ink: #0d1f18;
            --fc-muted: #5f7569;
            --fc-green: #2d6a4f;
            --fc-green-bright: #52b788;
            --fc-blue: #1677b8;
            --fc-gold: #f4b63f;
            --fc-red: #d85b45;
            --fc-line: rgba(153, 185, 160, .72);
        }
        .farmer-hero {
            position: relative;
            overflow: hidden;
            border-radius: 8px;
            padding: 1.35rem;
            margin-bottom: 1.25rem;
            color: #fff;
            background:
                radial-gradient(circle at 86% 16%, rgba(82,183,136,.26), transparent 30%),
                linear-gradient(135deg, #0d1f18 0%, #146b78 48%, #0d6a41 100%);
            box-shadow: 0 1rem 2.3rem rgba(13,31,24,.18);
        }
        .farmer-hero::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                linear-gradient(90deg, rgba(255,255,255,.08) 1px, transparent 1px),
                linear-gradient(0deg, rgba(255,255,255,.06) 1px, transparent 1px);
            background-size: 38px 38px;
            mask-image: linear-gradient(90deg, rgba(0,0,0,.78), transparent 88%);
        }
        .farmer-hero::after {
            content: "";
            position: absolute;
            left: 0;
            right: 0;
            bottom: 0;
            height: 7px;
            background: linear-gradient(90deg, var(--fc-gold), var(--fc-green-bright), var(--fc-blue));
        }
        .farmer-hero > * { position: relative; z-index: 1; }
        .farmer-eyebrow {
            font-size: .72rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .12em;
            color: rgba(255,255,255,.68);
        }
        .farmer-hero h1 { color: #fff; }
        .farmer-hero em { color: #9be5ba; font-style: italic; }
        .farmer-hero p { color: rgba(255,255,255,.66); max-width: 720px; }
        .field-chip {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            border: 1px solid rgba(255,255,255,.2);
            border-radius: 999px;
            padding: .45rem .75rem;
            background: rgba(255,255,255,.08);
            color: rgba(255,255,255,.88);
            font-size: .82rem;
            font-weight: 800;
        }
        .field-pulse {
            width: 10px;
            height: 10px;
            border-radius: 999px;
            background: var(--fc-green-bright);
            box-shadow: 0 0 0 6px rgba(82,183,136,.16);
        }
        .climate-grid {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 1rem;
            margin-bottom: 1.25rem;
        }
        .field-card {
            position: relative;
            overflow: hidden;
            border: 1px solid var(--fc-line);
            border-radius: 8px;
            background: linear-gradient(145deg, rgba(244,250,239,.97), rgba(229,242,226,.97));
            box-shadow: 0 .9rem 2rem rgba(20,32,51,.07);
            transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
        }
        .field-card:hover { transform: translateY(-2px); box-shadow: 0 1.1rem 2.2rem rgba(20,32,51,.11); border-color: rgba(31,143,85,.42); }
        .field-card::before {
            content: "";
            position: absolute;
            inset: 0 0 auto;
            height: 5px;
            background: var(--accent, var(--fc-green-bright));
        }
        .field-card::after {
            content: "";
            position: absolute;
            right: -28px;
            bottom: -30px;
            width: 98px;
            height: 98px;
            border-radius: 999px;
            background: color-mix(in srgb, var(--accent, var(--fc-green-bright)) 18%, transparent);
        }
        .field-card-body { padding: 1rem; position: relative; z-index: 1; }
        .field-label {
            color: var(--fc-muted);
            font-size: .72rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: .06em;
        }
        .field-value {
            margin-top: .35rem;
            font-size: clamp(1.6rem, 3vw, 2.25rem);
            line-height: 1;
            font-weight: 900;
            color: var(--fc-ink);
        }
        .field-note { color: var(--fc-muted); font-size: .84rem; margin-top: .65rem; }
        .tone-green { --accent: var(--fc-green-bright); }
        .tone-blue { --accent: var(--fc-blue); }
        .tone-gold { --accent: var(--fc-gold); }
        .tone-red { --accent: var(--fc-red); }
        .farmer-panel {
            border: 1px solid var(--fc-line);
            border-radius: 8px;
            background: linear-gradient(145deg, rgba(244,250,239,.97), rgba(232,244,230,.97));
            box-shadow: 0 .9rem 2rem rgba(20,32,51,.07);
            overflow: hidden;
        }
        .farmer-panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1rem .75rem;
            border-bottom: 1px solid rgba(153,185,160,.58);
            background: rgba(226,241,219,.58);
        }
        .farmer-panel-title { font-size: 1rem; font-weight: 900; margin: 0; color: var(--fc-ink); }
        .farmer-panel-sub { margin: .15rem 0 0; color: var(--fc-muted); font-size: .82rem; }
        .farmer-panel-body { padding: 1rem; }
        .farmer-list-item {
            display: flex;
            gap: .85rem;
            align-items: flex-start;
            padding: .85rem 0;
        }
        .farmer-list-item + .farmer-list-item { border-top: 1px solid rgba(153,185,160,.55); }
        .list-mark {
            width: 38px;
            height: 38px;
            border-radius: 10px;
            display: grid;
            place-items: center;
            background: #e2f1df;
            color: var(--fc-green);
            font-weight: 900;
            font-size: .76rem;
            flex: 0 0 auto;
        }
        .list-title { font-weight: 900; color: var(--fc-ink); line-height: 1.25; }
        .list-text { color: var(--fc-muted); font-size: .84rem; margin-top: .15rem; line-height: 1.45; }
        .list-meta { color: #7a8f82; font-size: .76rem; margin-top: .35rem; font-weight: 800; text-transform: uppercase; letter-spacing: .04em; }
        .status-pill {
            display: inline-flex;
            align-items: center;
            border-radius: 999px;
            padding: .32rem .58rem;
            font-size: .72rem;
            font-weight: 900;
            background: #d8f3dc;
            color: #1f6f4a;
        }
        .status-pill.muted { background: #e8eef6; color: #516579; }
        .advisory-card {
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid rgba(153,185,160,.72);
            background: linear-gradient(135deg, #edf7e7, #e3f1e7);
            min-height: 100%;
        }
        .advisory-card strong { color: var(--fc-ink); display: block; margin-bottom: .35rem; }
        .quick-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .85rem; }
        .quick-action {
            min-height: 104px;
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid rgba(153,185,160,.72);
            background: linear-gradient(135deg, #edf7e7, #dfeee8);
            color: inherit;
            text-decoration: none;
        }
        .quick-action:hover { transform: translateY(-2px); border-color: rgba(31,143,85,.55); }
        .quick-action strong { display: block; color: var(--fc-ink); }
        .quick-action span { display: block; color: var(--fc-muted); font-size: .82rem; margin-top: .25rem; }
        .empty-soft {
            border: 1px dashed #aac7b0;
            background: linear-gradient(135deg, #edf7e7, #e2f1df);
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
        }
        @media (max-width: 1199.98px) { .climate-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); } }
        @media (max-width: 767.98px) { .climate-grid, .quick-grid { grid-template-columns: 1fr; } .farmer-panel-header { align-items: flex-start; flex-direction: column; } }
    </style>

    @php
        $unreadNotifications = $notifications->where('is_read', false)->count();
        $latestAdvisory = $advisories->first();
        $profile = auth()->user()->farmerProfile;
    @endphp

    <div class="farmer-console">
        <section class="farmer-hero">
            <div class="d-flex flex-column flex-xl-row justify-content-between gap-4 align-items-xl-end">
                <div>
                    <div class="farmer-eyebrow mb-2">Farmer Field View</div>
                    <h1 class="h2 fw-bold mb-2">Climate-smart <em>farmer dashboard.</em></h1>
                    <p class="mb-0">Welcome back, {{ auth()->user()->name }}. Here is your latest climate summary, advisories, announcements, and notifications for Lian, Batangas.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <span class="field-chip"><span class="field-pulse"></span> {{ $climateSummary?->record_date?->format('M d, Y') ?? 'No climate record yet' }}</span>
                    <a class="btn btn-light fw-bold" href="{{ route('notifications.index') }}">Notifications</a>
                    <a class="btn btn-outline-light fw-bold" href="{{ route('profile.edit') }}">My Profile</a>
                </div>
            </div>
        </section>

        <section class="climate-grid">
            <a class="field-card tone-green text-decoration-none" href="{{ route('climate-records.index') }}">
                <div class="field-card-body">
                    <div class="field-label">Temperature</div>
                    <div class="field-value">{{ $climateSummary?->temperature !== null ? number_format($climateSummary->temperature, 1).' C' : 'N/A' }}</div>
                    <div class="field-note">Latest recorded field climate data</div>
                </div>
            </a>
            <a class="field-card tone-blue text-decoration-none" href="{{ route('climate-records.index') }}">
                <div class="field-card-body">
                    <div class="field-label">Rainfall</div>
                    <div class="field-value">{{ $climateSummary?->rainfall !== null ? number_format($climateSummary->rainfall, 1).' mm' : 'N/A' }}</div>
                    <div class="field-note">Use advisories before fertilizer application</div>
                </div>
            </a>
            <a class="field-card tone-gold text-decoration-none" href="{{ route('climate-records.index') }}">
                <div class="field-card-body">
                    <div class="field-label">Humidity</div>
                    <div class="field-value">{{ $climateSummary?->humidity !== null ? number_format($climateSummary->humidity, 1).'%' : 'N/A' }}</div>
                    <div class="field-note">Monitor crop disease risk after rain</div>
                </div>
            </a>
            <a class="field-card tone-red text-decoration-none" href="{{ route('planting-advisories.index') }}">
                <div class="field-card-body">
                    <div class="field-label">Unread Alerts</div>
                    <div class="field-value">{{ number_format($unreadNotifications) }}</div>
                    <div class="field-note">Farmer notifications needing review</div>
                </div>
            </a>
        </section>

        <div class="row g-4 mb-4">
            <div class="col-xl-8">
                <div class="farmer-panel h-100">
                    <div class="farmer-panel-header">
                        <div>
                            <h2 class="farmer-panel-title">Latest Planting Advisories</h2>
                            <p class="farmer-panel-sub">Published guidance from MAO Personnel.</p>
                        </div>
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('planting-advisories.index') }}">View All</a>
                    </div>
                    <div class="farmer-panel-body">
                        <div class="row g-3">
                            @forelse($advisories as $advisory)
                                <div class="col-md-6">
                                    <div class="advisory-card">
                                        <div class="d-flex justify-content-between gap-2 mb-2">
                                            <span class="status-pill">{{ $advisory->type }}</span>
                                            <span class="small text-muted">{{ $advisory->created_at?->format('M d') }}</span>
                                        </div>
                                        <strong>{{ $advisory->title }}</strong>
                                        <div class="list-text">{{ str($advisory->content)->limit(120) }}</div>
                                        <div class="list-meta">{{ $advisory->target_barangay ?: 'All barangays' }}</div>
                                    </div>
                                </div>
                            @empty
                                <div class="col-12"><div class="empty-soft"><strong>No advisories yet</strong><div class="small text-muted mt-1">Published planting advisories will appear here.</div></div></div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="farmer-panel h-100">
                    <div class="farmer-panel-header">
                        <div>
                            <h2 class="farmer-panel-title">Today&apos;s Field Guide</h2>
                            <p class="farmer-panel-sub">Quick action based on latest records.</p>
                        </div>
                    </div>
                    <div class="farmer-panel-body">
                        @if($latestAdvisory)
                            <div class="list-mark mb-3">ADV</div>
                            <div class="list-title">{{ $latestAdvisory->title }}</div>
                            <div class="list-text">{{ str($latestAdvisory->content)->limit(180) }}</div>
                            <div class="list-meta">{{ $latestAdvisory->type }} advisory</div>
                        @elseif($climateSummary)
                            <div class="list-mark mb-3">CLI</div>
                            <div class="list-title">Review latest climate conditions</div>
                            <div class="list-text">Season is {{ $climateSummary->season }} with {{ $climateSummary->rainfall }} mm rainfall recorded.</div>
                            <div class="list-meta">Source: {{ $climateSummary->source }}</div>
                        @else
                            <div class="empty-soft"><strong>No guide available</strong><div class="small text-muted mt-1">Advisories and climate records will appear here once published.</div></div>
                        @endif
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4 mb-4">
            <div class="col-xl-4">
                <div class="farmer-panel h-100">
                    <div class="farmer-panel-header">
                        <div>
                            <h2 class="farmer-panel-title">Announcements</h2>
                            <p class="farmer-panel-sub">Municipal updates for farmers.</p>
                        </div>
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('announcements.index') }}">Open</a>
                    </div>
                    <div class="farmer-panel-body">
                        @forelse($announcements as $announcement)
                            <div class="farmer-list-item">
                                <div class="list-mark">ANN</div>
                                <div>
                                    <div class="list-title">{{ $announcement->title }}</div>
                                    <div class="list-text">{{ str($announcement->content)->limit(90) }}</div>
                                    <div class="list-meta">{{ $announcement->category }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="empty-soft"><strong>No announcements</strong><div class="small text-muted mt-1">New announcements will appear here.</div></div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="farmer-panel h-100">
                    <div class="farmer-panel-header">
                        <div>
                            <h2 class="farmer-panel-title">Notifications</h2>
                            <p class="farmer-panel-sub">Messages sent to your account.</p>
                        </div>
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('notifications.index') }}">Open</a>
                    </div>
                    <div class="farmer-panel-body">
                        @forelse($notifications as $notification)
                            <div class="farmer-list-item">
                                <div class="list-mark">{{ $notification->is_read ? 'OK' : 'NEW' }}</div>
                                <div>
                                    <div class="list-title">{{ $notification->title }}</div>
                                    <div class="list-text">{{ str($notification->message)->limit(95) }}</div>
                                    <div class="list-meta">{{ $notification->type }} | {{ $notification->created_at?->diffForHumans() }}</div>
                                </div>
                            </div>
                        @empty
                            <div class="empty-soft"><strong>No notifications</strong><div class="small text-muted mt-1">You are all caught up.</div></div>
                        @endforelse
                    </div>
                </div>
            </div>

            <div class="col-xl-4">
                <div class="farmer-panel h-100">
                    <div class="farmer-panel-header">
                        <div>
                            <h2 class="farmer-panel-title">Farm Profile Snapshot</h2>
                            <p class="farmer-panel-sub">Your registered farmer details.</p>
                        </div>
                        <a class="btn btn-sm btn-outline-primary" href="{{ route('profile.edit') }}">Edit</a>
                    </div>
                    <div class="farmer-panel-body">
                        <div class="farmer-list-item pt-0">
                            <div class="list-mark">BRG</div>
                            <div><div class="list-title">{{ $profile?->barangay ?? auth()->user()->barangay ?? 'Barangay not set' }}</div><div class="list-text">Registered barangay</div></div>
                        </div>
                        <div class="farmer-list-item">
                            <div class="list-mark">FAR</div>
                            <div><div class="list-title">{{ $profile?->farm_area ? number_format($profile->farm_area, 2).' ha' : 'Farm area not set' }}</div><div class="list-text">Farm area</div></div>
                        </div>
                        <div class="farmer-list-item">
                            <div class="list-mark">TYP</div>
                            <div><div class="list-title">{{ $profile?->farm_type ?? 'Farm type not set' }}</div><div class="list-text">Farm irrigation type</div></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="farmer-panel">
            <div class="farmer-panel-header">
                <div>
                    <h2 class="farmer-panel-title">Farmer Quick Access</h2>
                    <p class="farmer-panel-sub">Open the modules available to your role.</p>
                </div>
            </div>
            <div class="farmer-panel-body">
                <div class="quick-grid">
                    <a class="quick-action" href="{{ route('climate-records.index') }}"><strong>Climate Records</strong><span>Review rainfall, temperature, humidity, wind, and season records.</span></a>
                    <a class="quick-action" href="{{ route('planting-advisories.index') }}"><strong>Planting Advisories</strong><span>Read MAO guidance for planting, irrigation, harvesting, and climate risks.</span></a>
                    <a class="quick-action" href="{{ route('announcements.index') }}"><strong>Announcements</strong><span>Check municipal news, events, trainings, and distributions.</span></a>
                    <a class="quick-action" href="{{ route('notifications.index') }}"><strong>Notifications</strong><span>View alerts and mark messages as read.</span></a>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>