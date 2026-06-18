<?php

namespace App\Modules\OrderItems\Requests;

use App\Http\Requests\ApiRequest;
use Illuminate\Validation\Rule;

class UpdateOrderItemRequest extends ApiRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'order_id' => ['sometimes', 'required', 'integer', 'exists:orders,id'],
            'product_id' => ['sometimes', 'required', 'integer', Rule::exists('products', 'id')->where('status', 'active')],
            'quantity' => ['sometimes', 'required', 'integer', 'min:1'],
            'product_name' => ['prohibited'],
            'unit_price' => ['prohibited'],
            'subtotal' => ['prohibited'],
        ];
    }
}
