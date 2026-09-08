<?php

namespace App\Http\Requests;

class StoreBookRequest extends BaseApiRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'author' => ['required', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:50'],
            'category' => ['required', 'string', 'max:100'],
            'copies_total' => ['required', 'integer', 'min:1', 'max:10000'],
            'copies_available' => ['nullable', 'integer', 'min:0', 'lte:copies_total'],
            'shelf_location' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'publication_year' => ['nullable', 'integer', 'min:1000', 'max:2100'],
        ];
    }
}
