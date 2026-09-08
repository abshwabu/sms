<?php

namespace App\Http\Requests;

class PublishReportCardRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'principal_remarks' => ['nullable', 'string', 'max:1000'],
            'homeroom_remarks' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
