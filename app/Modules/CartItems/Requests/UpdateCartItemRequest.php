<?php

namespace App\Modules\CartItems\Requests;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class UpdateCartItemRequest extends ApiRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'cart_id' => ['sometimes', 'required', 'integer', 'exists:carts,id'],
            'product_id' => ['sometimes', 'required', 'integer', Rule::exists('products', 'id')->where('status', 'active')],
            'quantity' => ['sometimes', 'required', 'integer', 'min:1'],
            'product_name' => ['prohibited'],
            'unit_price' => ['prohibited'],
            'subtotal' => ['prohibited'],
        ];
    }
}
