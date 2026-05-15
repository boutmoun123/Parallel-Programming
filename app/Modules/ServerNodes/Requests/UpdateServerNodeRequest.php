<?php

namespace App\Modules\ServerNodes\Requests;

use App\Models\ServerNode;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class UpdateServerNodeRequest extends FormRequest
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
            'name' => ['sometimes', 'string', 'max:100'],
            'host' => ['sometimes', 'string', 'max:255'],
            'status' => ['sometimes', Rule::in([
                ServerNode::STATUS_ACTIVE,
                ServerNode::STATUS_INACTIVE,
                ServerNode::STATUS_OVERLOADED,
            ])],
            'max_concurrent_requests' => ['sometimes', 'integer', 'min:1'],
            'current_load' => ['sometimes', 'integer', 'min:0'],
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
