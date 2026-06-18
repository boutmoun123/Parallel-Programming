<?php

namespace App\Modules\OrderItems\Requests;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class StoreOrderItemRequest extends ApiRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('status', 'active')],
            'quantity' => ['required', 'integer', 'min:1'],
            'product_name' => ['prohibited'],
            'unit_price' => ['prohibited'],
            'subtotal' => ['prohibited'],
        ];
    }
}
