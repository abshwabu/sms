<?php

namespace App\Http\Requests;

use App\Tenancy\TenantManager;
use Illuminate\Validation\Rule;

class StoreGradeLevelRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $schoolId = app(TenantManager::class)->getTenantId();

        return [
            'name' => [
                'required',
                'string',
                'max:50',
                Rule::unique('grade_levels', 'name')->where('school_id', $schoolId),
            ],
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('grade_levels', 'code')->where('school_id', $schoolId),
            ],
            'sequence' => ['required', 'integer', 'min:1'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }
}
