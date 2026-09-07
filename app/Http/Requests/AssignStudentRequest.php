<?php

namespace App\Http\Requests;

use App\Tenancy\TenantManager;
use Illuminate\Validation\Rule;

class AssignStudentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $schoolId = app(TenantManager::class)->getTenantId();

        return [
            'student_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('school_id', $schoolId),
            ],
            'roll_number' => ['nullable', 'string', 'max:50'],
            'enrolled_at' => ['nullable', 'date'],
        ];
    }
}
