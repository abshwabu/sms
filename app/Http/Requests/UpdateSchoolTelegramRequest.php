<?php

namespace App\Http\Requests;

class UpdateSchoolTelegramRequest extends BaseApiRequest
{
    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $mergeData = [];

        if ($this->has('telegram_bot_username')) {
            $username = trim((string) $this->input('telegram_bot_username'));
            $mergeData['telegram_bot_username'] = $username !== '' ? ltrim($username, '@') : null;
        }

        if ($this->has('telegram_bot_token')) {
            $token = trim((string) $this->input('telegram_bot_token'));
            $mergeData['telegram_bot_token'] = $token !== '' ? $token : null;
        }

        if (!empty($mergeData)) {
            $this->merge($mergeData);
        }
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'telegram_bot_token' => ['nullable', 'string', 'max:255'],
            'telegram_bot_username' => ['nullable', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_]{5,32}$/'],
            'register_webhook' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Custom validation messages.
     */
    public function messages(): array
    {
        return [
            'telegram_bot_username.regex' => 'The Telegram bot username must be between 5 and 32 characters and contain only letters, numbers, and underscores.',
        ];
    }
}
