<?php

namespace App\Modules\Invoices\Requests;

use App\Models\Invoice;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
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
            'order_id' => ['nullable', 'integer'],
            'invoice_number' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in([
                Invoice::STATUS_DRAFT,
                Invoice::STATUS_ISSUED,
                Invoice::STATUS_CANCELLED,
            ])],
            'issued_at' => ['nullable', 'date'],
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
