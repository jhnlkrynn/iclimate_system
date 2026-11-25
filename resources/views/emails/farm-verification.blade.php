<!DOCTYPE html>
<html lang="{{ $localeCode }}">
<body style="font-family: Arial, sans-serif; color: #1f2d24; line-height: 1.5; max-width: 600px; margin: 0 auto; padding: 20px;">
    <h2 style="color: #2f5238; margin-bottom: 12px;">
        @if ($decision === \App\Models\Farm::VERIFICATION_APPROVED)
            {{ __('farmer.farm_verification_email.heading_approved', [], $localeCode) }}
        @else
            {{ __('farmer.farm_verification_email.heading_rejected', [], $localeCode) }}
        @endif
    </h2>

    <p>{{ __('farmer.farm_verification_email.greeting', ['name' => $farm->user?->name], $localeCode) }}</p>

    @if ($decision === \App\Models\Farm::VERIFICATION_APPROVED)
        <p>
            {{ __('farmer.farm_verification_email.body_approved', ['farm' => $farm->farm_name, 'barangay' => $farm->barangay], $localeCode) }}
        </p>
        <p>
            <a href="{{ route('farmer.my-farm') }}" style="display: inline-block; padding: 10px 16px; background: #2f5238; color: #fff; text-decoration: none; border-radius: 6px;">
                {{ __('farmer.farm_verification_email.open_my_farm', [], $localeCode) }}
            </a>
        </p>
    @else
        <p>
            {{ __('farmer.farm_verification_email.body_rejected', ['farm' => $farm->farm_name, 'barangay' => $farm->barangay], $localeCode) }}
        </p>
        @if ($reason)
            <p style="padding: 10px 14px; background: #fdf2f2; border-left: 4px solid #c53030; color: #742a2a;">
                <strong>{{ __('farmer.farm_verification_email.reason_label', [], $localeCode) }}</strong> {{ $reason }}
            </p>
        @endif
        <p>
            <a href="{{ route('farmer.farms.edit', $farm) }}" style="display: inline-block; padding: 10px 16px; background: #2f5238; color: #fff; text-decoration: none; border-radius: 6px;">
                {{ __('farmer.farm_verification_email.fix_resubmit', [], $localeCode) }}
            </a>
        </p>
    @endif
</body>
</html>
