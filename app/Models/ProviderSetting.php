<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

#[Fillable(['provider', 'name', 'value'])]
class ProviderSetting extends Model
{
    public static function read(string $provider, string $name): ?string
    {
        $row = static::where('provider', $provider)->where('name', $name)->first();
        if (! $row || $row->value === null || $row->value === '') {
            return null;
        }
        try {
            return Crypt::decryptString($row->value);
        } catch (\Throwable) {
            return null;
        }
    }

    public static function write(string $provider, string $name, ?string $value): void
    {
        static::updateOrCreate(
            ['provider' => $provider, 'name' => $name],
            ['value' => $value === null || $value === '' ? null : Crypt::encryptString($value)],
        );
    }

    public static function isSet(string $provider, string $name): bool
    {
        return static::read($provider, $name) !== null;
    }
}
