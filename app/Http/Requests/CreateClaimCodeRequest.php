<?php

namespace App\Http\Requests;

use App\Enums\RoleEnum;
use Illuminate\Validation\Rule;

class CreateClaimCodeRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'role' => ['required', 'string', Rule::in([
                RoleEnum::STUDENT->value,
                RoleEnum::PARENT->value,
            ])],
            'student_id' => ['nullable', 'integer', 'exists:users,id'],
        ];
    }
}
