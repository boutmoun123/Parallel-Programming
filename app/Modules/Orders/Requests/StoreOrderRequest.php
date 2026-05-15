<?php

namespace App\Modules\Orders\Requests;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends ApiRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'cart_id' => ['nullable', 'integer', 'exists:carts,id'],
            'order_number' => ['sometimes', 'string', 'max:255', Rule::unique('orders', 'order_number')],
            'status' => ['required', 'string', 'max:50'],
            'payment_status' => ['required', 'string', 'max:50'],
            'total_items' => ['sometimes', 'integer', 'min:0'],
            'total_amount' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }
}
