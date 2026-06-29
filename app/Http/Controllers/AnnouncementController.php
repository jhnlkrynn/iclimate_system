<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
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

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
            'category' => ['required', Rule::in(['News', 'Event', 'Training', 'Seed Distribution', 'Fertilizer Distribution'])],
            'status' => ['required', Rule::in(['Draft', 'Published'])],
        ];
    }
}