<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Bill;
use App\Models\BillItem;
use App\Services\BillFetcher;
use App\Services\BillManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Saved bills of the token owner. Users can never see each other's bills (404). */
class BillController extends Controller
{
    public function __construct(private BillManager $manager, private BillFetcher $fetcher) {}

    private function mine(Request $request, string $id): Bill
    {
        return $request->user()->bills()->findOrFail($id);
    }

    public function index(Request $request): JsonResponse
    {
        $bills = $request->user()->bills()->orderBy('id')->get();

        return response()->json(['ok' => true, 'data' => $bills->map(fn ($b) => $this->bill($b))]);
    }

    public function store(Request $request): JsonResponse
    {
        $bill = $this->manager->create($request->user(), $request->only('title', 'bill_type', 'identifier', 'provider', 'period_days', 'include_mid_term'));
        $r = $this->fetcher->fetch($bill);

        return response()->json(['ok' => true, 'data' => $this->bill($bill->refresh(), true), 'fetch' => $r], 201);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json(['ok' => true, 'data' => $this->bill($this->mine($request, $id), true)]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $bill = $this->manager->update($this->mine($request, $id), $request->only('title', 'provider', 'period_days', 'include_mid_term'));

        return response()->json(['ok' => true, 'data' => $this->bill($bill, true)]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->mine($request, $id)->delete();

        return response()->json(['ok' => true, 'data' => null]);
    }

    public function fetch(Request $request, string $id): JsonResponse
    {
        $bill = $this->mine($request, $id);
        $r = $this->fetcher->fetch($bill);

        return response()->json(['ok' => true, 'fetch' => $r, 'data' => $this->bill($bill->refresh(), true)]);
    }

    public function items(Request $request, string $id): JsonResponse
    {
        $bill = $this->mine($request, $id);
        $q = $bill->items();
        if ($request->query('paid') !== null) {
            $request->boolean('paid') ? $q->whereNotNull('paid_at') : $q->whereNull('paid_at');
        }

        return response()->json(['ok' => true, 'data' => $q->get()->map(fn ($i) => $this->item($i))]);
    }

    public function updateItem(Request $request, string $id, string $itemId): JsonResponse
    {
        $bill = $this->mine($request, $id);
        $data = $request->validate(['paid' => ['required', 'boolean']]);
        $item = BillItem::where('bill_id', $bill->id)->findOrFail($itemId);
        $item->paid_at = $data['paid'] ? ($item->paid_at ?? now()) : null;
        $item->save();

        return response()->json(['ok' => true, 'data' => $this->item($item)]);
    }

    private function bill(Bill $b, bool $withItems = false): array
    {
        $out = [
            'id' => $b->id,
            'title' => $b->title,
            'type' => $b->bill_type,
            'identifier' => $b->identifier,
            'provider' => $b->provider,
            'period_days' => $b->period_days,
            'include_mid_term' => (bool) $b->include_mid_term,
            'last_fetched_at' => $b->last_fetched_at?->toIso8601String(),
            'next_fetch_at' => $b->next_fetch_at?->toIso8601String(),
            'last_status' => $b->last_status,
            'last_error' => $b->last_error,
        ];
        if ($withItems) {
            $out['unpaid_items'] = $b->items()->whereNull('paid_at')->get()->map(fn ($i) => $this->item($i))->all();
        }

        return $out;
    }

    private function item(BillItem $i): array
    {
        return [
            'id' => $i->id,
            'bill_no' => $i->bill_no,
            'payment_no' => $i->payment_no,
            'amount' => $i->amount,
            'term' => $i->term,
            'provider' => $i->provider,
            'extra' => (object) ($i->extra ?? []),
            'paid' => $i->paid_at !== null,
            'paid_at' => $i->paid_at?->toIso8601String(),
            'fetched_at' => $i->fetched_at?->toIso8601String(),
        ];
    }
}
