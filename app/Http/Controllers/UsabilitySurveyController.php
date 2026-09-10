<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UsabilitySurveyResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class UsabilitySurveyController extends Controller
{
    public function create(Request $request): View
    {
        $existing = UsabilitySurveyResponse::query()
            ->where('user_id', $request->user()->id)
            ->first();

        return view('usability-survey.create', [
            'items' => UsabilitySurveyResponse::SUS_ITEMS,
            'existing' => $existing,
            'canViewResults' => $this->canViewResults($request->user()),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rules = ['comments' => ['nullable', 'string', 'max:2000']];

        foreach (UsabilitySurveyResponse::SUS_ITEMS as $number => $item) {
            $rules["item_{$number}"] = ['required', 'integer', 'between:1,5'];
        }

        $data = $request->validate($rules);

        UsabilitySurveyResponse::query()->updateOrCreate(
            ['user_id' => $request->user()->id],
            ['role' => $request->user()->role, ...$data],
        );

        return redirect()->route('usability-survey.create')
            ->with('success', 'Thank you — your system evaluation has been saved.');
    }

    public function results(Request $request): View
    {
        $responses = UsabilitySurveyResponse::query()->with('user')->latest()->get();
        $scores = $responses->map(fn (UsabilitySurveyResponse $response): float => $response->score());

        $itemMeans = collect(UsabilitySurveyResponse::SUS_ITEMS)->mapWithKeys(function (array $item, int $number) use ($responses): array {
            $mean = $responses->avg("item_{$number}");

            return [$number => [
                'text' => $item['text'],
                'positive' => $item['positive'],
                'mean' => $mean !== null ? round($mean, 2) : null,
            ]];
        });

        $roleBreakdown = $responses->groupBy('role')->map(fn (Collection $group): array => [
            'count' => $group->count(),
            'mean_score' => round($group->map(fn (UsabilitySurveyResponse $response): float => $response->score())->avg(), 1),
        ]);

        return view('usability-survey.results', [
            'respondentCount' => $responses->count(),
            'meanScore' => $scores->isNotEmpty() ? round($scores->avg(), 1) : null,
            'medianScore' => $scores->isNotEmpty() ? $this->median($scores) : null,
            'minScore' => $scores->isNotEmpty() ? $scores->min() : null,
            'maxScore' => $scores->isNotEmpty() ? $scores->max() : null,
            'itemMeans' => $itemMeans,
            'roleBreakdown' => $roleBreakdown,
            'responses' => $responses,
        ]);
    }

    public function exportCsv(): Response
    {
        $responses = UsabilitySurveyResponse::query()->with('user')->latest()->get();

        $header = ['respondent_id', 'role', 'submitted_at'];
        foreach (array_keys(UsabilitySurveyResponse::SUS_ITEMS) as $number) {
            $header[] = "item_{$number}";
        }
        $header[] = 'sus_score';
        $header[] = 'comments';

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $header);

        foreach ($responses as $response) {
            $row = [$response->user_id, $response->role, $response->created_at?->toDateTimeString()];

            foreach (array_keys(UsabilitySurveyResponse::SUS_ITEMS) as $number) {
                $row[] = $response->{"item_{$number}"};
            }

            $row[] = $response->score();
            $row[] = $response->comments;
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="usability-survey-responses-'.now()->format('Ymd-His').'.csv"',
        ]);
    }

    private function canViewResults(User $user): bool
    {
        return in_array($user->role, [User::ROLE_MAO, User::ROLE_IT_EXPERT], true);
    }

    private function median(Collection $values): float
    {
        $sorted = $values->sort()->values();
        $count = $sorted->count();
        $middle = intdiv($count, 2);

        if ($count % 2 === 0) {
            return round(($sorted[$middle - 1] + $sorted[$middle]) / 2, 1);
        }

        return round($sorted[$middle], 1);
    }
}
