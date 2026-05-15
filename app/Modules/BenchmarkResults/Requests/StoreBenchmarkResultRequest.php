<?php

namespace App\Modules\BenchmarkResults\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreBenchmarkResultRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'operation_name' => ['required', 'string', 'max:150'],
            'scenario' => ['nullable', 'string', 'max:150'],
            'concurrent_users' => ['required', 'integer', 'min:1'],
            'total_requests' => ['required', 'integer', 'min:0'],
            'successful_requests' => ['required', 'integer', 'min:0'],
            'failed_requests' => ['required', 'integer', 'min:0'],
            'average_response_time_ms' => ['nullable', 'integer', 'min:0'],
            'max_response_time_ms' => ['nullable', 'integer', 'min:0'],
            'throughput_per_second' => ['nullable', 'numeric', 'min:0'],
            'bottleneck_note' => ['nullable', 'string'],
            'optimization_applied' => ['nullable', 'string', 'max:255'],
            'tested_at' => ['nullable', 'date'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation error',
            'data' => null,
            'errors' => $validator->errors(),
        ], 422));
    }
}
