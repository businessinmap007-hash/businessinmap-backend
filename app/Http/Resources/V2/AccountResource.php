<?php

namespace App\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Lean v2 account payload — the authenticated user's own profile. Deliberately
 * small (no legacy albums/posts/ratings joins like the v1 UserResource); the
 * mobile app pulls related collections from their own v2 endpoints.
 */
class AccountResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (int) $this->id,
            'name' => (string) $this->name,
            'email' => (string) $this->email,
            'phone' => $this->phone,
            'type' => (string) $this->type,
            'is_business' => (string) $this->type === 'business',
            'about' => $this->about,
            'logo' => $this->logo ?: null,
            'cover' => $this->cover ?: null,
            'image' => $this->image ?: null,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'category_id' => $this->category_id !== null ? (int) $this->category_id : null,
            'category_child_id' => $this->category_child_id !== null ? (int) $this->category_child_id : null,
            'balance' => (float) $this->balance,
            'created_at' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
