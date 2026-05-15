<?php

namespace App\Modules\OrderItems\Requests;

use App\Http\Requests\ApiRequest;

class StoreOrderItemRequest extends ApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'product_name' => ['required', 'string', 'max:255'],
            'quantity' => ['required', 'integer', 'min:1'],
            'unit_price' => ['required', 'numeric', 'min:0'],
            'subtotal' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
