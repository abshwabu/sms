<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string'],
            'audience_type' => ['required', 'string', 'in:all,grade_level,section,role'],
            'grade_level_id' => ['required_if:audience_type,grade_level', 'nullable', 'integer', 'exists:grade_levels,id'],
            'section_id' => ['required_if:audience_type,section', 'nullable', 'integer', 'exists:sections,id'],
            'target_role' => ['nullable', 'string', 'in:all,parent,student,teacher'],
            'priority' => ['nullable', 'string', 'in:low,normal,high,urgent'],
            'channels' => ['nullable', 'array'],
            'channels.*' => ['string', 'in:in_app,email,telegram'],
            'publish_now' => ['nullable', 'boolean'],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
