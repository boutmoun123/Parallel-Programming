<?php

namespace App\Modules\Carts\Requests;

use App\Http\Requests\ApiRequest;

class StoreCartRequest extends ApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'max:50'],
            'total_items' => ['sometimes', 'integer', 'min:0'],
            'total_amount' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
