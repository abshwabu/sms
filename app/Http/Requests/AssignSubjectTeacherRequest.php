<?php

namespace App\Http\Requests;

use App\Tenancy\TenantManager;
use Illuminate\Validation\Rule;

class AssignSubjectTeacherRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $schoolId = app(TenantManager::class)->getTenantId();

        return [
            'course_id' => [
                'required',
                'integer',
                Rule::exists('courses', 'id')->where('school_id', $schoolId),
            ],
            'staff_id' => [
                'required',
                'integer',
                Rule::exists('staff', 'id')->where('school_id', $schoolId),
            ],
        ];
    }
}
