<?php

namespace App\Http\Requests;

class StoreTransportRouteRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'vehicle_info' => ['required', 'string', 'max:255'],
            'driver_name' => ['required', 'string', 'max:255'],
            'driver_contact' => ['required', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:1', 'max:200'],
            'status' => ['nullable', 'string', 'in:active,inactive'],
            'description' => ['nullable', 'string', 'max:1000'],
            'stops' => ['nullable', 'array'],
            'stops.*.stop_name' => ['required_with:stops', 'string', 'max:255'],
            'stops.*.pickup_time' => ['required_with:stops', 'string'],
            'stops.*.dropoff_time' => ['required_with:stops', 'string'],
            'stops.*.sequence' => ['nullable', 'integer', 'min:1'],
            'stops.*.landmark' => ['nullable', 'string', 'max:255'],
        ];
    }
}
