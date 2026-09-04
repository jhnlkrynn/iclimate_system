<?php

namespace App\Http\Requests;

use App\Enums\PredictionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RiceYieldPredictionRequest extends FormRequest
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
            'farm_type' => ['nullable', 'in:Rainfed,Irrigated'],
            'area' => ['required', 'numeric', 'gt:0', 'max:10000'],
            'prediction_type' => ['required', Rule::in([PredictionType::RICE_YIELD->value])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'area.required' => 'Please enter a valid farm area greater than 0 hectares.',
            'area.numeric' => 'Please enter a valid farm area greater than 0 hectares.',
            'area.gt' => 'Please enter a valid farm area greater than 0 hectares.',
            'area.max' => 'Please enter a farm area within the supported range.',
        ];
    }
}
