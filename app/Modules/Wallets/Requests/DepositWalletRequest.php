<?php

namespace App\Modules\Wallets\Requests;

use App\Http\Requests\ApiRequest;

class DepositWalletRequest extends ApiRequest
{
    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
        ];
    }
}
