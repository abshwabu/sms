<?php

namespace App\Http\Requests;

use App\Tenancy\TenantManager;
use Illuminate\Validation\Rule;

class LinkStudentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $schoolId = app(TenantManager::class)->getTenantId();

        return [
            'student_id' => [
                'required',
                'integer',
                Rule::exists('students', 'id')->where('school_id', $schoolId),
            ],
            'relationship' => ['required', 'string', Rule::in(['mother', 'father', 'guardian', 'other'])],
            'is_primary_contact' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $parent = $this->route('parent');
            $studentId = $this->input('student_id');

            if ($parent instanceof \App\Models\ParentProfile && $studentId) {
                if ($parent->isLinkedTo($studentId)) {
                    $validator->errors()->add('student_id', 'This student is already linked to this parent.');
                }
            }
        });
    }
}
