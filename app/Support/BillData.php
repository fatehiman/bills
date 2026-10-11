<?php

namespace App\Support;

/** One payable bill returned by a provider (normalised, provider independent). */
final class BillData
{
    /**
     * @param  string  $billNo  The bill ID (shenase ghabz)
     * @param  string  $paymentNo  The payment ID (shenase pardakht)
     * @param  int  $amount  Amount in Rial
     * @param  string|null  $term  "final", "mid" or null (mobile and landline bills have two terms)
     * @param  array  $extra  Any extra info (name, address, dates...). Keys in English snake_case.
     */
    public function __construct(
        public readonly string $billNo,
        public readonly string $paymentNo,
        public readonly int $amount,
        public readonly ?string $term = null,
        public readonly array $extra = [],
    ) {}

    public function toArray(): array
    {
        return [
            'bill_no' => $this->billNo,
            'payment_no' => $this->paymentNo,
            'amount' => $this->amount,
            'term' => $this->term,
            'extra' => (object) $this->extra,
        ];
    }
}
