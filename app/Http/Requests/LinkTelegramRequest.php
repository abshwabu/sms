<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LinkTelegramRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'link_code' => ['required', 'string', 'max:50'],
            'telegram_chat_id' => ['required', 'string', 'max:100'],
            'telegram_username' => ['nullable', 'string', 'max:100'],
            'first_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}
