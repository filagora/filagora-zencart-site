<?php

namespace App\Http\Requests;

use Filagora\Forums\Services\ForumAccess;
use Illuminate\Foundation\Http\FormRequest;

class ModeratePostsBulkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(ForumAccess::class)->canModerate($this->user());
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('new_thread_title'))) {
            $this->merge(['new_thread_title' => trim($this->input('new_thread_title'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'in:move,promote,delete'],
            'post_ids' => ['required', 'array', 'min:1', 'max:200'],
            'post_ids.*' => ['integer', 'distinct', 'exists:posts,id'],
            'destination_thread_id' => ['nullable', 'integer', 'required_if:action,move', 'exists:threads,id'],
            'destination_forum_id' => ['nullable', 'integer', 'required_if:action,promote', 'exists:forums,id'],
            'new_thread_title' => ['nullable', 'string', 'required_if:action,promote', 'min:3', 'max:255'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'return_to' => ['nullable', 'string', 'max:2048'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'post_ids.required' => __('forum.errors.no_posts_selected'),
            'post_ids.min' => __('forum.errors.no_posts_selected'),
            'post_ids.max' => __('forum.errors.too_many_posts_selected'),
        ];
    }
}
