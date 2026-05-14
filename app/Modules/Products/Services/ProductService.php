<?php

namespace App\Modules\Products\Services;

use App\Models\Product;
use App\Modules\Products\Resources\ProductResource;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Throwable;

class ProductService
{
    private const CACHE_TTL_SECONDS = 60;

    private const ACTIVE_PRODUCTS_CACHE_KEY = 'products:active:list';

    private const MOST_ORDERED_CACHE_KEY = 'products:most-ordered';

    /**
     * @return array{data: array<int, array<string, mixed>>, source: string}
     */
    public function activeProducts(): array
    {
        return $this->rememberWithSource(self::ACTIVE_PRODUCTS_CACHE_KEY, function (): array {
            $products = Product::query()
                ->where('status', 'active')
                ->latest()
                ->get();

            return ProductResource::collection($products)->resolve();
        });
    }

    /**
     * @return array{data: array<string, mixed>, source: string}
     */
    public function productDetails(Product $product): array
    {
        $cacheKey = $this->productDetailsCacheKey($product->id);

        return $this->rememberWithSource($cacheKey, function () use ($product): array {
            $activeProduct = Product::query()
                ->whereKey($product->id)
                ->where('status', 'active')
                ->first();

            if (! $activeProduct) {
                throw (new ModelNotFoundException())->setModel(Product::class, [$product->id]);
            }

            return (new ProductResource($activeProduct))->resolve();
        });
    }

    /**
     * @return array{data: array<int, array<string, mixed>>, source: string}
     */
    public function mostOrderedProducts(): array
    {
        return $this->rememberWithSource(self::MOST_ORDERED_CACHE_KEY, function (): array {
            $products = Product::query()
                ->select('products.*', DB::raw('SUM(order_items.quantity) as total_ordered'))
                ->join('order_items', 'order_items.product_id', '=', 'products.id')
                ->where('products.status', 'active')
                ->groupBy(
                    'products.id',
                    'products.name',
                    'products.description',
                    'products.price',
                    'products.stock_quantity',
                    'products.quantity_counter',
                    'products.status',
                    'products.photos',
                    'products.created_at',
                    'products.updated_at',
                )
                ->orderByDesc('total_ordered')
                ->limit(10)
                ->get();

            return ProductResource::collection($products)->resolve();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createProduct(array $data): Product
    {
        $data['quantity_counter'] ??= $data['stock_quantity'];
        $storedPhotos = $this->storeUploadedPhotos($data['photos'] ?? []);
        $data['photos'] = $storedPhotos;

        try {
            $product = Product::create($data);
        } catch (Throwable $exception) {
            $this->deleteStoredPhotos($storedPhotos);

            throw $exception;
        }

        $this->clearCatalogCaches();

        return $product;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateProduct(Product $product, array $data): Product
    {
        if (
            array_key_exists('quantity_counter', $data)
            && (int) $data['quantity_counter'] > $product->stock_quantity
        ) {
            throw new InvalidArgumentException('Quantity counter cannot exceed stock quantity.');
        }

        $storedPhotos = null;

        if (array_key_exists('photos', $data)) {
            $storedPhotos = $this->storeUploadedPhotos($data['photos'] ?? []);
            $data['photos'] = $storedPhotos;
        }

        $existingPhotos = $product->photos ?? [];

        try {
            $product->fill($data);
            $product->save();
        } catch (Throwable $exception) {
            if ($storedPhotos !== null) {
                $this->deleteStoredPhotos($storedPhotos);
            }

            throw $exception;
        }

        if ($storedPhotos !== null) {
            $this->deleteStoredPhotos($existingPhotos);
        }

        $this->clearProductCaches($product->id);

        return $product;
    }

    public function deleteProduct(Product $product): void
    {
        $productId = $product->id;
        $storedPhotos = $product->photos ?? [];

        $product->delete();
        $this->deleteStoredPhotos($storedPhotos);

        $this->clearProductCaches($productId);
    }

    public function updateQuantityWithLock(Product $product, int $newQuantity): Product
    {
        if ($newQuantity < 0) {
            throw new InvalidArgumentException('Stock quantity cannot be negative.');
        }

        return DB::transaction(function () use ($product, $newQuantity): Product {
            // Pessimistic locking keeps stock changes serialized for future purchase flows.
            $lockedProduct = Product::query()
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $reservedQuantity = max(0, $lockedProduct->stock_quantity - $lockedProduct->quantity_counter);

            $lockedProduct->stock_quantity = $newQuantity;
            $lockedProduct->quantity_counter = max(0, $newQuantity - $reservedQuantity);
            $lockedProduct->save();

            $this->clearProductCaches($lockedProduct->id);

            return $lockedProduct;
        });
    }

    public function reserveQuantityWithLock(Product $product, int $quantity): Product
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Reservation quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($product, $quantity): Product {
            $lockedProduct = Product::query()
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedProduct->quantity_counter < $quantity) {
                throw new InvalidArgumentException('Requested quantity is not available for reservation.');
            }

            $lockedProduct->quantity_counter -= $quantity;
            $lockedProduct->save();

            $this->clearProductCaches($lockedProduct->id);

            return $lockedProduct;
        });
    }

    public function restoreReservedQuantityWithLock(Product $product, int $quantity): Product
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Restored quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($product, $quantity): Product {
            $lockedProduct = Product::query()
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedProduct->quantity_counter = min(
                $lockedProduct->stock_quantity,
                $lockedProduct->quantity_counter + $quantity,
            );
            $lockedProduct->save();

            $this->clearProductCaches($lockedProduct->id);

            return $lockedProduct;
        });
    }

    public function decrementStockAfterPaymentWithLock(Product $product, int $quantity): Product
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Purchased quantity must be greater than zero.');
        }

        return DB::transaction(function () use ($product, $quantity): Product {
            $lockedProduct = Product::query()
                ->whereKey($product->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedProduct->stock_quantity < $quantity) {
                throw new InvalidArgumentException('Purchased quantity exceeds stock quantity.');
            }

            $lockedProduct->stock_quantity -= $quantity;
            $lockedProduct->quantity_counter = min($lockedProduct->quantity_counter, $lockedProduct->stock_quantity);
            $lockedProduct->save();

            $this->clearProductCaches($lockedProduct->id);

            return $lockedProduct;
        });
    }

    /**
     * @template T of array
     *
     * @param  callable(): T  $callback
     * @return array{data: T, source: string}
     */
    private function rememberWithSource(string $key, callable $callback): array
    {
         $cache = Cache::store(config('cache.default'));

        // Expose cache/database source so Postman and JMeter can demonstrate Redis hits.
        if ($cache->has($key)) {
            return [
                'data' => $cache->get($key),
                'source' => 'cache',
            ];
        }

        $data = $callback();

        $cache->put($key, $data, self::CACHE_TTL_SECONDS);

        return [
            'data' => $data,
            'source' => 'database',
        ];
    }

    private function clearProductCaches(int $productId): void
    {
        $cache = Cache::store(config('cache.default'));

        $cache->forget(self::ACTIVE_PRODUCTS_CACHE_KEY);
        $cache->forget($this->productDetailsCacheKey($productId));
        $cache->forget(self::MOST_ORDERED_CACHE_KEY);
    }

    private function clearCatalogCaches(): void
    {
        $cache = Cache::store(config('cache.default'));

        $cache->forget(self::ACTIVE_PRODUCTS_CACHE_KEY);
        $cache->forget(self::MOST_ORDERED_CACHE_KEY);
    }

    private function productDetailsCacheKey(int $productId): string
    {
        return "products:details:{$productId}";
    }

    /**
     * @param  array<int, UploadedFile>  $photos
     * @return list<string>
     */
    private function storeUploadedPhotos(array $photos): array
    {
        return array_values(array_map(
            fn (UploadedFile $photo): string => $photo->store('products', 'public'),
            $photos,
        ));
    }

    /**
     * @param  array<int, mixed>  $photos
     */
    private function deleteStoredPhotos(array $photos): void
    {
        $storedPhotos = array_values(array_filter(
            $photos,
            fn (mixed $photo): bool => is_string($photo) && ! filter_var($photo, FILTER_VALIDATE_URL),
        ));

        if ($storedPhotos === []) {
            return;
        }

        Storage::disk('public')->delete($storedPhotos);
    }
}
