<?php

namespace App\Http\Requests;

class BulkAssignSectionTransportRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'transport_stop_id' => ['required', 'integer', 'exists:transport_stops,id'],
            'student_ids' => ['nullable', 'array'],
            'student_ids.*' => ['integer', 'exists:students,id'],
        ];
    }
}
