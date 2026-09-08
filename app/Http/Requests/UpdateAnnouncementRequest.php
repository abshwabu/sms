<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'body' => ['sometimes', 'required', 'string'],
            'audience_type' => ['sometimes', 'required', 'string', 'in:all,grade_level,section,role'],
            'grade_level_id' => ['nullable', 'integer', 'exists:grade_levels,id'],
            'section_id' => ['nullable', 'integer', 'exists:sections,id'],
            'target_role' => ['nullable', 'string', 'in:all,parent,student,teacher'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', 'in:in_app,email,telegram'],
            'publish_now' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
