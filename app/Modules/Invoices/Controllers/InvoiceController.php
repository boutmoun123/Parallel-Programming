<?php

namespace App\Modules\Invoices\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Modules\Invoices\Requests\StoreInvoiceRequest;
use App\Modules\Invoices\Resources\InvoiceResource;
use App\Modules\Invoices\Services\InvoiceService;
use Illuminate\Http\JsonResponse;

class InvoiceController extends Controller
{
    public function __construct(private readonly InvoiceService $invoiceService)
    {
    }

    public function index(): JsonResponse
    {
        $invoices = Invoice::query()
            ->latest()
            ->get();

        return $this->success('Invoices retrieved successfully', InvoiceResource::collection($invoices));
    }

    public function store(StoreInvoiceRequest $request): JsonResponse
    {
        $invoice = $this->invoiceService->createInvoice($request->validated());

        return $this->success('Invoice created successfully', new InvoiceResource($invoice), 201);
    }

    public function show(Invoice $invoice): JsonResponse
    {
        return $this->success('Invoice retrieved successfully', new InvoiceResource($invoice));
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
