<?php

namespace App\Http\Requests;

use App\Tenancy\TenantManager;
use Illuminate\Validation\Rule;

class ImportStudentsCsvRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $schoolId = app(TenantManager::class)->getTenantId();

        return [
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:10240'],
            'academic_year_id' => [
                'required',
                'integer',
                Rule::exists('academic_years', 'id')->where('school_id', $schoolId),
            ],
            'section_id' => [
                'required',
                'integer',
                Rule::exists('sections', 'id')->where('school_id', $schoolId),
            ],
            'default_password' => ['nullable', 'string', 'min:6'],
        ];
    }
}
