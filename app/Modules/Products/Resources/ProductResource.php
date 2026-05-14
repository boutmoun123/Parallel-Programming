<?php

namespace App\Modules\Products\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $photos = array_values(array_filter(array_map(
            fn (mixed $photo): ?string => $this->photoUrl($request, $photo),
            $this->photos ?? [],
        )));

        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'price' => $this->price,
            'stock_quantity' => $this->stock_quantity,
            'quantity_counter' => $this->quantity_counter,
            'status' => $this->status,
            'photos' => $photos,
            'first_photo' => $photos[0] ?? null,
            'total_ordered' => $this->when(isset($this->total_ordered), (int) $this->total_ordered),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function photoUrl(Request $request, mixed $photo): ?string
    {
        if (! is_string($photo) || $photo === '') {
            return null;
        }

        if (filter_var($photo, FILTER_VALIDATE_URL)) {
            return $photo;
        }

        $storageUrl = Storage::disk('public')->url($photo);
        $path = parse_url($storageUrl, PHP_URL_PATH) ?: $storageUrl;

        return rtrim($request->getSchemeAndHttpHost(), '/').'/'.ltrim($path, '/');
    }
}
