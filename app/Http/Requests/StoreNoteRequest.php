<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreNoteRequest extends FormRequest
{
    public const TITLE_MAX = 255;

    public const CONTENT_MAX = 20000;

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:'.self::TITLE_MAX],
            'content' => ['required', 'string', 'max:'.self::CONTENT_MAX],
        ];
    }
}
