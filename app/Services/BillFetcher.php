<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\BillItem;
use App\Support\GatewayException;
use App\Support\ProviderException;

/**
 * Fetches the payable bills for a saved Bill and stores them as BillItem rows.
 */
class BillFetcher
{
    public function __construct(private BillGateway $gateway) {}

    /**
     * @return array{status: string, new: int, error: ?string}
     */
    public function fetch(Bill $bill): array
    {
        $new = 0;
        $error = null;

        try {
            // A saved bill always uses the provider chosen for it (no fallback).
            $result = $this->gateway->inquire($bill->bill_type, $bill->identifier, $bill->provider);

            // Mid term bills are skipped unless the bill asks for them.
            $bills = array_values(array_filter(
                $result['bills'],
                fn ($data) => $data->term !== 'mid' || $bill->include_mid_term
            ));

            foreach ($bills as $data) {
                $item = BillItem::firstOrNew([
                    'bill_id' => $bill->id,
                    'bill_no' => $data->billNo,
                    'payment_no' => $data->paymentNo,
                ]);
                if (! $item->exists) {
                    $new++;
                }
                $item->fill([
                    'amount' => $data->amount,
                    'term' => $data->term,
                    'provider' => $result['provider'],
                    'extra' => $data->extra ?: null,
                    'fetched_at' => now(),
                ])->save();
            }
            $status = $bills ? 'ok' : 'no_bill';
        } catch (ProviderException|GatewayException $e) {
            $status = 'error';
            $error = $e->getMessage();
            if ($e instanceof GatewayException && $e->attempts) {
                $error .= ' ('.implode('; ', array_map(fn ($a) => $a['provider'].': '.$a['error'], $e->attempts)).')';
            }
        }

        $bill->last_fetched_at = now();
        $bill->last_status = $status;
        $bill->last_error = $error ? mb_substr($error, 0, 500) : null;
        $bill->scheduleNext(failed: $status === 'error');
        $bill->save();

        return ['status' => $status, 'new' => $new, 'error' => $error];
    }
}
