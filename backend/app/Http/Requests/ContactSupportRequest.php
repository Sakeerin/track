<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ContactSupportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tracking_number' => ['nullable', 'string', 'regex:/^[A-Z]{2}[0-9]{10}$/'],
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:255'],
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['required', 'string', 'min:10', 'max:4000'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('tracking_number') && is_string($this->tracking_number)) {
            $this->merge([
                'tracking_number' => strtoupper(trim($this->tracking_number)),
            ]);
        }
    }
}
