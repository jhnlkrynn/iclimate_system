# Handoff from Kaye (MAO and IT sides)

For Naicel and John Luke. Branch: `ui-other-file`.

## MAO Planner

- **Model:** `App\Models\MaoActivity`
- **Table:** `mao_activities` (migration `2026_10_07_100001_create_mao_activities_table.php`; run `php artisan migrate`)
- **Controller:** `App\Http\Controllers\MaoPlannerController`
- **Page:** `/mao/planner`, route names `mao.planner.index`, `.store`, `.update`, `.destroy` (MAO Personnel only)
- **View:** `resources/views/mao/planner.blade.php`

Columns of `mao_activities`:

| Column | Meaning |
| --- | --- |
| `user_id` | The MAO account that added it. Only that account can edit or delete it. |
| `activity_type` | One of `MaoActivity::TYPES`: Planting Event, Harvesting, Training, Seed Distribution, Field Visit, Meeting, Other. |
| `barangay` | A Lian barangay name, or `NULL` for **All Barangays**. |
| `starts_on` | Date of the activity. |
| `ends_on` | Optional last day, only for activities longer than one day. |
| `title`, `notes` | Text the MAO typed. Notes are optional. |

`$activity->summary()` gives the one-line label, for example `Brgy. Balibago – Planting Event – Oct 15`.

To show a farmer the MAO activities for their barangay (read-only):

```php
MaoActivity::query()
    ->where(fn ($q) => $q->where('barangay', $barangay)->orWhereNull('barangay'))
    ->orderBy('starts_on')->get();
```

The "Farmer Activities" list on the planner reads `FarmerPlantingSchedule` (planting date, harvest date, farm). It saves nothing.

## Other things that changed

- **Calendar:** the MAO menu item "Calendar" is now "MAO Planner" and opens `/mao/planner`. `/calendar` itself was not changed.
- **Farmer Profiles (MAO):** the table shows Name, Barangay, Farm Type, Total Farm Area, Status. View opens a pop-up (`resources/views/farmer-profiles/_modal.blade.php`, loaded with `?modal=1`). Empty fields are left out. The Mensahe button opens the conversation inside the pop-up using `/messages/{id}?embed=1`.
- **Messages:** `?embed=1` hides the sidebar, top bar and conversation list so the page fits in a pop-up. Nothing else changed.
- **Reports:** the Barangay column is gone from the screen and Print. The barangay filter and the CSV column stay.
- **IT accounts:** the menu is Dashboard, User Management, System Health, System Logs, Messages. The agricultural pages send IT back to the IT dashboard (`App\Http\Middleware\RedirectItFromFieldPages`). IT can still open the Modify Farmer Profile form and the Data Integrity farmer list. No controller or route was deleted.
- **"Account" is now "Profile"** in the menu and on the page. Route names (`profile.edit` and so on) are unchanged.
- **MAO Dashboard:** four charts from saved records (`App\Services\Analytics\MaoHomeService`).
