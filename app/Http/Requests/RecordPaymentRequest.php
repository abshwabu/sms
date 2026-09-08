<?php

namespace App\Http\Requests;

class RecordPaymentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:0.01'],
            'method' => ['required', 'string', 'in:cash,bank_transfer,online,telebirr,cbe_birr,check,other'],
            'paid_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:500'],
            'gateway_reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
