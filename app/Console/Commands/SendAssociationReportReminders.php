<?php

namespace App\Console\Commands;

use App\Models\AssociationReminderDelivery;
use App\Models\AssociationReportingPeriod;
use App\Models\AssociationYieldReport;
use App\Models\FarmerOrganization;
use App\Models\Notification;
use Illuminate\Console\Command;

class SendAssociationReportReminders extends Command
{
    protected $signature = 'associations:send-reminders';
    protected $description = 'Send idempotent in-app reminders for missing association rice-yield reports';

    public function handle(): int
    {
        foreach (['association_reporting_periods', 'association_yield_reports', 'association_chairman_assignments', 'association_reminder_deliveries'] as $table) {
            if (! \App\Support\SchemaCache::hasTable($table)) {
                $this->warn('Association reporting tables are not migrated yet; no reminders sent.');

                return self::SUCCESS;
            }
        }
        $today = now()->startOfDay();
        $beforeDays = (array) config('associations.reminder_days_before', [7, 1]);
        $periods = AssociationReportingPeriod::query()->where('required', true)->get();
        foreach ($periods as $period) {
            $kind = null;
            foreach ($beforeDays as $days) if ($today->isSameDay($period->due_at->copy()->startOfDay()->subDays((int) $days))) $kind = 'before_'.$days;
            if ($today->isSameDay($period->due_at->copy()->startOfDay())) $kind = 'due';
            if ($today->greaterThan($period->due_at->copy()->startOfDay())) $kind = 'overdue_'.(int) floor($period->due_at->diffInDays($today) / max(1, (int) config('associations.overdue_interval_days', 7)));
            if ($kind === null) continue;
            FarmerOrganization::query()->with(['chairmanAssignments' => fn ($q) => $q->where('status', 'active')->whereHas('user', fn ($users) => $users->where('role', \App\Models\User::ROLE_FARMER)->where('status', \App\Models\User::STATUS_ACTIVE))])->each(function (FarmerOrganization $association) use ($period, $kind): void {
                if ($association->yieldReports()->where('reporting_period_id', $period->id)->whereIn('status', [AssociationYieldReport::SUBMITTED, AssociationYieldReport::UNDER_REVIEW, AssociationYieldReport::NEEDS_REVISION, AssociationYieldReport::ACCEPTED])->exists()) return;
                $assignment = $association->chairmanAssignments->first();
                if (! $assignment) return;
                $key = $association->id.':'.$period->id.':'.$kind.':'.now()->toDateString();
                if (! AssociationReminderDelivery::query()->firstOrCreate(['delivery_key' => $key], ['farmer_organization_id' => $association->id, 'reporting_period_id' => $period->id, 'recipient_user_id' => $assignment->user_id, 'kind' => $kind, 'sent_at' => now()])->wasRecentlyCreated) return;
                Notification::query()->create(['user_id' => $assignment->user_id, 'title' => str_starts_with($kind, 'overdue') ? 'Overdue rice-yield report' : 'Rice-yield report reminder', 'message' => 'Your association has not submitted its required report for '.$period->label.'.', 'type' => 'Warning', 'category' => 'association_reporting']);
            });
        }
        return self::SUCCESS;
    }
}
