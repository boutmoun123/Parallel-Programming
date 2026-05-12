<?php

namespace App\Modules\Products\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Modules\Products\Services\ProductService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;

class ProductController extends Controller
{
    public function __construct(private readonly ProductService $productService)
    {
    }

    public function index(): JsonResponse
    {
        $startedAt = microtime(true);
        $result = $this->productService->activeProducts();

        return $this->success('Active products retrieved successfully', $result['data'], $this->meta($result['source'], $startedAt));
    }

    public function show(Product $product): JsonResponse
    {
        $startedAt = microtime(true);

        try {
            $result = $this->productService->productDetails($product);
        } catch (ModelNotFoundException) {
            return $this->error('Product not found', ['product' => ['The product is not active or does not exist.']], 404);
        }

        return $this->success('Product details retrieved successfully', $result['data'], $this->meta($result['source'], $startedAt));
    }

    public function mostOrdered(): JsonResponse
    {
        $startedAt = microtime(true);
        $result = $this->productService->mostOrderedProducts();

        return $this->success('Most ordered products retrieved successfully', $result['data'], $this->meta($result['source'], $startedAt));
    }

    /**
     * @param  array<string, mixed>  $meta
     */
    private function success(string $message, mixed $data, array $meta, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'meta' => $meta,
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

    /**
     * @return array{source: string, response_time_ms: float}
     */
    private function meta(string $source, float $startedAt): array
    {
        return [
            'source' => $source,
            'response_time_ms' => round((microtime(true) - $startedAt) * 1000, 2),
        ];
    }
}
