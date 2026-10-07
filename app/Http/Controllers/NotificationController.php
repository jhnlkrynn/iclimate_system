<?php

namespace App\Http\Controllers;

use App\Models\Notification as UserNotification;
use App\Models\User;
use App\Services\SystemAuditLogger;
use App\Support\SampleData;
use App\Support\SchemaCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class NotificationController extends CrudController
{
    protected string $model = UserNotification::class;

    protected string $routeName = 'notifications';

    protected string $title = 'Notification';

    /** Notifications to farmers are sent and edited by the MAO; IT views its own list (2026-10). */
    protected array $manageableRoles = [\App\Models\User::ROLE_MAO];

    protected array $columns = ['title' => 'Title', 'user.name' => 'Recipient', 'type' => 'Type', 'is_read' => 'Read'];

    protected array $searchable = ['title', 'message', 'type'];

    protected array $filterable = ['type' => ['Announcement', 'Advisory', 'Warning'], 'is_read' => [0 => 'Unread', 1 => 'Read']];

    protected array $indexedFilters = ['is_read'];

    protected bool $farmerPages = true;

    protected string $farmerSortColumn = 'created_at';

    protected function farmerIndexData(Request $request, Builder $filtered): array
    {
        $all = $this->baseQuery($request);

        return [
            'unreadCount' => (clone $all)->where('is_read', false)->count(),
            'totalCount' => (clone $all)->count(),
        ];
    }

    public function __construct()
    {
        $this->fields = [
            ['name' => 'recipient_scope', 'label' => 'Send To', 'type' => 'select', 'options' => ['all' => 'All Users', 'farmers' => 'Farmers', 'mao' => 'MAO Personnel', 'it_experts' => 'IT Experts', 'specific' => 'Specific User']],
            ['name' => 'user_id', 'label' => 'Specific User', 'type' => 'select', 'options' => $this->userOptions()],
            ['name' => 'title', 'label' => 'Title'],
            ['name' => 'message', 'label' => 'Message', 'type' => 'textarea'],
            ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => ['Announcement' => 'Announcement', 'Advisory' => 'Advisory', 'Warning' => 'Warning']],
            ['name' => 'category', 'label' => 'Topic (a weather warning shows as a weather alert on the farmer dashboard)', 'type' => 'select', 'options' => ['general' => 'General', 'weather' => 'Weather']],
        ];
    }

    /** null (general) unless the sender marked it as weather; null too when the column has not been migrated yet. */
    private function categoryColumns(array $data): array
    {
        if (! SchemaCache::hasColumn('notifications', 'category')) {
            return [];
        }

        return ['category' => ($data['category'] ?? null) === UserNotification::CATEGORY_WEATHER ? UserNotification::CATEGORY_WEATHER : null];
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $this->authorizeManage($request);
        $data = $this->validated($request);
        $recipients = $this->recipients($data);

        $timestamp = now();
        $category = $this->categoryColumns($data);
        $recipients->pluck('id')->chunk(500)->each(function ($userIds) use ($data, $timestamp, $category): void {
            UserNotification::query()->insert($userIds->map(fn (int $userId): array => [
                'user_id' => $userId,
                'title' => $data['title'],
                'message' => $data['message'],
                'type' => $data['type'],
                'is_read' => false,
                'created_at' => $timestamp,
                'updated_at' => $timestamp,
            ] + $category)->all());
        });
        // A bulk insert skips model events: retire what reads notifications (the staff counts) the same way a save would.
        // Each recipient's own pages pick the new notice up within their short cache time, as before.
        \App\Support\CacheScope::changed(UserNotification::class);
        SystemAuditLogger::record('Sent Notification', $request, [
            'title' => $data['title'],
            'type' => $data['type'],
            'recipient_scope' => $data['recipient_scope'],
            'recipient_count' => $recipients->count(),
        ]);

        $this->forgetUnreadCaches($request->user());
        $message = 'Notification sent to '.$recipients->count().' recipient(s).';

        // The notifications pop-up (resources/js/notifications-popup.js) saves without leaving the page.
        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message]);
        }

        return redirect()->route('notifications.index')->with('success', $message);
    }

    public function update(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $this->authorizeManage($request);
        $record = $this->findRecord($request, $id);
        $data = $this->validated($request, $id);
        $record->update([
            'user_id' => $data['user_id'] ?? $record->user_id,
            'title' => $data['title'],
            'message' => $data['message'],
            'type' => $data['type'],
            'is_read' => $request->boolean('is_read'),
        ] + $this->categoryColumns($data));
        SystemAuditLogger::forModel('updated', $record, $request, [
            'recipient_id' => $record->user_id,
            'type' => $record->type,
        ]);

        $this->forgetUnreadCaches($request->user());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => 'Notification updated successfully.']);
        }

        return redirect()->route('notifications.show', $record)->with('success', 'Notification updated successfully.');
    }

    public function destroy(Request $request, int $id): RedirectResponse|JsonResponse
    {
        $response = parent::destroy($request, $id);
        $this->forgetUnreadCaches($request->user());

        if ($request->expectsJson()) {
            // The deletion is done; the success message parent::destroy() flashed is not needed by the pop-up.
            $request->session()->forget('success');

            return response()->json(['ok' => true, 'message' => 'Notification deleted successfully.']);
        }

        return $response;
    }

    protected function baseQuery(Request $request): Builder
    {
        // Seeded placeholder notifications are left out here too, so the list agrees with the bell and the dashboard.
        return SampleData::without(UserNotification::query()->with('user')->where('user_id', $request->user()->id), 'notifications');
    }

    /** Opening a notification counts as reading it, so its unread emphasis and the badge update at once. */
    public function show(Request $request, int $id): \Illuminate\View\View
    {
        $view = parent::show($request, $id);
        $record = $view->getData()['record'] ?? null;

        if ($record instanceof UserNotification && ! $record->is_read && (int) $record->user_id === (int) $request->user()->id) {
            $record->update(['is_read' => true]);
            $this->forgetUnreadCaches($request->user());
        }

        return $view;
    }

    /**
     * The header bell's pop-up (resources/js/notifications-popup.js): the signed-in user's latest notifications and
     * the same unread count as the badge, for every role. ?filter=unread lists unread ones only.
     *
     * "View all" in that pop-up uses the same feed as the Notifications page does its list: ?filter=unread|read,
     * ?search= (title, message, type), ?type=, ?page= and ?per_page= (5-30). ?form=1 adds the send/edit form fields
     * for accounts that may manage notifications.
     */
    public function feed(Request $request): JsonResponse
    {
        $all = $this->baseQuery($request);
        $tz = \App\Support\BusinessClock::timezone();
        $canManage = $this->canManage($request);
        $search = is_string($request->query('search')) ? trim($request->query('search')) : '';
        $type = is_string($request->query('type')) ? $request->query('type') : '';
        $filter = is_string($request->query('filter')) ? $request->query('filter') : '';
        $perPage = min(30, max(5, $request->integer('per_page', 30)));
        // An advisory alert only points to the Advisories page, where the advice itself is read.
        $advisoriesLabel = trans('farmer.advisories_page.open', [], $request->user()->role === User::ROLE_FARMER ? \App\Support\FarmerLanguage::resolve($request) : 'en');

        $page = (clone $all)
            ->when($filter === 'unread', fn (Builder $query) => $query->where('is_read', false))
            ->when($filter === 'read', fn (Builder $query) => $query->where('is_read', true))
            ->when(in_array($type, $this->filterable['type'], true), fn (Builder $query) => $query->where('type', $type))
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $builder) use ($search): void {
                foreach ($this->searchable as $field) {
                    $builder->orWhere($field, 'like', "%{$search}%");
                }
            }))
            ->latest('created_at')->latest('id')
            ->paginate($perPage, ['*'], 'page', max(1, $request->integer('page', 1)));

        // Farmer – Naicel (2026-10): a missing-info notification (category "setup:*") links to the page where the
        // farmer adds it. Every other notification keeps the link it had.
        $setupLocale = $request->user()->role === User::ROLE_FARMER ? \App\Support\FarmerLanguage::resolve($request) : 'en';
        $setupLink = fn (UserNotification $note): array => ($link = \App\Services\Farmer\FarmerMissingInfoNotifier::linkFor($note->category, $setupLocale))
            ? ['link_url' => $link['url'], 'link_label' => $link['label']]
            : [];
        // end Farmer – Naicel

        return response()->json([
            'ok' => true,
            'unread' => (clone $all)->where('is_read', false)->count(),
            'total' => (clone $all)->count(),
            'filtered' => $page->total(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'from' => $page->firstItem() ?? 0,
            'to' => $page->lastItem() ?? 0,
            'types' => $this->filterable['type'],
            'can_manage' => $canManage,
            'store_url' => $canManage ? route('notifications.store') : null,
            'form' => $canManage && $request->boolean('form') ? $this->fields : null,
            'items' => collect($page->items())->map(fn (UserNotification $note): array => array_merge([
                'id' => $note->id,
                'title' => $note->title,
                'message' => $note->message,
                'type' => $note->type,
                'is_read' => (bool) $note->is_read,
                'created_at' => $note->created_at?->toIso8601String(),
                'time' => $note->created_at?->copy()->timezone($tz)->format('M j, Y · g:i A'),
                'ago' => $note->created_at?->diffForHumans(),
                'mark_read_url' => route('notifications.mark-read', $note),
                'link_url' => $note->type === 'Advisory' ? route('planting-advisories.index') : null,
                'link_label' => $note->type === 'Advisory' ? $advisoriesLabel : null,
            ], $setupLink($note)) + ($canManage ? [
                'user_id' => $note->user_id,
                'category' => $note->category ?? 'general',
                'update_url' => route('notifications.update', $note),
                'destroy_url' => route('notifications.destroy', $note),
            ] : []))->values(),
        ]);
    }

    public function markRead(Request $request, int $notification): RedirectResponse|JsonResponse
    {
        $record = $this->baseQuery($request)->findOrFail($notification);

        if (! $record->is_read) {
            $record->update(['is_read' => true]);
            SystemAuditLogger::forModel('marked read', $record, $request);
        }
        $this->forgetUnreadCaches($request->user());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'unread' => $this->unreadCount($request)]);
        }

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllRead(Request $request): RedirectResponse|JsonResponse
    {
        $updated = $this->baseQuery($request)
            ->where('is_read', false)
            ->update(['is_read' => true]);
        SystemAuditLogger::record('Marked All Notifications Read', $request, [
            'updated_count' => $updated,
        ]);
        $this->forgetUnreadCaches($request->user());

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'updated' => $updated, 'unread' => $this->unreadCount($request)]);
        }

        return back()->with('success', 'All notifications marked as read.');
    }

    private function unreadCount(Request $request): int
    {
        return $this->baseQuery($request)->where('is_read', false)->count();
    }

    /** The sidebar badge and the farmer overview both count unread notifications; drop their caches so they update at once. */
    private function forgetUnreadCaches(User $user): void
    {
        Cache::forget('sidebar:unread-notifications:'.$user->id);
        \App\Services\Analytics\FarmerDashboardService::forgetCached($user);
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        if ($id) {
            return [
                'user_id' => ['required', 'exists:users,id'],
                'title' => ['required', 'string', 'max:255'],
                'message' => ['required', 'string'],
                'type' => ['required', Rule::in(['Announcement', 'Advisory', 'Warning'])],
                'is_read' => ['nullable', 'boolean'],
                'category' => ['nullable', Rule::in(['general', 'weather'])],
            ];
        }

        return [
            'recipient_scope' => ['required', Rule::in(['all', 'farmers', 'mao', 'it_experts', 'specific'])],
            'user_id' => ['required_if:recipient_scope,specific', 'nullable', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'type' => ['required', Rule::in(['Announcement', 'Advisory', 'Warning'])],
            'category' => ['nullable', Rule::in(['general', 'weather'])],
        ];
    }

    private function recipients(array $data)
    {
        return match ($data['recipient_scope']) {
            'all' => User::query()->where('status', User::STATUS_ACTIVE)->get(['id']),
            'farmers' => User::query()->where('role', User::ROLE_FARMER)->where('status', User::STATUS_ACTIVE)->get(['id']),
            'mao' => User::query()->where('role', User::ROLE_MAO)->where('status', User::STATUS_ACTIVE)->get(['id']),
            'it_experts' => User::query()->where('role', User::ROLE_IT_EXPERT)->where('status', User::STATUS_ACTIVE)->get(['id']),
            'specific' => User::query()->whereKey($data['user_id'])->get(['id']),
        };
    }
}
