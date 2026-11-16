<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** PUT and PATCH both accept partial updates; at least one field is required. */
class UpdateNoteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'title' => ['required_without:content', 'string', 'max:'.StoreNoteRequest::TITLE_MAX],
            'content' => ['required_without:title', 'string', 'max:'.StoreNoteRequest::CONTENT_MAX],
        ];
    }
}
