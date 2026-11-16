<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ListNotesRequest extends FormRequest
{
    public const DEFAULT_LIMIT = 10;

    public function rules(): array
    {
        return [
            'page' => ['sometimes', 'integer', 'min:1'],
            'limit' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
