<?php

namespace App\Support;

/**
 * All bill types known to the app. A provider can only serve types listed here.
 * To add a new type: add one entry here and one icon in resources/views/partials/icon.blade.php.
 */
class BillTypes
{
    public static function all(): array
    {
        return [
            'electricity' => [
                'label' => 'Electricity',
                'identifier_label' => 'Electricity bill ID',
                'identifier_hint' => 'Digits only, from your paper bill',
                'pattern' => '/^\d{6,20}$/',
                'icon' => 'bolt',
                'color' => 'amber',
            ],
            'water' => [
                'label' => 'Water',
                'identifier_label' => 'Water bill ID',
                'identifier_hint' => 'Digits only, from your paper bill',
                'pattern' => '/^\d{6,20}$/',
                'icon' => 'drop',
                'color' => 'sky',
            ],
            'gas' => [
                'label' => 'Gas',
                'identifier_label' => 'Gas bill ID',
                'identifier_hint' => 'Digits only, from your paper bill',
                'pattern' => '/^\d{6,20}$/',
                'icon' => 'flame',
                'color' => 'orange',
            ],
            'landline' => [
                'label' => 'Landline phone',
                'identifier_label' => 'Phone number with area code',
                'identifier_hint' => 'Example: 02112345678',
                'pattern' => '/^0\d{10}$/',
                'icon' => 'phone',
                'color' => 'violet',
            ],
            'mci' => [
                'label' => 'MCI (Hamrah-e Aval)',
                'identifier_label' => 'Mobile number',
                'identifier_hint' => 'Example: 09121234567',
                'pattern' => '/^09\d{9}$/',
                'icon' => 'signal',
                'color' => 'blue',
            ],
            'irancell' => [
                'label' => 'Irancell',
                'identifier_label' => 'Mobile number',
                'identifier_hint' => 'Example: 09351234567',
                'pattern' => '/^09\d{9}$/',
                'icon' => 'signal',
                'color' => 'yellow',
            ],
            'rightel' => [
                'label' => 'Rightel',
                'identifier_label' => 'Mobile number',
                'identifier_hint' => 'Example: 09211234567',
                'pattern' => '/^09\d{9}$/',
                'icon' => 'signal',
                'color' => 'rose',
            ],
        ];
    }

    public static function exists(string $type): bool
    {
        return isset(self::all()[$type]);
    }

    public static function get(string $type): array
    {
        return self::all()[$type] ?? [
            'label' => ucfirst($type),
            'identifier_label' => 'Identifier',
            'identifier_hint' => '',
            'pattern' => '/^.+$/',
            'icon' => 'doc',
            'color' => 'slate',
        ];
    }

    public static function slugs(): array
    {
        return array_keys(self::all());
    }

    /** Normalise digits: remove spaces/dashes and convert Persian/Arabic digits to English digits. */
    public static function normalizeIdentifier(string $value): string
    {
        $value = strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);

        return preg_replace('/[\s\-]+/', '', $value) ?? $value;
    }

    public static function validIdentifier(string $type, string $identifier): bool
    {
        return (bool) preg_match(self::get($type)['pattern'], $identifier);
    }
}
