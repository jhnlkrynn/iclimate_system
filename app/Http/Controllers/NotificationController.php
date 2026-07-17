<?php

namespace App\Http\Controllers;

use App\Models\Notification as UserNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

class NotificationController extends CrudController
{
    protected string $model = UserNotification::class;
    protected string $routeName = 'notifications';
    protected string $title = 'Notification';
    protected array $columns = ['title' => 'Title', 'type' => 'Type', 'is_read' => 'Read'];
    protected array $searchable = ['title', 'message', 'type'];
    protected array $filterable = ['type' => ['Announcement', 'Advisory', 'Warning'], 'is_read' => [0 => 'Unread', 1 => 'Read']];

    public function __construct()
    {
        $this->fields = [
            ['name' => 'recipient_scope', 'label' => 'Send To', 'type' => 'select', 'options' => ['all' => 'All Users', 'farmers' => 'Farmers', 'mao' => 'MAO Personnel', 'it_experts' => 'IT Experts', 'specific' => 'Specific User']],
            ['name' => 'user_id', 'label' => 'Specific User', 'type' => 'select', 'options' => $this->userOptions()],
            ['name' => 'title', 'label' => 'Title'],
            ['name' => 'message', 'label' => 'Message', 'type' => 'textarea'],
            ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => ['Announcement' => 'Announcement', 'Advisory' => 'Advisory', 'Warning' => 'Warning']],
        ];
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);
        $data = $this->validated($request);
        $recipients = $this->recipients($data);

        foreach ($recipients as $user) {
            UserNotification::query()->create([
                'user_id' => $user->id,
                'title' => $data['title'],
                'message' => $data['message'],
                'type' => $data['type'],
                'is_read' => false,
            ]);
            Cache::forget('sidebar:unread-notifications:'.$user->id);
        }

        return redirect()->route('notifications.index')->with('success', 'Notification sent to '.$recipients->count().' recipient(s).');
    }

    public function update(Request $request, int $id): RedirectResponse
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
        ]);

        return redirect()->route('notifications.show', $record)->with('success', 'Notification updated successfully.');
    }

    protected function baseQuery(Request $request): Builder
    {
        return UserNotification::query()->where('user_id', $request->user()->id);
    }

    public function markRead(Request $request, int $notification): RedirectResponse
    {
        $record = UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->findOrFail($notification);

        $record->update(['is_read' => true]);
        Cache::forget('sidebar:unread-notifications:'.$request->user()->id);

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        UserNotification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);
        Cache::forget('sidebar:unread-notifications:'.$request->user()->id);

        return back()->with('success', 'All notifications marked as read.');
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
            ];
        }

        return [
            'recipient_scope' => ['required', Rule::in(['all', 'farmers', 'mao', 'it_experts', 'specific'])],
            'user_id' => ['required_if:recipient_scope,specific', 'nullable', 'exists:users,id'],
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string'],
            'type' => ['required', Rule::in(['Announcement', 'Advisory', 'Warning'])],
        ];
    }

    private function recipients(array $data)
    {
        return match ($data['recipient_scope']) {
            'all' => User::query()->where('status', User::STATUS_ACTIVE)->get(),
            'farmers' => User::query()->where('role', User::ROLE_FARMER)->where('status', User::STATUS_ACTIVE)->get(),
            'mao' => User::query()->where('role', User::ROLE_MAO)->where('status', User::STATUS_ACTIVE)->get(),
            'it_experts' => User::query()->where('role', User::ROLE_IT_EXPERT)->where('status', User::STATUS_ACTIVE)->get(),
            'specific' => User::query()->whereKey($data['user_id'])->get(),
        };
    }
}
