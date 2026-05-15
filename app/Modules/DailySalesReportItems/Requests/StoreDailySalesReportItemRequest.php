<?php

namespace App\Modules\DailySalesReportItems\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreDailySalesReportItemRequest extends FormRequest
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
            'daily_sales_report_id' => ['nullable', 'integer'],
            'product_id' => ['nullable', 'integer'],
            'total_quantity_sold' => ['nullable', 'integer', 'min:0'],
            'total_revenue' => ['nullable', 'numeric', 'min:0'],
            'inventory_movements' => ['nullable', 'integer'],
            'product_rank' => ['nullable', 'integer', 'min:1'],
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
