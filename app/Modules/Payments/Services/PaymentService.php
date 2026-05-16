<?php

namespace App\Modules\Payments\Services;

use App\Jobs\IssueOrderInvoiceJob;
use App\Jobs\SendPaymentSuccessNotificationJob;
use App\Models\Payment;
use Illuminate\Support\Str;

class PaymentService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createPayment(array $data): Payment
    {
        $existingPayment = Payment::query()
            ->where('idempotency_key', $data['idempotency_key'])
            ->first();

        if ($existingPayment) {
            return $existingPayment;
        }

        $status = $data['status'] ?? Payment::STATUS_COMPLETED;

        $payment = Payment::create([
            'order_id' => $data['order_id'] ?? null,
            'payment_method' => $data['payment_method'],
            'transaction_reference' => $data['transaction_reference'] ?? $this->transactionReference(),
            'idempotency_key' => $data['idempotency_key'],
            'amount' => $data['amount'],
            'status' => $status,
            'paid_at' => $status === Payment::STATUS_COMPLETED ? now() : null,
        ]);

        $this->dispatchPostPaymentJobs($payment);

        return $payment;
    }

    private function transactionReference(): string
    {
        return 'PAY-'.now()->timestamp.'-'.Str::upper(Str::random(8));
    }

    private function dispatchPostPaymentJobs(Payment $payment): void
    {
        if ($payment->status !== Payment::STATUS_COMPLETED || $payment->order_id === null) {
            return;
        }

        IssueOrderInvoiceJob::dispatch((int) $payment->order_id);
        SendPaymentSuccessNotificationJob::dispatch((int) $payment->order_id);
    }
}
