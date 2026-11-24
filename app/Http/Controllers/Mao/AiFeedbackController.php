<?php

namespace App\Http\Controllers\Mao;

use App\Http\Controllers\Controller;
use App\Models\AIChat;
use App\Support\MaoScope;
use App\Support\SchemaCache;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AiFeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $scopedBarangays = MaoScope::barangays($request->user());

        $query = AIChat::query()
            ->where('helpful', false)
            ->with([
                'user.farmerProfile',
                'user.farms' => fn ($q) => $q->active()->orderByDesc('is_primary')->orderBy('id'),
            ]);

        if ($scopedBarangays !== null) {
            $query->whereHas('user', function ($userQuery) use ($scopedBarangays): void {
                $userQuery->where(function ($q) use ($scopedBarangays): void {
                    if (SchemaCache::hasTable('farms')) {
                        $q->whereHas('farms', fn ($fq) => $fq->active()->whereIn('barangay', $scopedBarangays));
                    }
                    $q->orWhereHas('farmerProfile', fn ($pq) => $pq->whereIn('barangay', $scopedBarangays))
                        ->orWhereIn('barangay', $scopedBarangays);
                });
            });
        }

        $items = $query
            ->orderByDesc('feedback_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('mao.climora-feedback', [
            'items' => $items,
            'isFiltered' => MaoScope::isFiltered($request->user()),
            'scopedBarangays' => $scopedBarangays,
        ]);
    }
}
