<?php

namespace App\Modules\Notifications\Services;

use App\Models\Order;
use App\Models\Notification;

class NotificationService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createNotification(array $data): Notification
    {
        $status = $data['status'] ?? Notification::STATUS_PENDING;

        return Notification::create([
            'user_id' => $data['user_id'] ?? null,
            'order_id' => $data['order_id'] ?? null,
            'type' => $data['type'],
            'message' => $data['message'],
            'status' => $status,
            'sent_at' => $data['sent_at'] ?? ($status === Notification::STATUS_SENT ? now() : null),
        ]);
    }

    public function createPaymentSuccessNotificationForOrderIfMissing(Order $order): Notification
    {
        $message = "Payment for order {$order->order_number} was completed successfully.";

        $existingNotification = Notification::query()
            ->where('user_id', $order->user_id)
            ->where('order_id', $order->id)
            ->where('type', Notification::TYPE_PAYMENT_SUCCESS)
            ->first();

        if ($existingNotification) {
            return $existingNotification;
        }

        return $this->createNotification([
            'user_id' => $order->user_id,
            'order_id' => $order->id,
            'type' => Notification::TYPE_PAYMENT_SUCCESS,
            'message' => $message,
            'status' => Notification::STATUS_SENT,
        ]);
    }
}
