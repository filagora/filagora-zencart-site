<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string'],
            'return_to' => ['nullable', 'string', 'max:2048'],
            'attachments' => ['array', 'max:5'],
            'attachments.*' => ['file', 'max:10240', 'mimes:jpg,jpeg,png,gif,webp,pdf,txt,zip,log'],
            'remove_attachments' => ['array'],
            'remove_attachments.*' => ['integer'],
        ];
    }
}
