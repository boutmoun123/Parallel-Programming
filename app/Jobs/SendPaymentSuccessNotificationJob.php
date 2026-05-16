<?php

namespace App\Jobs;

use App\Models\Order;
use App\Modules\Notifications\Services\NotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SendPaymentSuccessNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $orderId)
    {
        $this->afterCommit();
    }

    public function handle(NotificationService $notificationService): void
    {
        $order = Order::query()->find($this->orderId);

        if (! $order) {
            return;
        }

        $notificationService->createPaymentSuccessNotificationForOrderIfMissing($order);
    }
}
