<?php

namespace App\Jobs;

use App\Models\Order;
use App\Modules\Invoices\Services\InvoiceService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class IssueOrderInvoiceJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $orderId)
    {
        $this->afterCommit();
    }

    public function handle(InvoiceService $invoiceService): void
    {
        $order = Order::query()->find($this->orderId);

        if (! $order) {
            return;
        }

        $invoiceService->createInvoiceForOrderIfMissing($order);
    }
}
