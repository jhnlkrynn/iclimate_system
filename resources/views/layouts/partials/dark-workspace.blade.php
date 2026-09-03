<style>
    .dark-workspace { color: var(--ic-ink-mid); }
    .dark-workspace .card, .dark-workspace .glass-card {
        background: linear-gradient(145deg, var(--ic-paper), #f7fbf8);
        border-color: var(--ic-sand-dark);
        color: var(--ic-ink-mid);
    }
    .dark-workspace .card:hover { border-color: var(--ic-green-300); }
    .dark-workspace .card-header { border-color: var(--ic-border) !important; background: rgba(13,31,24,.02); }
    .dark-workspace h1, .dark-workspace h2, .dark-workspace h3, .dark-workspace h4, .dark-workspace h5,
    .dark-workspace .fw-bold, .dark-workspace .fw-semibold { color: var(--ic-ink); }
    .dark-workspace .text-muted { color: var(--ic-muted) !important; }
    .dark-workspace small, .dark-workspace .small { color: var(--ic-muted); }
    .dark-workspace .stat-label { color: var(--ic-muted); }
    .dark-workspace .stat-value { color: var(--ic-ink); }
    .dark-workspace .table { color: var(--ic-ink-mid); }
    .dark-workspace .table thead th { background: var(--ic-green-50); color: var(--ic-muted); border-color: var(--ic-border); }
    .dark-workspace .table td, .dark-workspace .table th { color: var(--ic-ink) !important; border-color: var(--ic-border); }
    .dark-workspace .table-hover tbody tr:hover { background: rgba(13,31,24,.035) !important; }
    .dark-workspace .form-control, .dark-workspace .form-select {
        background-color: var(--ic-field);
        border-color: var(--ic-sand-dark);
        color: var(--ic-ink);
    }
    .dark-workspace .form-control::placeholder { color: var(--ic-muted); }
    .dark-workspace .form-control:hover, .dark-workspace .form-select:hover { border-color: var(--ic-green-300); background-color: var(--ic-panel); }
    .dark-workspace .form-select option { color: var(--ic-ink); }
    .dark-workspace .form-label { color: var(--ic-ink-mid); }
    .dark-workspace .empty-state { background: linear-gradient(135deg, #ffffff, var(--ic-green-50)); border-color: var(--ic-sand-dark) !important; color: var(--ic-ink-mid); }
    .dark-workspace .filter-panel { background: linear-gradient(135deg, #ffffff, var(--ic-green-50)); border-color: var(--ic-sand-dark) !important; }
    .dark-workspace .soft-section { background: linear-gradient(135deg, #ffffff, #f5f9f6); border-color: var(--ic-sand-dark) !important; }
    .dark-workspace .action-cluster { background: rgba(240,247,244,.76); border-color: var(--ic-sand-dark) !important; }
    .dark-workspace .module-tile, .dark-workspace .climate-chip {
        background: linear-gradient(135deg, #ffffff, var(--ic-green-50));
        border-color: var(--ic-sand-dark) !important;
        color: var(--ic-ink-mid);
    }
    .dark-workspace .details-list dd { background: rgba(240,247,244,.75); border-color: var(--ic-sand-dark) !important; color: var(--ic-ink-mid); }
    .dark-workspace .details-list dt { color: var(--ic-muted); }
    .dark-workspace .btn-outline-secondary { color: var(--ic-ink-mid) !important; border-color: var(--ic-sand-dark); }
    .dark-workspace .btn-outline-secondary:hover { background: var(--ic-green-50); border-color: var(--ic-green-300); color: var(--ic-ink) !important; }
    .dark-workspace .btn-outline-primary { color: var(--ic-green-700); border-color: var(--ic-green-500); }
    .dark-workspace .btn-outline-primary:hover { background: var(--ic-green-700); border-color: var(--ic-green-700); color: #fff; }
    .dark-workspace .badge.text-bg-light { background: var(--ic-green-50) !important; color: var(--ic-ink) !important; }
    .dark-workspace .border-top, .dark-workspace .border-bottom, .dark-workspace .border-start, .dark-workspace .border-end, .dark-workspace .border {
        border-color: var(--ic-border) !important;
    }
</style>
