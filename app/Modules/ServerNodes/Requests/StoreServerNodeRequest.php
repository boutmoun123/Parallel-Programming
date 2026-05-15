<?php

namespace App\Modules\ServerNodes\Requests;

use App\Models\ServerNode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreServerNodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'host' => ['required', 'string', 'max:255'],
            'status' => ['nullable', Rule::in([
                ServerNode::STATUS_ACTIVE,
                ServerNode::STATUS_INACTIVE,
                ServerNode::STATUS_OVERLOADED,
            ])],
            'max_concurrent_requests' => ['required', 'integer', 'min:1'],
            'current_load' => ['nullable', 'integer', 'min:0'],
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
