<?php

namespace App\Modules\OrderItems\Requests;

use App\Http\Requests\ApiRequest;

class UpdateOrderItemRequest extends ApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['sometimes', 'required', 'integer', 'exists:orders,id'],
            'product_id' => ['sometimes', 'required', 'integer', 'exists:products,id'],
            'product_name' => ['sometimes', 'required', 'string', 'max:255'],
            'quantity' => ['sometimes', 'required', 'integer', 'min:1'],
            'unit_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'subtotal' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
