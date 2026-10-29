<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

class StoreBookRequest extends BookRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rules = $this->apiRules();
        $rules['isbn'][] = Rule::unique('books', 'isbn');

        return $rules;
    }
}
