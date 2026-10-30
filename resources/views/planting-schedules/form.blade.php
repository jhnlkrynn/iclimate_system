<x-app-layout>
    @include('layouts.partials.dark-workspace')
    <div class="dark-workspace schedule-form-page">
        <style>
            .schedule-form-page { width: 100%; max-width: none; margin: 0; }
            .schedule-form-page .page-hero { padding: 1.1rem 1.35rem; margin-bottom: 1rem; }
            .schedule-form-page .page-hero h1 { margin-bottom: .35rem !important; font-size: clamp(1.45rem, 2.4vw, 2rem); line-height: 1.15; }
            .schedule-form-page .page-hero p { max-width: 70ch; line-height: 1.45; }
            .schedule-form-card { width: 100%; margin: 0 auto; border: 1px solid var(--ic-border); border-radius: 14px; }
            .schedule-form-card .card-body { padding: clamp(1.25rem, 2.2vw, 2rem) !important; }
            .schedule-form-card .schedule-form { width: 100%; max-width: none; margin: 0; }
            .schedule-form-page .schedule-form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 1.15rem 1.35rem; align-items: start; }
            .schedule-form-page .schedule-form-field { min-width: 0; }
            .schedule-form-page .schedule-form-field.is-full { grid-column: 1 / -1; }
            .schedule-form-page .schedule-form-field > label,
            .schedule-form-page .schedule-form-field > legend { display: block; margin: 0 0 .42rem; }
            .schedule-form-page .schedule-form-field .form-control,
            .schedule-form-page .schedule-form-field .form-select { width: 100%; min-height: 50px; }
            .schedule-form-page .schedule-form-field textarea.form-control { min-height: 112px; resize: vertical; }
            .schedule-form-page .schedule-help { margin-top: .42rem !important; line-height: 1.45; }
            .schedule-form-page .season-guide,
            .schedule-form-page .schedule-date-warning { display: flex; align-items: baseline; gap: .35rem .55rem; margin: 0 0 1rem !important; padding: .75rem .95rem; border-radius: 10px; line-height: 1.45; }
            .schedule-form-page .season-guide { background: color-mix(in srgb, var(--ic-green-100) 78%, var(--ic-paper)); border: 1px solid color-mix(in srgb, var(--ic-green-500) 22%, var(--ic-border)); }
            .schedule-form-page .schedule-date-warning { background: color-mix(in srgb, #e6b84b 15%, var(--ic-paper)); border: 1px solid color-mix(in srgb, #b67a12 28%, var(--ic-border)); color: var(--ic-ink); }
            .schedule-form-page .rv-plant-fields { gap: 1rem; padding: 1rem; border-radius: 10px; }
            .schedule-form-page .rv-plant-fields .rv-choice-row { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: .65rem; }
            .schedule-form-page .rv-plant-fields .rv-choice,
            .schedule-form-page .rv-plant-fields .rv-seg { min-width: 0; }
            .schedule-form-page .rv-plant-fields .rv-choice span,
            .schedule-form-page .rv-plant-fields .rv-seg span { width: 100%; justify-content: center; min-height: 48px; padding: .55rem .7rem; text-align: center; line-height: 1.25; }
            .schedule-form-page .rv-plant-fields .rv-segmented { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .65rem; }
            .schedule-form-page .rv-combo-input { min-height: 50px; padding-right: 2.5rem; background-image: linear-gradient(45deg, transparent 50%, var(--ic-green-700) 50%), linear-gradient(135deg, var(--ic-green-700) 50%, transparent 50%); background-position: calc(100% - 17px) 22px, calc(100% - 12px) 22px; background-size: 5px 5px, 5px 5px; background-repeat: no-repeat; }
            .schedule-form-page .rv-combo-list { max-height: 300px; border-color: var(--ic-green-300); }
            .schedule-form-page .schedule-form-actions { display: flex; flex-wrap: wrap; gap: .7rem; margin-top: 1.55rem !important; padding-top: 1.25rem; border-top: 1px solid var(--ic-border); }
            .schedule-form-page .schedule-form-actions .btn { min-width: 150px; min-height: 50px; }
            @media (max-width: 767.98px) {
                .schedule-form-page .page-hero { padding: .95rem 1rem; }
                .schedule-form-page .schedule-form-grid { grid-template-columns: minmax(0, 1fr); gap: 1rem; }
                .schedule-form-page .schedule-form-field.is-full { grid-column: auto; }
                .schedule-form-page .schedule-form-actions { flex-direction: column; }
                .schedule-form-page .schedule-form-actions .btn { width: 100%; }
            }
            @media (max-width: 480px) {
                .schedule-form-card .card-body { padding: 1rem !important; }
                .schedule-form-page .rv-plant-fields { padding: .85rem; }
                .schedule-form-page .rv-plant-fields .rv-choice-row,
                .schedule-form-page .rv-plant-fields .rv-segmented { grid-template-columns: minmax(0, 1fr); }
                .schedule-form-page .season-guide,
                .schedule-form-page .schedule-date-warning { align-items: flex-start; flex-direction: column; gap: .2rem; }
            }
            @media (min-width: 1400px) {
                .schedule-form-page .schedule-form-grid { grid-template-columns: repeat(3, minmax(0, 1fr)); column-gap: 1.2rem; }
            }
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
