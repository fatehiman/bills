<?php

namespace App\BillProviders;

use App\Support\BillData;
use App\Support\ProviderException;
use Illuminate\Support\Facades\Http;

/**
 * Ghabzino (Ayantech inquiry web service). API knowledge comes from the old notes project (inquireBills.php).
 * The token is sent inside the JSON body (Identity.Token), not as a header.
 * Ghabzino has no Rightel service. Its gas service needs the gas participation code (ParticipateCode).
 */
class GhabzinoProvider extends BillProvider
{
    private const BASE_URL = 'https://core.inquiry.ayantech.ir/webservices/core.svc/';

    /** bill type => [endpoint, request field name] */
    private const ENDPOINTS = [
        'electricity' => ['ElectricityBillInquiry', 'ElectricityBillID'],
        'water' => ['WaterBillInquiry', 'WaterBillID'],
        'gas' => ['GasBillInquiry', 'ParticipateCode'],
        'landline' => ['FixedLineBillInquiry', 'FixedLineNumber'],
        'mci' => ['MCIMobileBillInquiry', 'MobileNumber'],
        'irancell' => ['MtnMobileBillInquiry', 'MobileNumber'],
    ];

    public function key(): string
    {
        return 'ghabzino';
    }

    public function name(): string
    {
        return 'Ghabzino';
    }

    public function supportedTypes(): array
    {
        return array_keys(self::ENDPOINTS);
    }

    public function settingsFields(): array
    {
        return ['token' => ['label' => 'API token (Identity token)', 'secret' => true]];
    }

    public function inquire(string $type, string $identifier): array
    {
        if (! isset(self::ENDPOINTS[$type])) {
            throw ProviderException::unavailable("Ghabzino does not support bill type '$type'.");
        }
        $token = $this->setting('token');
        if (! $token) {
            throw ProviderException::unavailable('Ghabzino API token is not set.');
        }

        [$endpoint, $field] = self::ENDPOINTS[$type];

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->timeout(30)
                ->post(self::BASE_URL.$endpoint, [
                    'Identity' => ['Token' => $token],
                    'Parameters' => [$field => $identifier, 'TraceNumber' => '1'],
                ]);
        } catch (\Throwable $e) {
            throw ProviderException::unavailable('Ghabzino connection error: '.$e->getMessage());
        }

        $json = $response->json();
        $code = is_array($json) ? (string) ($json['Status']['Code'] ?? '') : '';
        $description = is_array($json) ? (string) ($json['Status']['Description'] ?? '') : '';

        if ($code === 'G00000') {
            return $this->parse((array) ($json['Parameters'] ?? []));
        }

        // The provider says there is no payable bill. This is a normal answer, not an error.
        if ($code === 'GD2278' || str_contains($description, 'هیچ قبض قابل پرداختی')) {
            return [];
        }

        $text = $this->clean($description) ?: (stripos((string) $response->body(), 'rate limit') !== false ? 'rate limit exceeded' : '');
        throw ProviderException::unavailable(
            'Ghabzino error (HTTP '.$response->status().($code ? ', '.$code : '').')'.($text ? ': '.$text : '')
        );
    }

    /** @return BillData[] */
    private function parse(array $data): array
    {
        // Mobile and landline: FinalTerm and MidTerm. Each can be null.
        if (array_key_exists('FinalTerm', $data) || array_key_exists('MidTerm', $data)) {
            $items = [];
            foreach (['final' => 'FinalTerm', 'mid' => 'MidTerm'] as $term => $key) {
                $t = $data[$key] ?? null;
                if (is_array($t) && ! empty($t['BillID']) && ! empty($t['PaymentID']) && (string) $t['PaymentID'] !== '0' && (int) ($t['Amount'] ?? 0) > 0) {
                    $extra = array_filter(['cycle' => $t['Cycle'] ?? null], fn ($v) => $v !== null && $v !== '');
                    $items[] = new BillData((string) $t['BillID'], (string) $t['PaymentID'], (int) $t['Amount'], $term, $extra);
                }
            }

            return $items;
        }

        // Electricity, water, gas: one bill. Payment ID "0" or empty means no payable bill.
        $billId = (string) ($data['BillID'] ?? '');
        $paymentId = (string) ($data['PaymentID'] ?? '');
        $amount = (int) ($data['Amount'] ?? 0);
        if ($billId === '' || $paymentId === '' || $paymentId === '0' || $amount <= 0) {
            return [];
        }

        // ExtraInfo has Persian keys, so we skip it (the app is English only).
        $extra = array_filter([
            'full_name' => $this->clean((string) ($data['FullName'] ?? '')),
            'address' => $this->clean((string) ($data['Address'] ?? '')),
            'previous_date' => $this->date($data['PreviousDate'] ?? null),
            'current_date' => $this->date($data['CurrentDate'] ?? null),
            'payment_date' => $this->date($data['PaymentDate'] ?? null),
        ], fn ($v) => $v !== null && $v !== '');

        return [new BillData($billId, $paymentId, $amount, null, $extra)];
    }

    /** "06/22/2020 00:00:00" -> "2020-06-22" (the provider sends month/day/year). */
    private function date(mixed $value): ?string
    {
        if (! is_string($value) || ! preg_match('#^(\d{2})/(\d{2})/(\d{4})#', $value, $m)) {
            return null;
        }

        return "$m[3]-$m[1]-$m[2]";
    }

    private function clean(string $text): string
    {
        // The app is English only. Ghabzino messages are Persian, so we skip them.
        return preg_match('/^[ -~]*$/', $text) ? mb_substr($text, 0, 200) : '';
    }
}
