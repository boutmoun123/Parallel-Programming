<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const TYPE_PAYMENT_SUCCESS = 'payment_success';

    public const TYPE_PAYMENT_FAILED = 'payment_failed';

    public const TYPE_INVOICE_CREATED = 'invoice_created';

    public const TYPE_ORDER_STATUS_CHANGED = 'order_status_changed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'order_id',
        'type',
        'message',
        'status',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }
}
