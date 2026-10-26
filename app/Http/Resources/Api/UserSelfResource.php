<?php

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserSelfResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => (int) $this->id,
            'username' => (string) ($this->username ?? ''),
            'name' => (string) ($this->name ?? ''),
            'email' => (string) ($this->email ?? ''),
            'email_verified_at' => $this->email_verified_at?->toISOString(),
            'avatar_url' => (string) ($this->avatar_url ?? ''),
        ];
    }
}
