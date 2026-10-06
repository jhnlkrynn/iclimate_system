<?php

namespace App\Http\Controllers;

use App\Models\SystemLog;
use App\Models\User;
use App\Support\SystemLogPresenter;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class SystemLogController extends CrudController
{
    protected string $model = SystemLog::class;

    protected string $routeName = 'system-logs';

    protected string $title = 'System Log';

    protected array $columns = ['action' => 'Action', 'user.name' => 'Actor', 'details' => 'Details', 'created_at' => 'Date'];

    protected array $searchable = ['action', 'details'];

    protected bool $viewOnly = true;

    private const OPTIONS_CACHE_KEY = 'system-logs:filter-options';

    public function index(Request $request): View
    {
        $this->authorizeView($request);

        $search = trim((string) $request->query('search', ''));
        $actionLabel = (string) $request->query('action', '');
        $userId = (string) $request->query('user', '');
        $role = (string) $request->query('role', '');
        $date = (string) $request->query('date', '');

        $options = $this->filterOptions();
        $query = $this->baseQuery($request);

        if ($search !== '') {
            $like = '%'.addcslashes($search, '\%_').'%';
            // Also match what the page displays ("Logged In"), not only the stored action name.
            $matchingActions = collect($options['actions'])
                ->filter(fn ($raw, $label) => stripos($label, $search) !== false)->flatten()->all();

            $query->where(function (Builder $q) use ($like, $matchingActions) {
                $q->where('action', 'like', $like)
                    ->orWhere('details', 'like', $like)
                    ->orWhereHas('user', fn (Builder $u) => $u->where('name', 'like', $like));
                if ($matchingActions !== []) {
                    $q->orWhereIn('action', $matchingActions);
                }
            });
        }
        if ($actionLabel !== '') {
            // One label can cover several stored values (e.g. "User Login" and "login").
            $query->whereIn('action', $options['actions'][$actionLabel] ?? [$actionLabel]);
        }
        if ($userId !== '' && ctype_digit($userId)) {
            $query->where('user_id', (int) $userId);
        }
        if (in_array($role, [User::ROLE_FARMER, User::ROLE_MAO, User::ROLE_IT_EXPERT], true)) {
            // Prefer the role recorded in the log; fall back to the user's current role when none was stored.
            $query->where(function (Builder $q) use ($role) {
                $q->where('details', 'like', '%"role":"'.$role.'"%')
                    ->orWhere(function (Builder $q2) use ($role) {
                        $q2->where(fn (Builder $n) => $n->whereNull('details')->orWhere('details', 'not like', '%"role":"%'))
                            ->whereHas('user', fn (Builder $u) => $u->where('role', $role));
                    });
            });
        }
        if ($date !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && strtotime($date) !== false) {
            $start = Carbon::parse($date, config('app.timezone'))->startOfDay();
            $query->where('created_at', '>=', $start)->where('created_at', '<', $start->copy()->addDay());
        }

        $logs = $query->latest('id')->paginate(CrudController::PER_PAGE)->withQueryString();
        $rows = $logs->getCollection()->map(fn (SystemLog $log) => SystemLogPresenter::present($log));

        return view('system-logs.index', [
            'logs' => $logs,
            'rows' => $rows,
            'summary' => $this->summary(),
            'actions' => array_keys($options['actions']),
            'users' => $options['users'],
            'roles' => [User::ROLE_FARMER, User::ROLE_MAO, User::ROLE_IT_EXPERT],
            'filters' => ['search' => $search, 'action' => $actionLabel, 'role' => $role, 'date' => $date, 'user' => $userId],
        ]);
    }

    /**
     * Dropdown contents only (not counts), so a 30 s cache cannot make numbers disagree with the table.
     * If a requested action label is not cached yet, the list is rebuilt once.
     *
     * @return array{actions: array<string, array<int,string>>, users: array<int,string>}
     */
    private function filterOptions(): array
    {
        $build = function (): array {
            $actions = [];
            foreach (SystemLog::query()->select('action')->distinct()->pluck('action') as $raw) {
                $actions[SystemLogPresenter::actionLabel($raw)][] = $raw;
            }
            ksort($actions);

            return [
                'actions' => $actions,
                'users' => User::query()->whereIn('id', SystemLog::query()->whereNotNull('user_id')->select('user_id')->distinct())
                    ->orderBy('name')->pluck('name', 'id')->all(),
            ];
        };

        $options = Cache::remember(self::OPTIONS_CACHE_KEY, now()->addSeconds(30), $build);
        $requested = (string) request()->query('action', '');
        if ($requested !== '' && ! isset($options['actions'][$requested])) {
            Cache::forget(self::OPTIONS_CACHE_KEY);
            $options = Cache::remember(self::OPTIONS_CACHE_KEY, now()->addSeconds(30), $build);
        }

        return $options;
    }

    /**
     * Live numbers (no cache): one aggregate query + one users query.
     *
     * @return array{total:int, today:int, logins_today:int, recent_users:int, recent_names:array<int,string>}
     */
    private function summary(): array
    {
        $startOfDay = \App\Support\BusinessClock::todayUtc();
        $loginList = "'".implode("','", SystemLogPresenter::LOGIN_ACTIONS)."'";

        $counts = SystemLog::query()->selectRaw(
            "count(*) as total,
             coalesce(sum(case when created_at >= ? then 1 else 0 end), 0) as today,
             coalesce(sum(case when created_at >= ? and lower(action) in ({$loginList}) then 1 else 0 end), 0) as logins_today",
            [$startOfDay, $startOfDay]
        )->first();

        $names = User::query()
            ->whereIn('id', SystemLog::query()->whereNotNull('user_id')->where('created_at', '>=', now()->subDays(7))->select('user_id'))
            ->orderBy('name')->pluck('name');

        return [
            'total' => (int) $counts->total,
            'today' => (int) $counts->today,
            'logins_today' => (int) $counts->logins_today,
            'recent_users' => $names->count(),
            'recent_names' => $names->take(3)->all(),
        ];
    }

    protected function baseQuery(Request $request): Builder
    {
        return SystemLog::query()->with('user');
    }

    protected function authorizeView(Request $request): void
    {
        if ($request->user()->role !== User::ROLE_IT_EXPERT) {
            abort(403);
        }
    }
}
