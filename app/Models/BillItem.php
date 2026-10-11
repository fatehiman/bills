<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['bill_id', 'bill_no', 'payment_no', 'amount', 'term', 'provider', 'extra', 'paid_at', 'fetched_at'])]
class BillItem extends Model
{
    protected function casts(): array
    {
        return ['extra' => 'array', 'paid_at' => 'datetime', 'fetched_at' => 'datetime'];
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }
}
