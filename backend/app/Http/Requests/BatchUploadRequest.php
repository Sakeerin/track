<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BatchUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled by API key middleware
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'batch_file' => [
                'required',
                'file',
                'mimes:csv,txt',
                'max:10240', // 10MB max
            ],
            'partner_id' => 'required|string|max:50',
            'batch_id' => 'nullable|string|max:100',
            'processing_options' => 'nullable|array',
            'processing_options.skip_validation' => 'boolean',
            'processing_options.ignore_duplicates' => 'boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'batch_file.required' => 'Batch file is required',
            'batch_file.file' => 'Invalid file upload',
            'batch_file.mimes' => 'File must be CSV or TXT format',
            'batch_file.max' => 'File size cannot exceed 10MB',
            'partner_id.required' => 'Partner ID is required',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->hasFile('batch_file')) {
                $file = $this->file('batch_file');
                
                // Validate file is readable
                if (!$file->isValid()) {
                    $validator->errors()->add('batch_file', 'Uploaded file is corrupted or invalid');
                    return;
                }

                // Basic CSV structure validation
                try {
                    $handle = fopen($file->getPathname(), 'r');
                    if ($handle === false) {
                        $validator->errors()->add('batch_file', 'Cannot read uploaded file');
                        return;
                    }

                    // Check first line for expected headers
                    $firstLine = fgetcsv($handle);
                    fclose($handle);

                    if ($firstLine === false || count($firstLine) < 4) {
                        $validator->errors()->add('batch_file', 'CSV file must contain at least 4 columns (event_id, tracking_number, event_code, event_time)');
                    }

                    // Expected headers (flexible order)
                    $requiredHeaders = ['event_id', 'tracking_number', 'event_code', 'event_time'];
                    $headerMap = array_map('strtolower', array_map('trim', $firstLine));
                    
                    foreach ($requiredHeaders as $required) {
                        if (!in_array($required, $headerMap)) {
                            $validator->errors()->add('batch_file', "Missing required column: {$required}");
                        }
                    }

                } catch (\Exception $e) {
                    $validator->errors()->add('batch_file', 'Error reading CSV file: ' . $e->getMessage());
                }
            }
        });
    }
}