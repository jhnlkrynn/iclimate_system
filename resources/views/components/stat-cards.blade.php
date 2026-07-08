<div class="row g-3 g-xl-4 mb-4">
    @foreach ([
        ['Total Farmers', $totalFarmers ?? 0, 'green', 'Farmers'],
        ['Climate Records', $totalClimateRecords ?? 0, 'blue', 'Climate'],
        ['Rice Productions', $totalRiceProductions ?? 0, 'amber', 'Harvest'],
        ['Advisories', $totalAdvisories ?? 0, 'coral', 'Guidance'],
        ['Community Posts', $totalCommunityPosts ?? 0, 'green', 'Feed'],
        ['Notifications', $totalNotifications ?? 0, 'blue', 'Alerts'],
    ] as [$label, $value, $tone, $tag])
        <div class="col-sm-6 col-xl-4 col-xxl-2">
            <div class="card stat-card h-100 tone-{{ $tone }}">
                <div class="card-body position-relative">
                    <div class="d-flex align-items-start justify-content-between gap-3">
                        <div>
                            <div class="stat-label">{{ $label }}</div>
                            <div class="stat-value">{{ is_numeric($value) ? number_format($value) : $value }}</div>
                            <div class="small text-muted mt-2">{{ $tag }}</div>
                        </div>
                        <div class="stat-dot" aria-hidden="true"></div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach
</div>
