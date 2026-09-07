<?php

namespace App\Http\Requests;

use App\Enums\RoleEnum;
use Illuminate\Validation\Rule;

class InviteStaffRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in([
                RoleEnum::SCHOOL_ADMIN->value,
                RoleEnum::TEACHER->value,
            ])],
        ];
    }
}
