<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Notification as UserNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnnouncementController extends CrudController
{
    protected string $model = Announcement::class;
    protected string $routeName = 'announcements';
    protected string $title = 'Announcement';
    protected array $columns = ['title' => 'Title', 'category' => 'Category', 'status' => 'Status'];
    protected array $searchable = ['title', 'content', 'category', 'status'];
    protected array $filterable = ['category' => ['News', 'Event', 'Training', 'Seed Distribution', 'Fertilizer Distribution'], 'status' => ['Draft', 'Published']];

    public function __construct()
    {
        $this->fields = [
            ['name' => 'title', 'label' => 'Title'],
            ['name' => 'content', 'label' => 'Content', 'type' => 'textarea'],
            ['name' => 'category', 'label' => 'Category', 'type' => 'select', 'options' => ['News' => 'News', 'Event' => 'Event', 'Training' => 'Training', 'Seed Distribution' => 'Seed Distribution', 'Fertilizer Distribution' => 'Fertilizer Distribution']],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['Draft' => 'Draft', 'Published' => 'Published']],
        ];
    }

    protected function prepareData(Request $request, array $data): array
    {
        $data['posted_by'] = $request->user()->id;
        return $data;
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeManage($request);

        $announcement = Announcement::query()->create($this->prepareData($request, $this->validated($request)));

        if ($announcement->status === 'Published') {
            $this->broadcastAnnouncement($announcement);
        }

        return redirect()->route('announcements.show', $announcement)->with('success', 'Announcement created successfully.');
    }

    public function update(Request $request, int $id): RedirectResponse
    {
        $this->authorizeManage($request);

        $announcement = $this->findRecord($request, $id);
        $wasPublished = $announcement->status === 'Published';
        $announcement->update($this->prepareData($request, $this->validated($request, $announcement->id)));

        if (! $wasPublished && $announcement->status === 'Published') {
            $this->broadcastAnnouncement($announcement);
        }

        return redirect()->route('announcements.show', $announcement)->with('success', 'Announcement updated successfully.');
    }

    protected function baseQuery(Request $request): Builder
    {
        $query = parent::baseQuery($request);

        if ($request->user()->role === User::ROLE_FARMER) {
            $query->where('status', 'Published');
        }

        return $query;
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'category' => ['required', Rule::in(['News', 'Event', 'Training', 'Seed Distribution', 'Fertilizer Distribution'])],
            'status' => ['required', Rule::in(['Draft', 'Published'])],
        ];
    }

    private function broadcastAnnouncement(Announcement $announcement): void
    {
        User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->each(function (User $user) use ($announcement) {
                UserNotification::query()->create([
                    'user_id' => $user->id,
                    'title' => 'Announcement: '.$announcement->title,
                    'message' => $announcement->content,
                    'type' => 'Announcement',
                    'is_read' => false,
                ]);
            });
    }
}
