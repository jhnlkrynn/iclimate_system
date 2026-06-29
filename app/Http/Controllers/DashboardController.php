<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\ClimateRecord;
use App\Models\FarmerProfile;
use App\Models\HeatmapArea;
use App\Models\Notification as UserNotification;
use App\Models\PlantingAdvisory;
use App\Models\Report;
use App\Models\RiceProduction;
use App\Models\SystemLog;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function farmer(Request $request): View
    {
        return view('dashboards.farmer', $this->stats() + [
            'announcements' => Announcement::query()->where('status', 'Published')->latest()->take(5)->get(),
            'advisories' => PlantingAdvisory::query()->where('status', 'Published')->latest()->take(5)->get(),
            'notifications' => UserNotification::query()->where('user_id', $request->user()->id)->latest()->take(5)->get(),
            'climateSummary' => ClimateRecord::query()->latest('record_date')->first(),
        ]);
    }

    public function mao(): View
    {
        return view('dashboards.mao', $this->stats() + [
            'latestClimate' => ClimateRecord::query()->latest('record_date')->first(),
            'recentClimateRecords' => ClimateRecord::query()->latest('record_date')->take(5)->get(),
            'recentRiceProductions' => RiceProduction::query()->latest()->take(5)->get(),
            'latestAdvisories' => PlantingAdvisory::query()->latest()->take(5)->get(),
            'latestAnnouncements' => Announcement::query()->latest()->take(4)->get(),
            'latestHeatmapAreas' => HeatmapArea::query()->latest()->take(5)->get(),
            'latestReports' => Report::query()->with('generatedBy')->latest()->take(4)->get(),
            'riceProductionTotal' => RiceProduction::query()->sum('total_production'),
            'riceAreaTotal' => RiceProduction::query()->sum('area_hectares'),
            'profileCount' => FarmerProfile::count(),
            'reportCount' => Report::count(),
            'heatMapCount' => HeatmapArea::count(),
            'publishedAdvisoryCount' => PlantingAdvisory::query()->where('status', 'Published')->count(),
            'publishedAnnouncementCount' => Announcement::query()->where('status', 'Published')->count(),
        ]);
    }

    public function admin(): View
    {
        return view('dashboards.it-expert', $this->stats() + [
            'userCount' => User::count(),
            'activeUsers' => User::query()->where('status', User::STATUS_ACTIVE)->count(),
            'inactiveUsers' => User::query()->where('status', User::STATUS_INACTIVE)->count(),
            'logCount' => SystemLog::count(),
            'roleCounts' => [
                User::ROLE_FARMER => User::query()->where('role', User::ROLE_FARMER)->count(),
                User::ROLE_MAO => User::query()->where('role', User::ROLE_MAO)->count(),
                User::ROLE_IT_EXPERT => User::query()->where('role', User::ROLE_IT_EXPERT)->count(),
            ],
            'moduleCounts' => [
                'Farmer Profiles' => FarmerProfile::count(),
                'Climate Records' => ClimateRecord::count(),
                'Rice Productions' => RiceProduction::count(),
                'Advisories' => PlantingAdvisory::count(),
                'Announcements' => Announcement::count(),
                'Notifications' => UserNotification::count(),
                'Heat Map Areas' => HeatmapArea::count(),
                'Reports' => Report::count(),
            ],
            'latestLogs' => SystemLog::query()->with('user')->latest()->take(6)->get(),
            'latestUsers' => User::query()->latest()->take(5)->get(),
            'latestReports' => Report::query()->with('generatedBy')->latest()->take(5)->get(),
        ]);
    }

    private function stats(): array
    {
        return [
            'totalFarmers' => User::query()->where('role', User::ROLE_FARMER)->count(),
            'totalClimateRecords' => ClimateRecord::count(),
            'totalRiceProductions' => RiceProduction::count(),
            'totalAdvisories' => PlantingAdvisory::count(),
            'totalAnnouncements' => Announcement::count(),
            'totalNotifications' => UserNotification::count(),
        ];
    }
}