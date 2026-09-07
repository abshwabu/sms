<?php

namespace App\Http\Requests;

use App\Tenancy\TenantManager;
use Illuminate\Validation\Rule;

class StoreSectionRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $schoolId = app(TenantManager::class)->getTenantId();
        $academicYearId = $this->input('academic_year_id');
        $gradeLevelId = $this->input('grade_level_id');

        return [
            'academic_year_id' => [
                'required',
                'integer',
                Rule::exists('academic_years', 'id')->where('school_id', $schoolId),
            ],
            'grade_level_id' => [
                'required',
                'integer',
                Rule::exists('grade_levels', 'id')->where('school_id', $schoolId),
            ],
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('sections', 'name')
                    ->where('academic_year_id', $academicYearId)
                    ->where('grade_level_id', $gradeLevelId),
            ],
            'capacity' => ['sometimes', 'integer', 'min:1', 'max:200'],
            'homeroom_teacher_id' => [
                'nullable',
                'integer',
                Rule::exists('users', 'id')->where('school_id', $schoolId),
            ],
        ];
    }
}
