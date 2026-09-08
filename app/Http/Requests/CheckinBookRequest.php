<?php

namespace App\Http\Requests;

class CheckinBookRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'returned_at' => ['nullable', 'date'],
            'daily_fine_rate' => ['nullable', 'numeric', 'min:0'],
            'fine_amount' => ['nullable', 'numeric', 'min:0'],
            'fine_paid' => ['nullable', 'boolean'],
            'fine_type' => ['nullable', 'string', 'in:overdue,damage,lost'],
            'payment_method' => ['nullable', 'string', 'max:50'],
            'fine_notes' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
