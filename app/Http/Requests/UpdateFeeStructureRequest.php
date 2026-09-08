<?php

namespace App\Http\Requests;

class UpdateFeeStructureRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'string', 'max:150'],
            'category' => ['sometimes', 'string', 'in:tuition,transport,registration,activity,facility,other'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'is_mandatory' => ['sometimes', 'boolean'],
            'condition_type' => ['sometimes', 'string', 'in:none,transport_enrollment,custom'],
            'description' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
