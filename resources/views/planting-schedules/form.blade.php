<x-app-layout>
    @include('layouts.partials.dark-workspace')
    <div class="dark-workspace">
        <style>
            .schedule-form-card { max-width: 920px; margin: 0 auto; border: 1px solid var(--ic-border); border-radius: 14px; }
            @media (max-width: 575.98px) { .schedule-form-card .card-body { padding: 1rem !important; } }
        </style>

        <section class="page-hero">
            <div class="eyebrow mb-2">Farmer Calendar</div>
            <h1 class="h2 fw-bold mb-2">{{ $schedule->exists ? 'Edit My Planting Plan' : 'Add My Planting Plan' }}</h1>
            <p class="mb-0" style="color:var(--ic-ink-mid)">Record your field plan. Unusual dates show a warning, but you can still save them.</p>
        </section>

        <div class="card no-lift schedule-form-card">
            <div class="card-body p-4">
                @include('planting-schedules._form')
            </div>
        </div>
    </div>
</x-app-layout>
