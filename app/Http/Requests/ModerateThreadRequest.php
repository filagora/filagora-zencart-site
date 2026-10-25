<?php

namespace App\Http\Requests;

use App\Actions\BanUserAction;
use Filagora\Forums\Services\ForumAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ModerateThreadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(ForumAccess::class)->canModerate($this->user());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'action' => ['required', 'in:lock,unlock,pin,unpin,move,rename,approve,reject_permanently,mark_unread'],
            'forum_id' => ['nullable', 'integer', 'required_if:action,move', 'exists:forums,id'],
            'title' => ['nullable', 'string', 'required_if:action,rename', 'min:3', 'max:255'],
            'reason' => ['nullable', 'string', 'max:5000'],
            'return_to' => ['nullable', 'string', 'max:2048'],
            'ban' => ['sometimes', 'boolean'],
            'ban_reason' => ['exclude_unless:ban,1', 'nullable', 'string', 'max:4000'],
            'ban_duration' => ['exclude_unless:ban,1', 'nullable', Rule::in([...array_keys(BanUserAction::DURATIONS), 'permanent', 'custom'])],
            'ban_until' => ['exclude_unless:ban,1', 'nullable', 'required_if:ban_duration,custom', 'date', 'after:now'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required_if' => 'The reason field is required when action is :value.',
            'forum_id.required_if' => 'The forum id field is required when action is :value.',
            'title.required_if' => 'The title field is required when action is :value.',
            'ban_until.required_if' => 'Choose an end date for a custom-length ban.',
        ];
    }
}
