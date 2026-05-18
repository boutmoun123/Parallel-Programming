<?php

namespace App\Modules\Payments\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Modules\Payments\Requests\StorePaymentRequest;
use App\Modules\Payments\Resources\PaymentResource;
use App\Modules\Payments\Services\PaymentService;
use Illuminate\Http\JsonResponse;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $paymentService)
    {
    }

    public function index(): JsonResponse
    {
        $payments = Payment::query()
            ->latest()
            ->get();

        return $this->success('Payments retrieved successfully', PaymentResource::collection($payments));
    }

    public function store(StorePaymentRequest $request): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => 'Payments must be created through checkout.',
            'data' => null,
            'errors' => [
                'payment' => ['Use the checkout endpoint to create payments.'],
            ],
        ], 422);
    }

    public function show(Payment $payment): JsonResponse
    {
        return $this->success('Payment retrieved successfully', new PaymentResource($payment));
    }

    private function success(string $message, mixed $data, int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'errors' => null,
        ], $status);
    }
}
