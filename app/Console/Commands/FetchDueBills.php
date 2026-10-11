<?php

namespace App\Console\Commands;

use App\Models\Bill;
use App\Services\BillFetcher;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bills:fetch-due {--limit=5 : Maximum bills to fetch in one run}')]
#[Description('Fetch bills whose automatic fetch time has come')]
class FetchDueBills extends Command
{
    public function handle(BillFetcher $fetcher): int
    {
        $bills = Bill::whereNotNull('next_fetch_at')
            ->where('next_fetch_at', '<=', now())
            ->where('period_days', '>', 0)
            ->orderBy('next_fetch_at')
            ->limit((int) $this->option('limit'))
            ->get();

        foreach ($bills as $bill) {
            $r = $fetcher->fetch($bill);
            $this->line("bill #{$bill->id} ({$bill->bill_type}): {$r['status']}, new={$r['new']}".($r['error'] ? ", error={$r['error']}" : ''));
        }

        if ($bills->isEmpty()) {
            $this->line('Nothing to fetch.');
        }

        return self::SUCCESS;
    }
}
