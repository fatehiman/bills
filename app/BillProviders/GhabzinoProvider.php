<?php

namespace App\BillProviders;

use App\Support\BillData;
use App\Support\ExtraInfo;
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
            return $this->parse($type, (array) ($json['Parameters'] ?? []));
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
    private function parse(string $type, array $data): array
    {
        // Mobile and landline: FinalTerm and MidTerm. Each can be null.
        if (array_key_exists('FinalTerm', $data) || array_key_exists('MidTerm', $data)) {
            $items = [];
            foreach (['final' => 'FinalTerm', 'mid' => 'MidTerm'] as $term => $key) {
                $t = $data[$key] ?? null;
                if (is_array($t) && ! empty($t['BillID']) && ! empty($t['PaymentID']) && (string) $t['PaymentID'] !== '0' && (int) ($t['Amount'] ?? 0) > 0) {
                    $extra = ExtraInfo::build($this->snake(array_diff_key($t, array_flip(['BillID', 'PaymentID', 'Amount']))), [], $type);
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

        // ExtraInfo is a JSON string with Persian keys (usage, meter readings, issue date...).
        $persian = is_string($data['ExtraInfo'] ?? null) ? json_decode($data['ExtraInfo'], true) : ($data['ExtraInfo'] ?? null);
        $extra = ExtraInfo::build([
            'full_name' => $data['FullName'] ?? null,
            'address' => $data['Address'] ?? null,
            'previous_date' => $data['PreviousDate'] ?? null,
            'current_date' => $data['CurrentDate'] ?? null,
            'payment_date' => $data['PaymentDate'] ?? null,
            'bill_pdf_url' => $data['BillPdfUrl'] ?? null,
        ], is_array($persian) ? $persian : [], $type);

        return [new BillData($billId, $paymentId, $amount, null, $extra)];
    }

    /** @return array<string, mixed> "BillID" style keys -> "bill_id" style keys */
    private function snake(array $fields): array
    {
        $out = [];
        foreach ($fields as $key => $value) {
            $out[strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', (string) $key))] = $value;
        }

        return $out;
    }

    private function clean(string $text): string
    {
        // Error messages stay English only (Ghabzino's are Persian, so we skip them). Extra info keeps Persian values.
        return preg_match('/^[ -~]*$/', $text) ? mb_substr($text, 0, 200) : '';
    }
}
