<?php

namespace App\Http\Controllers;

use App\Services\SystemHealthService;
use Illuminate\View\View;

class SystemHealthController extends Controller
{
    public function index(SystemHealthService $health): View
    {
        $checks = $health->checks();

        return view('system-health.index', [
            'checks' => $checks,
            'summary' => $health->summarize($checks),
            'checkedAt' => now()->timezone('Asia/Manila'),
        ]);
    }
}
