<?php

namespace App\Http\Requests;

class StoreReviewRequest extends ReviewRequest
{
    public function authorize(): bool
    {
        return true;
    }
}
