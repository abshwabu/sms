<?php

namespace App\Http\Requests;

class StoreTransportStopRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'stop_name' => ['required', 'string', 'max:255'],
            'pickup_time' => ['required', 'string'],
            'dropoff_time' => ['required', 'string'],
            'sequence' => ['nullable', 'integer', 'min:1'],
            'landmark' => ['nullable', 'string', 'max:255'],
        ];
    }
}
