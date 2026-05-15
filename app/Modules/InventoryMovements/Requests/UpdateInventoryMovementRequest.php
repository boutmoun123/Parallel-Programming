<?php

namespace App\Modules\InventoryMovements\Requests;

use App\Http\Requests\ApiRequest;

class UpdateInventoryMovementRequest extends ApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['sometimes', 'required', 'integer', 'exists:products,id'],
            'order_id' => ['sometimes', 'nullable', 'integer', 'exists:orders,id'],
            'order_item_id' => ['sometimes', 'nullable', 'integer', 'exists:order_items,id'],
            'type' => ['sometimes', 'required', 'string', 'max:50'],
            'quantity' => ['sometimes', 'required', 'integer', 'min:1'],
            'stock_before' => ['sometimes', 'required', 'integer', 'min:0'],
            'stock_after' => ['sometimes', 'required', 'integer', 'min:0'],
            'unit_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'total_price' => ['sometimes', 'numeric', 'min:0'],
            'reason' => ['sometimes', 'nullable', 'string', 'max:255'],
        ];
    }
}
