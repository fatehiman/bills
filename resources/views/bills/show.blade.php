@extends('layouts.app')
@section('title', $bill->title)
@section('content')
    @php($t = $bill->typeInfo())
    <div class="page-head">
        <div class="actions c-{{ $t['color'] }}">
            <span class="badge-ico">@include('partials.icon', ['name' => $t['icon']])</span>
            <div>
                <h1 style="margin:0">{{ $bill->title }}</h1>
                <div class="muted small">{{ $t['label'] }} &middot; <span class="mono">{{ $bill->identifier }}</span></div>
            </div>
        </div>
        <div class="actions">
            <form method="post" action="{{ route('bills.fetch', $bill->id) }}">@csrf
                <button class="btn green" type="submit">@include('partials.icon', ['name' => 'refresh']) Fetch now</button>
            </form>
            <a class="btn alt" href="{{ route('bills.edit', $bill->id) }}">@include('partials.icon', ['name' => 'edit']) Edit</a>
            <form method="post" action="{{ route('bills.destroy', $bill->id) }}" data-confirm="Delete this bill and all its history?">@csrf @method('DELETE')
                <button class="btn red" type="submit">@include('partials.icon', ['name' => 'trash']) Delete</button>
            </form>
        </div>
    </div>

    <div class="card" style="margin-bottom:16px">
        <div class="actions" style="gap:18px">
            <span><span class="muted small">Provider</span><br><b>{{ app(\App\Services\ProviderRegistry::class)->get($bill->provider)?->name() ?? $bill->provider }}</b></span>
            <span><span class="muted small">Mid term</span><br><b>{{ $bill->include_mid_term ? 'Included' : 'Not included' }}</b></span>
            <span><span class="muted small">Auto fetch</span><br><b>{{ \App\Services\BillManager::PERIODS[$bill->period_days] ?? $bill->period_days.' days' }}</b></span>
            <span><span class="muted small">Last check</span><br><b>{{ $bill->last_fetched_at?->diffForHumans() ?? 'never' }}</b></span>
            <span><span class="muted small">Next check</span><br><b>{{ $bill->next_fetch_at?->diffForHumans() ?? '-' }}</b></span>
        </div>
        @if ($bill->last_status === 'error')
            <p class="flash err" style="margin:12px 0 0">Last fetch failed: {{ $bill->last_error }}</p>
        @elseif ($bill->last_status === 'no_bill')
            <p class="muted small" style="margin:12px 0 0">The provider said there is no payable bill at the last check.</p>
        @endif
    </div>

    <h2>Bills to pay</h2>
    @php($unpaid = $items->whereNull('paid_at'))
    @if ($unpaid->isEmpty())
        <div class="card empty" style="margin-bottom:16px">Nothing to pay now.</div>
    @else
        <div class="grid" style="margin-bottom:20px">
            @foreach ($unpaid as $item) @include('partials.item', ['item' => $item, 'bill' => $bill]) @endforeach
        </div>
    @endif

    @php($paid = $items->whereNotNull('paid_at'))
    @if ($paid->isNotEmpty())
        <h2>Paid</h2>
        <div class="grid">
            @foreach ($paid as $item) @include('partials.item', ['item' => $item, 'bill' => $bill]) @endforeach
        </div>
    @endif
@endsection
