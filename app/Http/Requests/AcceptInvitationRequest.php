<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rules\Password;

class AcceptInvitationRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'size:64'],
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', Password::min(8)],
            'phone' => ['nullable', 'string', 'max:30'],
        ];
    }
}
