@props(['compact' => false])

<span class="badge text-bg-warning" data-ml-status="unvalidated-estimate">Preliminary estimate</span>
@unless ($compact)
    <span class="small text-muted" data-ml-status-note>Accuracy not verified; this is not a scientifically validated prediction.</span>
@endunless
