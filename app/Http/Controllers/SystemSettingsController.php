<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Risk\BarangayWeekRiskService;
use App\Services\SystemAuditLogger;
use App\Support\ModuleAccess;
use App\Support\RiceSeason;
use App\Support\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/**
 * Manuscript – Naicel (2026-10-08), Phase 8: System Settings, Security and Roles & Permissions (IT). Everything here is
 * saved in the settings file (App\Support\SystemSettings) and used by the parts of the system named next to each field.
 */
class SystemSettingsController extends Controller
{
    public function general(): View
    {
        return view('admin.settings', [
            'settings' => SystemSettings::all(),
            'seasons' => ['Wet' => RiceSeason::WET_MONTHS],
            'riskDefaults' => [
                'risk_heavy_rain_mm' => BarangayWeekRiskService::HEAVY_RAIN_MM,
                'risk_very_heavy_rain_mm' => BarangayWeekRiskService::VERY_HEAVY_RAIN_MM,
                'risk_strong_wind_kmh' => BarangayWeekRiskService::STRONG_WIND_KMH,
                'risk_storm_wind_kmh' => BarangayWeekRiskService::STORM_WIND_KMH,
                'risk_hot_day_c' => BarangayWeekRiskService::HOT_DAY_C,
            ],
            'farmerApproval' => SystemSettings::farmerApproval(),
            'logoUrl' => SystemSettings::get('logo_path') ? Storage::disk('public')->url((string) SystemSettings::get('logo_path')) : null,
        ]);
    }

    public function saveGeneral(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'system_name' => ['required', 'string', 'max:60'],
            'office_name' => ['required', 'string', 'max:150'],
            'weather_source_note' => ['nullable', 'string', 'max:300'],
            'logo' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp', 'max:1024'],
            'heavy_rain_month_mm' => ['required', 'numeric', 'between:50,2000'],
            'dry_month_mm' => ['required', 'numeric', 'between:0,300'],
            'hot_month_c' => ['required', 'numeric', 'between:20,40'],
            'risk_heavy_rain_mm' => ['nullable', 'numeric', 'between:5,300'],
            'risk_very_heavy_rain_mm' => ['nullable', 'numeric', 'between:10,500'],
            'risk_strong_wind_kmh' => ['nullable', 'numeric', 'between:10,200'],
            'risk_storm_wind_kmh' => ['nullable', 'numeric', 'between:20,300'],
            'risk_hot_day_c' => ['nullable', 'numeric', 'between:25,50'],
            'maintenance_message' => ['required', 'string', 'max:300'],
        ]);

        $values = collect($data)->except('logo')->map(fn ($v) => is_numeric($v) ? $v + 0 : $v)->all();
        $values['notify_farmers_on_publish'] = $request->boolean('notify_farmers_on_publish');
        $values['farmer_approval'] = $request->boolean('farmer_approval');
        $values['maintenance_mode'] = $request->boolean('maintenance_mode');
        if ($request->hasFile('logo')) {
            $values['logo_path'] = $request->file('logo')->store('branding', 'public');
        } elseif ($request->boolean('remove_logo')) {
            $values['logo_path'] = null;
        }

        $changed = SystemSettings::set($values, $request->user()->getKey());
        $this->audit($request, 'Updated System Settings', $changed);

        return redirect()->route('admin.settings.index')->with('success', $changed === [] ? 'Nothing changed.' : 'Settings saved.');
    }

    public function security(Request $request): View
    {
        return view('admin.security', [
            'settings' => SystemSettings::all(),
            'sessionMinutes' => (int) config('session.lifetime'),
            'https' => [
                'request' => $request->isSecure(),
                'app_url' => str_starts_with((string) config('app.url'), 'https://'),
                'secure_cookie' => (bool) config('session.secure'),
                'debug_off' => ! config('app.debug'),
            ],
        ]);
    }

    public function saveSecurity(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'session_timeout_minutes' => ['required', 'integer', 'between:5,1440'],
            'password_min_length' => ['required', 'integer', 'between:8,64'],
            'lockout_attempts' => ['required', 'integer', 'between:3,20'],
            'lockout_minutes' => ['required', 'integer', 'between:1,120'],
        ]);
        $values = array_map('intval', $data) + [
            'password_mixed_case' => $request->boolean('password_mixed_case'),
            'password_numbers' => $request->boolean('password_numbers'),
            'password_symbols' => $request->boolean('password_symbols'),
        ];

        $changed = SystemSettings::set($values, $request->user()->getKey());
        $this->audit($request, 'Updated Security Settings', $changed);

        return redirect()->route('admin.settings.security')->with('success', $changed === [] ? 'Nothing changed.' : 'Security settings saved. New sessions and new passwords follow them.');
    }

    public function permissions(): View
    {
        return view('admin.permissions', [
            'modules' => ModuleAccess::MODULES,
            'allowed' => collect(ModuleAccess::MODULES)->keys()->mapWithKeys(fn ($k) => [$k => ModuleAccess::allowed($k)])->all(),
            'roles' => [User::ROLE_FARMER => 'Farmer', User::ROLE_MAO => 'MAO', User::ROLE_IT_EXPERT => 'IT'],
        ]);
    }

    public function savePermissions(Request $request): RedirectResponse
    {
        $changed = ModuleAccess::save((array) $request->input('modules', []), $request->user()->getKey());
        $this->audit($request, 'Updated Role Permissions', $changed);

        return redirect()->route('admin.permissions.index')->with('success', $changed === [] ? 'Nothing changed.' : 'Permissions saved.');
    }

    private function audit(Request $request, string $action, array $changed): void
    {
        if ($changed !== []) {
            SystemAuditLogger::record($action, $request, ['changed' => array_keys($changed)]);
        }
    }
}
