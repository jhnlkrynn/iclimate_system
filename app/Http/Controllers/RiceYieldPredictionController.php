<?php

namespace App\Http\Controllers;

use App\Enums\PredictionType;
use App\Http\Requests\RiceYieldPredictionRequest;
use App\Models\FarmerProfile;
use App\Services\Analytics\AnalyticsDispatcher;
use App\Services\Prediction\PredictionDateValidator;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;

class RiceYieldPredictionController extends Controller
{
    public function predict(
        RiceYieldPredictionRequest $request,
        PredictionDateValidator $dateValidator,
        AnalyticsDispatcher $analytics,
    ): RedirectResponse {
        /*
        |--------------------------------------------------------------------------
        | Validate Request
        |--------------------------------------------------------------------------
        */

        $validated =
            $request->validated();

        /*
        |--------------------------------------------------------------------------
        | Target Prediction Date
        |--------------------------------------------------------------------------
        */

        $targetDate =
            CarbonImmutable::parse(
                $validated[
                    'prediction_date'
                ]
            );

        /*
        |--------------------------------------------------------------------------
        | Validate Prediction Date
        |--------------------------------------------------------------------------
        */

        if (
            $message =
                $dateValidator->validate(
                    $targetDate
                )
        ) {
            return back()
                ->withErrors([
                    'prediction_date' =>
                        $message,
                ])
                ->withInput();
        }

        /*
        |--------------------------------------------------------------------------
        | Determine Farm Type
        |--------------------------------------------------------------------------
        |
        | Priority:
        |
        | 1. Submitted farm type
        | 2. Logged-in farmer profile
        | 3. Rainfed fallback
        |
        */

        $farmType =
            $validated[
                'farm_type'
            ]
            ??
            $request
                ->user()
                ?->farmerProfile
                ?->farm_type
            ??
            FarmerProfile::FARM_TYPE_RAINFED;

        /*
        |--------------------------------------------------------------------------
        | Dispatch Existing Production Analytics Run
        |--------------------------------------------------------------------------
        |
        | IMPORTANT:
        |
        | This keeps the CURRENT production rice-yield prediction workflow
        | unchanged.
        |
        | The actual predicted_yield is produced later by the analytics
        | processing layer.
        |
        | Shadow comparison should be added where that final production result
        | becomes available, NOT here.
        |
        */

        $run =
            $analytics->dispatch(
                'rice_yield_prediction',
                [
                    'prediction_type' =>
                        PredictionType::RICE_YIELD->value,

                    'target_date' =>
                        $targetDate->toDateString(),

                    'farm_type' =>
                        $farmType,

                    'area' =>
                        (float)
                        $validated[
                            'area'
                        ],
                ],
                $request->user()
            );

        /*
        |--------------------------------------------------------------------------
        | Redirect to Prediction Result Page
        |--------------------------------------------------------------------------
        */

        return redirect()->route(
            'weather-predictions.index',
            [
                'run' =>
                    $run->uuid,
            ]
        );
    }
}