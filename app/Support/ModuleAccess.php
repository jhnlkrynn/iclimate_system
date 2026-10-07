<?php

namespace App\Support;

use App\Models\User;

/**
 * Manuscript – Naicel (2026-10-08), Phase 8: Roles & Permissions. Each module is a group of pages (route names) with
 * the roles the system supports for it — the manuscript's access matrix. IT can take a module away from a role on the
 * Roles & Permissions page (SystemSettings 'permissions'); it cannot give a role a module the system does not support
 * for it, and IT always keeps the IT modules. Nothing is restricted until IT changes something.
 */
class ModuleAccess
{
    private const F = User::ROLE_FARMER;

    private const M = User::ROLE_MAO;

    private const I = User::ROLE_IT_EXPERT;

    /** key => [label, route-name patterns, supported roles, roles that can never lose it] */
    public const MODULES = [
        'weather_analysis' => ['Weather Analysis', ['analysis.weather'], [self::F, self::M], []],
        'predictions' => ['Predictions / Forecast', ['weather-predictions.*'], [self::F, self::M, self::I], []],
        'climate_data' => ['Climate Data Management', ['climate-records.*', 'records.*'], [self::M, self::I], []],
        'production_data' => ['Production Data Management', ['rice-productions.*'], [self::M, self::I], []],
        'barangays' => ['Barangay / Location Management', ['mao.barangays.*'], [self::M], []],
        'farmer_management' => ['Farmer Management', ['farmer-profiles.*', 'mao.farmer-approvals.*'], [self::M, self::I], []],
        'impact_analysis' => ['Weather Impact Analysis', ['analysis.impact*'], [self::M], []],
        'yield_prediction' => ['Yield Prediction', ['analysis.yield*', 'analysis.forecast*'], [self::F, self::M], []],
        'advisories' => ['Planting Advisory', ['planting-advisories.*', 'management.advisories.*', 'calendar.*'], [self::F, self::M, self::I], []],
        'alerts' => ['Advisory Notifications (send)', ['mao.alerts.*'], [self::M], []],
        'heat_map' => ['Heat Map', ['heat-map.*'], [self::F, self::M, self::I], []],
        'reports' => ['Reports & Analytics', ['reports.*', 'farmer.reports.*'], [self::F, self::M, self::I], []],
        'community' => ['Announcements / Community Feed', ['community-feed.*'], [self::M, self::I], []],
        'messages' => ['Messages', ['messages.*'], [self::F, self::M, self::I], []],
        'user_management' => ['User Management', ['users.*'], [self::I], [self::I]],
        'permissions' => ['Roles & Permissions', ['admin.permissions.*'], [self::I], [self::I]],
        'system_logs' => ['System Logs / Audit Trail', ['system-logs.*', 'admin.system-logs.*'], [self::I], [self::I]],
        'backups' => ['Database Backup / Restore', ['admin.backups.*'], [self::I], [self::I]],
        'ml_models' => ['ML Model Management', ['model-registry.*', 'model-evaluation.*', 'admin.models.*'], [self::I], [self::I]],
        'settings' => ['System Settings & Security', ['admin.settings.*', 'system-health.*'], [self::I], [self::I]],
        'maintenance_records' => ['Maintenance Records', ['admin.maintenance.*'], [self::I], [self::I]],
    ];

    /** The roles allowed a module now: the stored choice, or every supported role. Locked roles are always in. */
    public static function allowed(string $module): array
    {
        [, , $supported, $locked] = self::MODULES[$module];
        $stored = SystemSettings::get('permissions', [])[$module] ?? null;
        $roles = is_array($stored) ? array_values(array_intersect($supported, $stored)) : $supported;

        return array_values(array_unique([...$roles, ...$locked]));
    }

    /** The module a route belongs to, or null (a page outside every module, e.g. Dashboard or Profile). */
    public static function moduleFor(?string $routeName): ?string
    {
        if ($routeName === null) {
            return null;
        }
        foreach (self::MODULES as $key => [, $patterns]) {
            foreach ($patterns as $pattern) {
                if (\Illuminate\Support\Str::is($pattern, $routeName)) {
                    return $key;
                }
            }
        }

        return null;
    }

    /**
     * False only when IT took this module away from the role. A role the module was never for is left to the route's
     * own role check (which answers 403), so this never changes what other roles see.
     */
    public static function permits(?string $role, ?string $routeName): bool
    {
        $module = self::moduleFor($routeName);
        if ($module === null || ! in_array($role, self::MODULES[$module][2], true)) {
            return true;
        }

        return in_array($role, self::allowed($module), true);
    }

    /** Saves the matrix from the form: module => roles ticked (only supported, non-locked roles are kept). */
    public static function save(array $ticked, ?int $userId): array
    {
        $permissions = [];
        foreach (self::MODULES as $key => [, , $supported, $locked]) {
            $roles = array_values(array_intersect($supported, (array) ($ticked[$key] ?? [])));
            $roles = array_values(array_unique([...$roles, ...$locked]));
            if (array_diff($supported, $roles) !== []) {
                $permissions[$key] = $roles;   // only modules with something taken away are stored
            }
        }

        return SystemSettings::set(['permissions' => $permissions], $userId);
    }
}
