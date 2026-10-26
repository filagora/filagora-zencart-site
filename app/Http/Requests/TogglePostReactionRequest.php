<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TogglePostReactionRequest extends FormRequest
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
            'type' => ['nullable', 'in:like'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ];
    }
}
