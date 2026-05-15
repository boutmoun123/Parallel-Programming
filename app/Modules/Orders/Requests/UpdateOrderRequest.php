<?php

namespace App\Modules\Orders\Requests;

use App\Http\Requests\ApiRequest;
use App\Models\Order;
use Illuminate\Validation\Rule;

class UpdateOrderRequest extends ApiRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $order = $this->route('order');
        $orderId = $order instanceof Order ? $order->id : null;

        return [
            'cart_id' => ['sometimes', 'nullable', 'integer', 'exists:carts,id'],
            'order_number' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('orders', 'order_number')->ignore($orderId)],
            'status' => ['sometimes', 'required', 'string', 'max:50'],
            'payment_status' => ['sometimes', 'required', 'string', 'max:50'],
            'total_items' => ['sometimes', 'integer', 'min:0'],
            'total_amount' => ['sometimes', 'numeric', 'min:0'],
            'notes' => ['sometimes', 'nullable', 'string'],
        ];
    }
}
