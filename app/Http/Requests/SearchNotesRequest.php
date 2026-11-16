<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchNotesRequest extends FormRequest
{
    public const DEFAULT_LIMIT = 10;

    public function rules(): array
    {
        return [
            'q' => ['required', 'string', 'min:2', 'max:500'],
            'limit' => ['sometimes', 'integer', 'between:1,50'],
        ];
    }
}
