@php
    $typeClass = [
        'climate' => 'text-bg-primary',
        'planting' => 'text-bg-success',
        'harvesting' => 'text-bg-success',
        'irrigation' => 'text-bg-info',
    ][$advisory->advisory_type] ?? 'text-bg-light';
    $severityClass = [
        'information' => 'text-bg-light',
        'low' => 'text-bg-success',
        'moderate' => 'text-bg-warning',
        'high' => 'text-bg-danger',
        'critical' => 'text-bg-dark',
    ][$advisory->severity] ?? 'text-bg-light';
    $statusClass = [
        'pending_review' => 'text-bg-warning',
        'published' => 'text-bg-success',
        'expired' => 'text-bg-secondary',
        'rejected' => 'text-bg-danger',
        'archived' => 'text-bg-dark',
    ][$advisory->status] ?? 'text-bg-light';
@endphp
