<?php

namespace App\Modules\InventoryMovements\Services;

use App\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Collection;

class InventoryMovementService
{
    public function list(): Collection
    {
        return InventoryMovement::query()
            ->latest('created_at')
            ->get();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): InventoryMovement
    {
        return InventoryMovement::create($this->normalizeData($data + [
            'created_at' => now(),
        ]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(InventoryMovement $inventoryMovement, array $data): InventoryMovement
    {
        $payload = array_merge(
            $inventoryMovement->only([
                'product_id',
                'order_id',
                'order_item_id',
                'type',
                'quantity',
                'stock_before',
                'stock_after',
                'unit_price',
                'reason',
            ]),
            $data,
        );

        $inventoryMovement->fill($this->normalizeData($payload));
        $inventoryMovement->save();

        return $inventoryMovement->fresh();
    }

    public function delete(InventoryMovement $inventoryMovement): void
    {
        $inventoryMovement->delete();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizeData(array $data): array
    {
        if (array_key_exists('unit_price', $data) && $data['unit_price'] !== null) {
            $data['total_price'] = round((float) $data['unit_price'] * (int) $data['quantity'], 2);
        }

        return $data;
    }
}
