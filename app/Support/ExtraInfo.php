<?php

namespace App\Support;

/**
 * Turns the extra data of a provider into ONE shape, so every provider gives the same keys.
 *
 * Keys (all optional, English snake_case; values keep the provider's language, e.g. Persian names):
 *   full_name, address, usage_type (tariff / consumption type), distribution_company,
 *   subscription_id, meter_serial, billing_days,
 *   previous_date (start of the period), current_date (end of the period), issue_date,
 *   payment_date (payment deadline), all as Gregorian "YYYY-MM-DD",
 *   previous_meter_reading, current_meter_reading,
 *   consumption (+ consumption_unit), consumption_mid_load / _peak / _low (electricity),
 *   cycle, bill_pdf_url.
 * Everything else the provider sent goes into `details` (original keys, nothing is lost).
 */
final class ExtraInfo
{
    /** Provider field => our key. */
    private const ALIASES = [
        'previous_reading_date' => 'previous_date',
        'current_reading_date' => 'current_date',
        'consumption_type' => 'usage_type',
        'account_type' => 'usage_type',
        'previous_counter_value' => 'previous_meter_reading',
        'current_counter_value' => 'current_meter_reading',
    ];

    /** Ghabzino "ExtraInfo" keys (Persian) => our key. */
    private const PERSIAN = [
        'تاریخ صدور صورت حساب' => 'issue_date',
        'نوع تعرفه' => 'usage_type',
        'نوع مصرف' => 'usage_type',
        'نام شرکت توزیع' => 'distribution_company',
        'شناسه اشتراک' => 'subscription_id',
        'شماره اشتراک' => 'subscription_id',
        'شماره بدنه' => 'meter_serial',
        'سریال کنتور' => 'meter_serial',
        'رقم شمارشگر پیشین' => 'previous_meter_reading',
        'رقم شمارشگر فعلی' => 'current_meter_reading',
        'کارکرد شمارشگر' => 'consumption',
        'تعداد کل روزها' => 'billing_days',
        'مصرف میان باری' => 'consumption_mid_load',
        'مصرف پر باری' => 'consumption_peak',
        'مصرف کم باری' => 'consumption_low',
    ];

    private const DATES = ['previous_date', 'current_date', 'issue_date', 'payment_date'];

    private const NUMBERS = [
        'previous_meter_reading', 'current_meter_reading', 'consumption', 'billing_days',
        'consumption_mid_load', 'consumption_peak', 'consumption_low',
    ];

    private const TEXTS = [
        'full_name', 'address', 'usage_type', 'distribution_company', 'subscription_id',
        'meter_serial', 'cycle', 'bill_pdf_url',
    ];

    private const UNITS = ['electricity' => 'kWh', 'gas' => 'm3'];

    /**
     * @param  array  $fields  Provider fields with English snake_case keys (unknown keys are kept as details)
     * @param  array  $persian  Ghabzino's decoded "ExtraInfo" (Persian keys), optional
     * @param  string  $type  Bill type (gives the consumption unit)
     */
    public static function build(array $fields, array $persian = [], string $type = ''): array
    {
        $out = [];
        $details = [];

        foreach ($persian as $key => $value) {
            if (isset(self::PERSIAN[$key])) {
                $fields[self::PERSIAN[$key]] ??= $value;
            } elseif (self::filled($value)) {
                $details[(string) $key] = $value;
            }
        }

        foreach ($fields as $key => $value) {
            $key = self::ALIASES[$key] ?? $key;
            if (! self::filled($value)) {
                continue;
            }
            if (in_array($key, self::DATES, true)) {
                $date = self::date($value);
                if ($date !== null) {
                    $out[$key] = $date;
                }
            } elseif (in_array($key, self::NUMBERS, true)) {
                $number = self::number($value);
                if ($number !== null) {
                    $out[$key] = $number;
                }
            } elseif (in_array($key, self::TEXTS, true)) {
                $out[$key] = mb_substr(trim((string) $value), 0, 300);
            } else {
                $details[(string) $key] = $value;
            }
        }

        // Electricity gives three load bands; gas gives the meter difference.
        if (! isset($out['consumption'])) {
            $bands = array_filter([$out['consumption_mid_load'] ?? null, $out['consumption_peak'] ?? null, $out['consumption_low'] ?? null], fn ($v) => $v !== null);
            if ($bands && array_sum($bands) > 0) {
                $out['consumption'] = array_sum($bands);
            } elseif (isset($out['previous_meter_reading'], $out['current_meter_reading']) && $out['current_meter_reading'] >= $out['previous_meter_reading']) {
                $out['consumption'] = $out['current_meter_reading'] - $out['previous_meter_reading'];
            }
        }
        if (isset($out['consumption'], self::UNITS[$type])) {
            $out['consumption_unit'] = self::UNITS[$type];
        }
        if ($details) {
            $out['details'] = $details;
        }

        return $out;
    }

    /**
     * Any date the providers send -> Gregorian "YYYY-MM-DD".
     * Accepts "1403/01/15" (Jalali, Zohal and Ghabzino's issue date), "06/22/2020 00:00:00"
     * (month/day/year, Ghabzino) and "2020-06-22". Persian digits are fine.
     */
    public static function date(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $v = self::digits(trim($value));
        if (preg_match('#^(\d{4})[/-](\d{1,2})[/-](\d{1,2})#', $v, $m)) {
            [$y, $mo, $d] = [(int) $m[1], (int) $m[2], (int) $m[3]];
            if ($y < 1700) { // Jalali
                [$y, $mo, $d] = self::jalaliToGregorian($y, $mo, $d);
            }

            return checkdate($mo, $d, $y) ? sprintf('%04d-%02d-%02d', $y, $mo, $d) : null;
        }
        if (preg_match('#^(\d{1,2})/(\d{1,2})/(\d{4})#', $v, $m) && checkdate((int) $m[1], (int) $m[2], (int) $m[3])) {
            return sprintf('%04d-%02d-%02d', $m[3], $m[1], $m[2]);
        }

        return null;
    }

    public static function digits(string $text): string
    {
        return strtr($text, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
    }

    private static function number(mixed $value): int|float|null
    {
        $v = str_replace(',', '', self::digits(trim((string) $value)));

        return is_numeric($v) ? $v + 0 : null;
    }

    private static function filled(mixed $value): bool
    {
        return $value !== null && $value !== '' && $value !== [];
    }

    /** @return array{0:int,1:int,2:int} */
    public static function jalaliToGregorian(int $jy, int $jm, int $jd): array
    {
        $jy += 1595;
        $days = -355668 + (365 * $jy) + intdiv($jy, 33) * 8 + intdiv(($jy % 33) + 3, 4) + $jd
            + ($jm < 7 ? ($jm - 1) * 31 : (($jm - 7) * 30) + 186);
        $gy = 400 * intdiv($days, 146097);
        $days %= 146097;
        if ($days > 36524) {
            $gy += 100 * intdiv(--$days, 36524);
            $days %= 36524;
            if ($days >= 365) {
                $days++;
            }
        }
        $gy += 4 * intdiv($days, 1461);
        $days %= 1461;
        if ($days > 365) {
            $gy += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }
        $gd = $days + 1;
        $leap = ($gy % 4 === 0 && $gy % 100 !== 0) || $gy % 400 === 0;
        $months = [0, 31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        for ($gm = 0; $gm < 13 && $gd > $months[$gm]; $gm++) {
            $gd -= $months[$gm];
        }

        return [$gy, $gm, $gd];
    }
}
