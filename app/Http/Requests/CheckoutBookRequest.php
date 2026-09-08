<?php

namespace App\Http\Requests;

class CheckoutBookRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'book_id' => ['required', 'integer', 'exists:books,id'],
            'student_id' => ['nullable', 'integer', 'exists:students,id', 'required_without_all:staff_id,user_id'],
            'staff_id' => ['nullable', 'integer', 'exists:staff,id', 'required_without_all:student_id,user_id'],
            'user_id' => ['nullable', 'integer', 'exists:users,id', 'required_without_all:student_id,staff_id'],
            'borrowed_at' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after_or_equal:borrowed_at'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
