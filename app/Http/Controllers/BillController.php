<?php

namespace App\Http\Controllers;

use App\Models\Bill;
use App\Models\BillItem;
use App\Services\BillFetcher;
use App\Services\BillManager;
use App\Support\BillTypes;
use Illuminate\Http\Request;

class BillController extends Controller
{
    public function __construct(private BillManager $manager, private BillFetcher $fetcher) {}

    /** Always search inside the current user's bills. Other users' bills are invisible (404). */
    private function mine(int|string $id): Bill
    {
        return auth()->user()->bills()->findOrFail($id);
    }

    public function dashboard()
    {
        $bills = auth()->user()->bills()->with(['items' => fn ($q) => $q->whereNull('paid_at')])->orderBy('title')->get();
        $unpaid = $bills->flatMap->items;

        return view('bills.dashboard', [
            'bills' => $bills,
            'unpaidCount' => $unpaid->count(),
            'unpaidTotal' => $unpaid->sum('amount'),
        ]);
    }

    public function create()
    {
        return view('bills.form', [
            'bill' => null,
            'providerOptions' => $this->manager->providerOptions(),
        ]);
    }

    public function store(Request $request)
    {
        $bill = $this->manager->create($request->user(), $request->only('title', 'bill_type', 'identifier', 'provider', 'period_days'));
        $r = $this->fetcher->fetch($bill);

        return redirect()->route('bills.show', $bill)->with('status', $this->message($r));
    }

    public function show(string $id)
    {
        $bill = $this->mine($id);

        return view('bills.show', ['bill' => $bill, 'items' => $bill->items()->get()]);
    }

    public function edit(string $id)
    {
        return view('bills.form', [
            'bill' => $this->mine($id),
            'providerOptions' => $this->manager->providerOptions(),
        ]);
    }

    public function update(Request $request, string $id)
    {
        $bill = $this->mine($id);
        $this->manager->update($bill, $request->only('title', 'provider', 'period_days'));

        return redirect()->route('bills.show', $bill)->with('status', 'Bill saved.');
    }

    public function destroy(string $id)
    {
        $this->mine($id)->delete();

        return redirect()->route('dashboard')->with('status', 'Bill deleted.');
    }

    public function fetch(string $id)
    {
        $bill = $this->mine($id);

        return redirect()->route('bills.show', $bill)->with('status', $this->message($this->fetcher->fetch($bill)));
    }

    public function togglePaid(string $id, string $itemId)
    {
        $bill = $this->mine($id);
        $item = BillItem::where('bill_id', $bill->id)->findOrFail($itemId);
        $item->paid_at = $item->paid_at ? null : now();
        $item->save();

        return back()->with('status', $item->paid_at ? 'Marked as paid.' : 'Marked as not paid.');
    }

    private function message(array $r): string
    {
        return match ($r['status']) {
            'ok' => $r['new'] > 0 ? "Fetched. {$r['new']} new bill(s) found." : 'Fetched. No new bills.',
            'no_bill' => 'The provider says there is no payable bill now.',
            default => 'Fetch failed: '.$r['error'],
        };
    }
}
