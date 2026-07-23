<?php

namespace App\Http\Controllers;

use App\Services\MachineLearning\ModelEvaluationService;
use Illuminate\View\View;

class ModelEvaluationController extends Controller
{
    public function index(ModelEvaluationService $evaluation): View
    {
        return view('model-evaluation.index', [
            'evaluation' => $evaluation->evaluate(),
        ]);
    }
}
