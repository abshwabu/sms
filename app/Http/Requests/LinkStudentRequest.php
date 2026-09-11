<?php

namespace App\Http\Requests;

use App\Models\ParentProfile;
use App\Tenancy\TenantManager;
use Illuminate\Validation\Rule;

class LinkStudentRequest extends BaseApiRequest
{
    public function rules(): array
    {
        $parent = $this->route('parent');
        if (! ($parent instanceof ParentProfile) && is_numeric($parent)) {
            $parent = ParentProfile::withoutTenantScope()->find((int) $parent);
        }

        $schoolId = app(TenantManager::class)->getTenantId()
            ?? $this->user()?->school_id
            ?? ($parent instanceof ParentProfile ? $parent->school_id : null);

        $studentRules = ['required', 'integer'];
        if ($schoolId) {
            $studentRules[] = Rule::exists('students', 'id')->where('school_id', $schoolId);
        } else {
            $studentRules[] = Rule::exists('students', 'id');
        }

        return [
            'student_id' => $studentRules,
            'relationship' => ['nullable', 'string', Rule::in(['mother', 'father', 'guardian', 'other'])],
            'is_primary_contact' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $parent = $this->route('parent');
            if (! ($parent instanceof ParentProfile) && is_numeric($parent)) {
                $parent = ParentProfile::withoutTenantScope()->find((int) $parent);
            }

            $studentId = $this->input('student_id');

            if ($parent instanceof ParentProfile && $studentId) {
                if ($parent->isLinkedTo($studentId)) {
                    $validator->errors()->add('student_id', 'This student is already linked to this parent.');
                }
            }
        });
    }
}
