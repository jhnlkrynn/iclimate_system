<?php

namespace App\Http\Controllers;

use App\Models\FieldReport;
use App\Models\User;
use App\Support\LianBarangays;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class FieldReportController extends CrudController
{
    protected string $model = FieldReport::class;

    protected string $routeName = 'field-reports';

    protected string $title = 'Field Report';

    protected array $columns = [
        'title' => 'Title',
        'barangay' => 'Barangay',
        'issue_type' => 'Issue',
        'status' => 'Status',
        'assignedTo.name' => 'Assigned To',
    ];

    protected array $searchable = ['title', 'barangay', 'description'];

    public function __construct()
    {
        $this->filterable = [
            'barangay' => LianBarangays::all(),
            'issue_type' => FieldReport::ISSUE_TYPES,
            'status' => FieldReport::STATUSES,
        ];

        $this->fields = [
            ['name' => 'title', 'label' => 'Title'],
            ['name' => 'barangay', 'label' => 'Barangay', 'type' => 'select', 'options' => LianBarangays::options(false)],
            ['name' => 'issue_type', 'label' => 'Issue Type', 'type' => 'select', 'options' => array_combine(FieldReport::ISSUE_TYPES, FieldReport::ISSUE_TYPES)],
            ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
            ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => array_combine(FieldReport::STATUSES, FieldReport::STATUSES)],
            ['name' => 'assigned_to', 'label' => 'Assigned Personnel', 'type' => 'select', 'options' => ['' => 'Unassigned'] + $this->userOptions([User::ROLE_MAO, User::ROLE_IT_EXPERT])],
        ];
    }

    protected function rules(Request $request, ?int $id = null): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'barangay' => ['required', Rule::in(LianBarangays::all())],
            'issue_type' => ['required', Rule::in(FieldReport::ISSUE_TYPES)],
            'description' => ['nullable', 'string'],
            'status' => ['required', Rule::in(FieldReport::STATUSES)],
            'assigned_to' => ['nullable', 'exists:users,id'],
        ];
    }

    protected function prepareData(Request $request, array $data): array
    {
        $data = parent::prepareData($request, $data);

        if (! array_key_exists('reported_by', $data)) {
            $data['reported_by'] = $request->user()->id;
        }

        return $data;
    }
}
