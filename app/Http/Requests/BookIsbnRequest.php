<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * ISBN 検索（`GET /books/isbn/{isbn}`）のパスパラメータの検証。
 *
 * 画面の JS が `data.error` を表示するため、検証エラーも常に `{error}` の JSON（400）で返す。
 */
class BookIsbnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * パスの `{isbn}` を検証対象に含める。
     *
     * @return array<string, mixed>
     */
    public function validationData(): array
    {
        return array_merge($this->all(), ['isbn' => $this->route('isbn')]);
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'isbn' => ['required', 'digits:13'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            response()->json(['error' => $validator->errors()->first()], 400),
        );
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'isbn.required' => 'ISBNは13桁で入力してください。',
            'isbn.digits' => 'ISBNは13桁で入力してください。',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'isbn' => 'ISBN',
        ];
    }
}
