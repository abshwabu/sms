<?php

namespace App\Http\Requests;

class AssignStudentTransportRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'transport_route_id' => ['required', 'integer', 'exists:transport_routes,id'],
            'transport_stop_id' => ['required', 'integer', 'exists:transport_stops,id'],
            'status' => ['nullable', 'string', 'in:active,suspended,cancelled'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}
