<?php

namespace App\Modules\Invoices\Services;

use App\Models\Invoice;
use Illuminate\Support\Str;

class InvoiceService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createInvoice(array $data): Invoice
    {
        $status = $data['status'] ?? Invoice::STATUS_ISSUED;

        return Invoice::create([
            'order_id' => $data['order_id'] ?? null,
            'invoice_number' => $data['invoice_number'] ?? $this->invoiceNumber(),
            'status' => $status,
            'issued_at' => $data['issued_at'] ?? ($status === Invoice::STATUS_ISSUED ? now() : null),
        ]);
    }

    private function invoiceNumber(): string
    {
        return 'INV-'.now()->format('YmdHis').'-'.Str::upper(Str::random(8));
    }
}
