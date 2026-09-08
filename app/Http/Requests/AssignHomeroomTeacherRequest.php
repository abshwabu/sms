<?php

namespace App\Http\Requests;

use App\Tenancy\TenantManager;
use Illuminate\Validation\Rule;

class AssignHomeroomTeacherRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $schoolId = app(TenantManager::class)->getTenantId();

        return [
            'teacher_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')->where('school_id', $schoolId),
            ],
        ];
    }
}
