<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class UpdateGenreRequest extends GenreRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50', Rule::unique('genres', 'name')->ignore($this->route('genre'))],
        ];
    }
}
