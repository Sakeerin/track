<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class WebhookEventRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by HMAC signature validation middleware
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Single event or array of events
            'events' => 'sometimes|array|max:100',
            'events.*.event_id' => 'required_with:events|string|max:100',
            'events.*.tracking_number' => 'required_with:events|string|max:50',
            'events.*.event_code' => 'required_with:events|string|max:50',
            'events.*.event_time' => 'required_with:events|date',
            'events.*.facility_code' => 'nullable|string|max:20',
            'events.*.location' => 'nullable|string|max:200',
            'events.*.description' => 'nullable|string|max:500',
            'events.*.remarks' => 'nullable|string|max:1000',
            
            // Single event format (when not using events array)
            'event_id' => 'required_without:events|string|max:100',
            'tracking_number' => 'required_without:events|string|max:50',
            'event_code' => 'required_without:events|string|max:50',
            'event_time' => 'required_without:events|date',
            'facility_code' => 'nullable|string|max:20',
            'location' => 'nullable|string|max:200',
            'description' => 'nullable|string|max:500',
            'remarks' => 'nullable|string|max:1000',
            
            // Optional metadata
            'partner_reference' => 'nullable|string|max:100',
            'source_system' => 'nullable|string|max:50',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'events.max' => 'Maximum 100 events allowed per request',
            'event_id.required_without' => 'Event ID is required',
            'tracking_number.required_without' => 'Tracking number is required',
            'event_code.required_without' => 'Event code is required',
            'event_time.required_without' => 'Event time is required',
            'event_time.date' => 'Event time must be a valid date',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Validate event_time is not too far in the future (max 1 hour)
            $events = $this->input('events', [$this->all()]);
            
            foreach ($events as $index => $event) {
                if (isset($event['event_time'])) {
                    $eventTime = \Carbon\Carbon::parse($event['event_time']);
                    $maxFutureTime = now()->addHour();
                    
                    if ($eventTime->gt($maxFutureTime)) {
                        $field = isset($this->input('events')) ? "events.{$index}.event_time" : 'event_time';
                        $validator->errors()->add($field, 'Event time cannot be more than 1 hour in the future');
                    }
                }
            }
        });
    }
}