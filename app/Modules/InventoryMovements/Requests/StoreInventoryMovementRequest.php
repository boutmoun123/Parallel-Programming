<?php

namespace App\Modules\InventoryMovements\Requests;

use App\Http\Requests\ApiRequest;

class StoreInventoryMovementRequest extends ApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'order_id' => ['nullable', 'integer', 'exists:orders,id'],
            'order_item_id' => ['nullable', 'integer', 'exists:order_items,id'],
            'type' => ['required', 'string', 'max:50'],
            'quantity' => ['required', 'integer', 'min:1'],
            'stock_before' => ['required', 'integer', 'min:0'],
            'stock_after' => ['required', 'integer', 'min:0'],
            'unit_price' => ['nullable', 'numeric', 'min:0'],
            'total_price' => ['sometimes', 'numeric', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
