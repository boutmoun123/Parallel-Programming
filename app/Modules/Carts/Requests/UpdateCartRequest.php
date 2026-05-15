<?php

namespace App\Modules\Carts\Requests;

use App\Http\Requests\ApiRequest;

class UpdateCartRequest extends ApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'status' => ['sometimes', 'required', 'string', 'max:50'],
            'total_items' => ['sometimes', 'integer', 'min:0'],
            'total_amount' => ['sometimes', 'numeric', 'min:0'],
        ];
    }
}
