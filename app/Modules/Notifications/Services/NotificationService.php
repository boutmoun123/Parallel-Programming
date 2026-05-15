<?php

namespace App\Modules\Notifications\Services;

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
}
