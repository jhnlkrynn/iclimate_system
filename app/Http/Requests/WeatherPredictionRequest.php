<?php

namespace App\Http\Requests;

use App\Enums\PredictionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WeatherPredictionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'prediction_date' => ['required', 'date'],
            'prediction_type' => ['required', Rule::in([PredictionType::WEATHER->value])],
        ];
    }
}
