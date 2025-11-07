<?php

namespace App\Services\Ingestion;

use App\Jobs\ProcessBatchJob;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class BatchProcessingService
{
    private EventIngestionService $ingestionService;

    public function __construct(EventIngestionService $ingestionService)
    {
        $this->ingestionService = $ingestionService;
    }

    /**
     * Process CSV file upload
     */
    public function processCsvFile(UploadedFile $file, string $partnerId): array
    {
        $batchId = $this->generateBatchId($partnerId);
        
        try {
            // Store file temporarily
            $filePath = $file->storeAs(
                'batch_uploads',
                $batchId . '_' . $file->getClientOriginalName(),
                'local'
            );

            Log::info('Batch file uploaded', [
                'batch_id' => $batchId,
                'partner_id' => $partnerId,
                'filename' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
                'path' => $filePath
            ]);

            // Process file synchronously for immediate feedback
            $result = $this->processCsvData($filePath, $partnerId, $batchId);

            // Clean up temporary file
            Storage::disk('local')->delete($filePath);

            return $result;

        } catch (\Exception $e) {
            Log::error('Batch processing failed', [
                'batch_id' => $batchId,
                'partner_id' => $partnerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            throw $e;
        }
    }

    /**
     * Process CSV data from file
     */
    private function processCsvData(string $filePath, string $partnerId, string $batchId): array
    {
        $fullPath = Storage::disk('local')->path($filePath);
        $handle = fopen($fullPath, 'r');
        
        if ($handle === false) {
            throw new \Exception('Cannot read uploaded file');
        }

        $processedCount = 0;
        $failedCount = 0;
        $errors = [];
        $lineNumber = 0;

        try {
            // Read header row
            $headers = fgetcsv($handle);
            if ($headers === false) {
                throw new \Exception('Cannot read CSV headers');
            }

            $headers = array_map('trim', array_map('strtolower', $headers));
            $headerMap = array_flip($headers);

            // Validate required columns
            $requiredColumns = ['event_id', 'tracking_number', 'event_code', 'event_time'];
            foreach ($requiredColumns as $column) {
                if (!isset($headerMap[$column])) {
                    throw new \Exception("Missing required column: {$column}");
                }
            }

            // Process data rows
            while (($row = fgetcsv($handle)) !== false) {
                $lineNumber++;
                
                try {
                    $eventData = $this->mapCsvRowToEvent($row, $headerMap, $partnerId);
                    $result = $this->ingestionService->queueEvent($eventData, 'batch');
                    
                    if ($result['status'] === 'queued' || $result['status'] === 'duplicate') {
                        $processedCount++;
                    } else {
                        $failedCount++;
                        $errors[] = [
                            'line' => $lineNumber + 1, // +1 for header
                            'tracking_number' => $eventData['tracking_number'] ?? 'unknown',
                            'error' => $result['message'] ?? 'Unknown error'
                        ];
                    }

                } catch (\Exception $e) {
                    $failedCount++;
                    $errors[] = [
                        'line' => $lineNumber + 1,
                        'error' => $e->getMessage()
                    ];

                    Log::warning('Failed to process CSV row', [
                        'batch_id' => $batchId,
                        'line' => $lineNumber + 1,
                        'error' => $e->getMessage(),
                        'row_data' => $row
                    ]);
                }

                // Prevent memory issues with large files
                if ($lineNumber % 1000 === 0) {
                    Log::info('Batch processing progress', [
                        'batch_id' => $batchId,
                        'processed_lines' => $lineNumber,
                        'processed_count' => $processedCount,
                        'failed_count' => $failedCount
                    ]);
                }
            }

        } finally {
            fclose($handle);
        }

        Log::info('Batch processing completed', [
            'batch_id' => $batchId,
            'partner_id' => $partnerId,
            'total_lines' => $lineNumber,
            'processed_count' => $processedCount,
            'failed_count' => $failedCount,
            'error_count' => count($errors)
        ]);

        return [
            'batch_id' => $batchId,
            'processed_count' => $processedCount,
            'failed_count' => $failedCount,
            'errors' => array_slice($errors, 0, 100) // Limit errors returned
        ];
    }

    /**
     * Map CSV row to event data structure
     */
    private function mapCsvRowToEvent(array $row, array $headerMap, string $partnerId): array
    {
        $eventData = [];

        // Required fields
        $eventData['event_id'] = trim($row[$headerMap['event_id']] ?? '');
        $eventData['tracking_number'] = trim($row[$headerMap['tracking_number']] ?? '');
        $eventData['event_code'] = trim($row[$headerMap['event_code']] ?? '');
        $eventData['event_time'] = trim($row[$headerMap['event_time']] ?? '');

        // Optional fields
        $optionalFields = [
            'facility_code', 'location', 'description', 'remarks',
            'partner_reference', 'source_system'
        ];

        foreach ($optionalFields as $field) {
            if (isset($headerMap[$field]) && isset($row[$headerMap[$field]])) {
                $value = trim($row[$headerMap[$field]]);
                if ($value !== '') {
                    $eventData[$field] = $value;
                }
            }
        }

        // Add partner context
        $eventData['partner_id'] = $partnerId;

        // Validate required fields are not empty
        $requiredFields = ['event_id', 'tracking_number', 'event_code', 'event_time'];
        foreach ($requiredFields as $field) {
            if (empty($eventData[$field])) {
                throw new \Exception("Missing required field: {$field}");
            }
        }

        // Parse and validate event_time
        try {
            $eventData['event_time'] = Carbon::parse($eventData['event_time']);
        } catch (\Exception $e) {
            throw new \Exception("Invalid event_time format: {$eventData['event_time']}");
        }

        return $eventData;
    }

    /**
     * Generate unique batch ID
     */
    private function generateBatchId(string $partnerId): string
    {
        return $partnerId . '_' . date('Ymd_His') . '_' . substr(uniqid(), -6);
    }

    /**
     * Process SFTP batch files (for scheduled processing)
     */
    public function processSftpBatch(string $sftpPath, string $partnerId): array
    {
        // This would be implemented for SFTP file processing
        // For now, return placeholder
        return [
            'processed_count' => 0,
            'failed_count' => 0,
            'errors' => []
        ];
    }
}