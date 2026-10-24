<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

class UpdateBookRequest extends BookRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = $this->apiRules();
        $rules['isbn'][] = Rule::unique('books', 'isbn')->ignore($this->route('book'));

        return $rules;
    }
}
