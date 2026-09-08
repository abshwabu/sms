<?php

namespace App\Http\Requests;

use App\Models\Book;

class UpdateBookRequest extends BaseApiRequest
{
    public function rules(): array
    {
        /** @var Book|null $book */
        $book = $this->route('book');
        $minCopies = $book ? $book->borrowedCopies() : 1;

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'author' => ['sometimes', 'required', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:50'],
            'category' => ['sometimes', 'required', 'string', 'max:100'],
            'copies_total' => ['sometimes', 'required', 'integer', "min:{$minCopies}", 'max:10000'],
            'copies_available' => ['nullable', 'integer', 'min:0'],
            'shelf_location' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'publication_year' => ['nullable', 'integer', 'min:1000', 'max:2100'],
        ];
    }
}
