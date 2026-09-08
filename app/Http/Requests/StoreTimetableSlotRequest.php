<?php

namespace App\Http\Requests;

use App\Enums\DayOfWeek;
use Illuminate\Validation\Rule;

class StoreTimetableSlotRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'day_of_week' => ['required', 'string', Rule::in(DayOfWeek::values())],
            'period_number' => ['required', 'integer', 'min:1', 'max:12'],
            'start_time' => ['nullable', 'string'],
            'end_time' => ['nullable', 'string'],
            'room' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }
}
