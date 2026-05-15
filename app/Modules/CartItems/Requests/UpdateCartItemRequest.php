<?php

namespace App\Modules\CartItems\Requests;

use App\Http\Requests\ApiRequest;

class UpdateCartItemRequest extends ApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'cart_id' => ['sometimes', 'required', 'integer', 'exists:carts,id'],
            'product_id' => ['sometimes', 'required', 'integer', 'exists:products,id'],
            'product_name' => ['sometimes', 'required', 'string', 'max:255'],
            'quantity' => ['sometimes', 'required', 'integer', 'min:1'],
            'unit_price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'subtotal' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
