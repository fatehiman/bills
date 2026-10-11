<?php

namespace App\Models;

use App\Support\BillTypes;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'title', 'bill_type', 'identifier', 'provider', 'period_days', 'next_fetch_at', 'last_fetched_at', 'last_status', 'last_error'])]
class Bill extends Model
{
    protected function casts(): array
    {
        return ['next_fetch_at' => 'datetime', 'last_fetched_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class)->orderByDesc('id');
    }

    public function typeInfo(): array
    {
        return BillTypes::get($this->bill_type);
    }

    /** Set the next automatic fetch time. After a failure we retry sooner. */
    public function scheduleNext(bool $failed = false): void
    {
        if ($this->period_days <= 0) {
            $this->next_fetch_at = null;
        } elseif ($failed) {
            $this->next_fetch_at = now()->addHours(6);
        } else {
            $this->next_fetch_at = now()->addDays($this->period_days);
        }
    }
}
