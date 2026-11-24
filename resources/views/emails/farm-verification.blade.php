<!DOCTYPE html>
<html lang="{{ $localeCode }}">
<body style="font-family: Arial, sans-serif; color: #1f2d24; line-height: 1.5; max-width: 600px; margin: 0 auto; padding: 20px;">
    @php($fil = $localeCode === 'fil')
    <h2 style="color: #2f5238; margin-bottom: 12px;">
        @if ($decision === \App\Models\Farm::VERIFICATION_APPROVED)
            {{ $fil ? 'Aprubado na ang iyong bukid' : 'Your farm has been approved' }}
        @else
            {{ $fil ? 'Hindi pa naaprubahan ang iyong bukid' : 'Your farm was not approved' }}
        @endif
    </h2>

    <p>{{ $fil ? 'Magandang araw,' : 'Hello,' }} {{ $farm->user?->name }},</p>

    @if ($decision === \App\Models\Farm::VERIFICATION_APPROVED)
        <p>
            {{ $fil
                ? "Inaprubahan ng Municipal Agriculture Office (MAO) ang iyong bukid na \"{$farm->farm_name}\" ({$farm->barangay}). Kasama na ito sa iyong dashboard at mga kalkulasyon ng ani."
                : "The Municipal Agriculture Office (MAO) has approved your farm \"{$farm->farm_name}\" ({$farm->barangay}). It is now active on your dashboard and included in yield estimates." }}
        </p>
        <p>
            <a href="{{ route('farmer.my-farm') }}" style="display: inline-block; padding: 10px 16px; background: #2f5238; color: #fff; text-decoration: none; border-radius: 6px;">
                {{ $fil ? 'Buksan ang My Farm' : 'Open My Farm' }}
            </a>
        </p>
    @else
        <p>
            {{ $fil
                ? "Hindi naaprubahan ng MAO ang iyong bukid na \"{$farm->farm_name}\" ({$farm->barangay})."
                : "The MAO did not approve your farm \"{$farm->farm_name}\" ({$farm->barangay})." }}
        </p>
        @if ($reason)
            <p style="padding: 10px 14px; background: #fdf2f2; border-left: 4px solid #c53030; color: #742a2a;">
                <strong>{{ $fil ? 'Dahilan:' : 'Reason:' }}</strong> {{ $reason }}
            </p>
        @endif
        <p>
            <a href="{{ route('farmer.farms.edit', $farm) }}" style="display: inline-block; padding: 10px 16px; background: #2f5238; color: #fff; text-decoration: none; border-radius: 6px;">
                {{ $fil ? 'Ayusin at ipasa muli' : 'Fix and resubmit' }}
            </a>
        </p>
    @endif
</body>
</html>
