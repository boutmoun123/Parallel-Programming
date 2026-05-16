<?php

namespace App\Modules\Orders\Requests;

use App\Http\Requests\ApiRequest;

class CheckoutOrderRequest extends ApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'payment_method' => ['required', 'string', 'max:50'],
            'idempotency_key' => ['required', 'string', 'max:100'],
        ];
    }
}
