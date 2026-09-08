<?php

namespace App\Http\Requests;

use App\Enums\DayOfWeek;
use Illuminate\Validation\Rule;

class BatchTimetableSlotRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'replace_existing' => ['nullable', 'boolean'],
            'slots' => ['required', 'array', 'min:1'],
            'slots.*.subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'slots.*.teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'slots.*.day_of_week' => ['required', 'string', Rule::in(DayOfWeek::values())],
            'slots.*.period_number' => ['required', 'integer', 'min:1', 'max:12'],
            'slots.*.start_time' => ['nullable', 'string'],
            'slots.*.end_time' => ['nullable', 'string'],
            'slots.*.room' => ['nullable', 'string', 'max:50'],
            'slots.*.color' => ['nullable', 'string', 'max:20'],
        ];
    }
}
