<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class MediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'path' => ['sometimes', 'required', 'string', 'max:4096'],
            'folder' => ['nullable', 'string', 'max:4096'],
            'track' => ['nullable', 'integer', 'min:0'],
            'audio' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
