<?php

namespace App\Http\Controllers;

use App\Models\MaoActivityLog;
use App\Models\User;
use App\Support\LianBarangays;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Panel revision 4.4 D: the MAO Activity Log. The Municipal Agriculturist sees every MAO user's actions; other MAO
 * users see only their own. Filters: MAO user (head only), action, barangay and date range.
 */
class MaoActivityLogController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $request->validate([
            'user' => ['nullable', 'integer'],
            'action' => ['nullable', Rule::in(MaoActivityLog::ACTIONS)],
            'barangay' => ['nullable', Rule::in(LianBarangays::all())],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ]);

        $query = MaoActivityLog::query()
            ->when(! $user->isMaoHead(), fn ($q) => $q->where('user_id', $user->id))
            ->when($user->isMaoHead() && ! empty($filters['user']), fn ($q) => $q->where('user_id', $filters['user']))
            ->when(! empty($filters['action']), fn ($q) => $q->where('action', $filters['action']))
            ->when(! empty($filters['barangay']), fn ($q) => $q->where('barangay', $filters['barangay']))
            ->when(! empty($filters['from']), fn ($q) => $q->where('created_at', '>=', $filters['from'].' 00:00:00'))
            ->when(! empty($filters['to']), fn ($q) => $q->where('created_at', '<=', $filters['to'].' 23:59:59'));

        return view('mao.activity-log', [
            'entries' => $query->latest('created_at')->latest('id')->paginate(30)->withQueryString(),
            'filters' => $filters,
            'isHead' => $user->isMaoHead(),
            'maoUsers' => $user->isMaoHead()
                ? User::query()->where('role', User::ROLE_MAO)->orderBy('name')->get(['id', 'name', 'position'])
                : collect(),
            'barangays' => LianBarangays::all(),
            'actions' => MaoActivityLog::ACTIONS,
        ]);
    }
}
