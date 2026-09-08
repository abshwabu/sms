<?php

namespace App\Http\Requests;

use App\Enums\DayOfWeek;
use Illuminate\Validation\Rule;

class UpdateTimetableSlotRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
            'teacher_id' => ['nullable', 'integer', 'exists:users,id'],
            'day_of_week' => ['nullable', 'string', Rule::in(DayOfWeek::values())],
            'period_number' => ['nullable', 'integer', 'min:1', 'max:12'],
            'start_time' => ['nullable', 'string'],
            'end_time' => ['nullable', 'string'],
            'room' => ['nullable', 'string', 'max:50'],
            'color' => ['nullable', 'string', 'max:20'],
        ];
    }
}
