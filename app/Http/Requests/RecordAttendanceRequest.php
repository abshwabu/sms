<?php

namespace App\Http\Requests;

use App\Enums\AttendanceStatus;
use Illuminate\Validation\Rule;

class RecordAttendanceRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'date' => ['required', 'date'],
            'default_status' => ['nullable', 'string', Rule::in(AttendanceStatus::values())],
            'records' => ['nullable', 'array'],
            'records.*.student_id' => ['required_with:records', 'integer'],
            'records.*.status' => ['required_with:records', 'string', Rule::in(AttendanceStatus::values())],
            'records.*.remarks' => ['nullable', 'string', 'max:500'],
        ];
    }
}
