<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Str;

#[Fillable(['username', 'password', 'is_admin'])]
#[Hidden(['password', 'remember_token', 'api_token_hash'])]
class User extends Authenticatable
{
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'api_token_created_at' => 'datetime',
        ];
    }

    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /** Create (or rotate) the API token. Returns the plain token. It cannot be read again. */
    public function rotateApiToken(): string
    {
        $token = 'bl_'.Str::random(40);
        $this->forceFill([
            'api_token_hash' => self::hashToken($token),
            'api_token_hint' => substr($token, -4),
            'api_token_created_at' => now(),
        ])->save();

        return $token;
    }

    public function revokeApiToken(): void
    {
        $this->forceFill(['api_token_hash' => null, 'api_token_hint' => null, 'api_token_created_at' => null])->save();
    }
}
