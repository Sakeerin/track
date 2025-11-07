<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TrackingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true; // Public endpoint
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'tracking_numbers' => ['required', 'array', 'min:1', 'max:' . config('tracking.max_tracking_numbers', 20)],
            'tracking_numbers.*' => ['required', 'string', 'regex:/^[A-Z]{2}[0-9]{10}$/'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'tracking_numbers.required' => 'At least one tracking number is required.',
            'tracking_numbers.array' => 'Tracking numbers must be provided as an array.',
            'tracking_numbers.min' => 'At least one tracking number is required.',
            'tracking_numbers.max' => 'Maximum :max tracking numbers allowed per request.',
            'tracking_numbers.*.required' => 'Each tracking number is required.',
            'tracking_numbers.*.string' => 'Each tracking number must be a string.',
            'tracking_numbers.*.regex' => 'Invalid tracking number format. Expected format: 2 letters followed by 10 digits (e.g., TH1234567890).',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Remove duplicates and trim whitespace
        if ($this->has('tracking_numbers') && is_array($this->tracking_numbers)) {
            $this->merge([
                'tracking_numbers' => array_values(array_unique(array_map('trim', $this->tracking_numbers))),
            ]);
        }
    }
}
