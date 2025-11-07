<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SingleTrackingRequest extends FormRequest
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
            'tracking_number' => ['required', 'string', 'regex:/^[A-Z]{2}[0-9]{10}$/'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'tracking_number.required' => 'Tracking number is required.',
            'tracking_number.string' => 'Tracking number must be a string.',
            'tracking_number.regex' => 'Invalid tracking number format. Expected format: 2 letters followed by 10 digits (e.g., TH1234567890).',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Get tracking number from route parameter if not in request body
        if (!$this->has('tracking_number') && $this->route('trackingNumber')) {
            $this->merge([
                'tracking_number' => trim($this->route('trackingNumber')),
            ]);
        }
    }
}