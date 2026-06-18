<?php

namespace App\Modules\CartItems\Requests;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class StoreCartItemRequest extends ApiRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'cart_id' => ['required', 'integer', 'exists:carts,id'],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('status', 'active')],
            'quantity' => ['required', 'integer', 'min:1'],
            'product_name' => ['prohibited'],
            'unit_price' => ['prohibited'],
            'subtotal' => ['prohibited'],
        ];
    }
}
