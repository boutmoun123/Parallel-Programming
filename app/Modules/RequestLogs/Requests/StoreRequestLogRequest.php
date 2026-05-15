<?php

namespace App\Modules\RequestLogs\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreRequestLogRequest extends FormRequest
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
            'server_node_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'operation_name' => ['required', 'string', 'max:150'],
            'endpoint' => ['required', 'string', 'max:255'],
            'method' => ['required', 'string', 'max:20'],
            'response_time_ms' => ['nullable', 'integer', 'min:0'],
            'status_code' => ['nullable', 'integer', 'min:100', 'max:599'],
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
