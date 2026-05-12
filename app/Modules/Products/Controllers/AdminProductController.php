<?php

namespace App\Modules\Products\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Modules\Products\Requests\StoreProductRequest;
use App\Modules\Products\Requests\UpdateProductQuantityRequest;
use App\Modules\Products\Requests\UpdateProductRequest;
use App\Modules\Products\Resources\ProductResource;
use App\Modules\Products\Services\ProductService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class AdminProductController extends Controller
{
    public function __construct(private readonly ProductService $productService)
    {
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $startedAt = microtime(true);
        $product = $this->productService->createProduct($request->validated());

        return $this->success('Product created successfully', new ProductResource($product), $startedAt, 201);
    }

    public function update(UpdateProductRequest $request, Product $product): JsonResponse
    {
        $startedAt = microtime(true);

        try {
            $updatedProduct = $this->productService->updateProduct($product, $request->validated());
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), ['quantity_counter' => [$exception->getMessage()]], 422);
        }

        return $this->success('Product updated successfully', new ProductResource($updatedProduct), $startedAt);
    }

    public function destroy(Product $product): JsonResponse
    {
        $startedAt = microtime(true);

        $this->productService->deleteProduct($product);

        return $this->success('Product deleted successfully', null, $startedAt);
    }

    public function updateQuantity(UpdateProductQuantityRequest $request, Product $product): JsonResponse
    {
        $startedAt = microtime(true);

        try {
            $updatedProduct = $this->productService->updateQuantityWithLock($product, (int) $request->validated('stock_quantity'));
        } catch (InvalidArgumentException $exception) {
            return $this->error($exception->getMessage(), ['stock_quantity' => [$exception->getMessage()]], 422);
        }

        return $this->success('Product quantity updated successfully', new ProductResource($updatedProduct), $startedAt);
    }

    private function success(string $message, mixed $data, float $startedAt, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => [
                'source' => 'database',
                'response_time_ms' => round((microtime(true) - $startedAt) * 1000, 2),
            ],
            'errors' => null,
        ], $status);
    }

    private function error(string $message, mixed $errors, int $status): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message,
            'data' => null,
            'errors' => $errors,
        ], $status);
    }
}
