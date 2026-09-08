<?php

namespace App\Http\Requests;

class InitiateOnlinePaymentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['nullable', 'numeric', 'min:0.01'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'return_url' => ['nullable', 'string', 'url'],
        ];
    }
}
