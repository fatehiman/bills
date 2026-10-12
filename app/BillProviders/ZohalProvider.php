<?php

namespace App\BillProviders;

use App\Support\BillData;
use App\Support\ExtraInfo;
use App\Support\ProviderException;
use Illuminate\Support\Facades\Http;

/**
 * Zohal (https://zohal.io). API docs: reference-docs/zohal-api-1.json
 * Note: Zohal only answers requests that come from IP addresses registered in the Zohal panel.
 */
class ZohalProvider extends BillProvider
{
    private const BASE_URL = 'https://service.zohal.io/api/v0/services/inquiry/bill/';

    /** bill type => [endpoint, request field name] */
    private const ENDPOINTS = [
        'electricity' => ['electricity', 'bill_id'],
        'water' => ['water', 'bill_id'],
        'gas' => ['gas', 'bill_id'],
        'landline' => ['fixed_line', 'phone_number'],
        'mci' => ['mci', 'mobile'],
        'irancell' => ['irancell', 'mobile'],
        'rightel' => ['rightel', 'mobile'],
    ];

    public function key(): string
    {
        return 'zohal';
    }

    public function name(): string
    {
        return 'Zohal';
    }

    public function supportedTypes(): array
    {
        return array_keys(self::ENDPOINTS);
    }

    public function settingsFields(): array
    {
        return ['token' => ['label' => 'API token (Bearer)', 'secret' => true]];
    }

    public function inquire(string $type, string $identifier): array
    {
        if (! isset(self::ENDPOINTS[$type])) {
            throw ProviderException::unavailable("Zohal does not support bill type '$type'.");
        }
        $token = $this->setting('token');
        if (! $token) {
            throw ProviderException::unavailable('Zohal API token is not set.');
        }

        [$endpoint, $field] = self::ENDPOINTS[$type];

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->asJson()
                ->timeout(30)
                ->post(self::BASE_URL.$endpoint, [$field => $identifier]);
        } catch (\Throwable $e) {
            throw ProviderException::unavailable('Zohal connection error: '.$e->getMessage());
        }

        $json = $response->json();
        $body = is_array($json) ? ($json['response_body'] ?? []) : [];
        $errorCode = (string) ($body['error_code'] ?? '');
        $message = (string) ($body['message'] ?? '');

        if (! $response->successful() || ! is_array($json) || ($json['result'] ?? null) !== 1) {
            // Wrong input: bill ID or number is not valid. Other providers would say the same.
            if ($response->status() === 400 && (
                str_ends_with($errorCode, '_INVALID') || ($json['result'] ?? null) === 6
            )) {
                throw ProviderException::invalidInput('The bill identifier is not valid.');
            }
            // The provider says there is no payable bill.
            if (preg_match('/NO_BILL|BILL_NOT_FOUND|NO_UNPAID/', $errorCode)) {
                return [];
            }
            throw ProviderException::unavailable(
                'Zohal error (HTTP '.$response->status().($errorCode ? ', '.$errorCode : '').')'.($this->clean($message) ? ': '.$this->clean($message) : '')
            );
        }

        return $this->parse($type, (array) ($body['data'] ?? []));
    }

    /** @return BillData[] */
    private function parse(string $type, array $data): array
    {
        // Mobile and landline: two terms (final_term and mid_term). Each can be null.
        if (array_key_exists('final_term', $data) || array_key_exists('mid_term', $data)) {
            $items = [];
            foreach (['final' => 'final_term', 'mid' => 'mid_term'] as $term => $key) {
                $t = $data[$key] ?? null;
                if (is_array($t) && ! empty($t['bill_id']) && ! empty($t['payment_id']) && (int) ($t['amount'] ?? 0) > 0) {
                    $extra = ExtraInfo::build(array_diff_key($t, array_flip(['bill_id', 'payment_id', 'amount'])), [], $type);
                    $items[] = new BillData((string) $t['bill_id'], (string) $t['payment_id'], (int) $t['amount'], $term, $extra);
                }
            }

            return $items;
        }

        // Electricity, water, gas: one bill. Payment ID "0" or empty means no payable bill.
        $paymentId = (string) ($data['payment_id'] ?? '');
        $billId = (string) ($data['bill_id'] ?? '');
        $amount = (int) ($data['amount'] ?? 0);
        if ($billId === '' || $paymentId === '' || $paymentId === '0' || $amount <= 0) {
            return [];
        }

        $extra = ExtraInfo::build(array_diff_key($data, array_flip(['bill_id', 'payment_id', 'amount'])), [], $type);

        return [new BillData($billId, $paymentId, $amount, null, $extra)];
    }

    private function clean(string $text): string
    {
        // Error messages stay English only (Zohal's are Persian, so we skip them). Extra info keeps Persian values.
        return preg_match('/^[ -~]*$/', $text) ? mb_substr($text, 0, 200) : '';
    }
}
