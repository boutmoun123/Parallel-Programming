<?php

namespace App\Modules\DailySalesReports\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreDailySalesReportRequest extends FormRequest
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
            'report_date' => ['required', 'date'],
            'total_orders' => ['nullable', 'integer', 'min:0'],
            'total_sales' => ['nullable', 'numeric', 'min:0'],
            'total_items_sold' => ['nullable', 'integer', 'min:0'],
            'inventory_movements' => ['nullable', 'integer'],
            'generated_at' => ['nullable', 'date'],
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
