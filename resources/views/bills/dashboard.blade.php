@extends('layouts.app')
@section('title', 'My bills')
@section('content')
    <div class="page-head">
        <h1>My bills</h1>
        <a class="btn" href="{{ route('bills.create') }}">@include('partials.icon', ['name' => 'plus']) Add bill</a>
    </div>

    @if ($bills->isEmpty())
        <div class="card empty">
            <div class="badge-ico c-violet">@include('partials.icon', ['name' => 'receipt'])</div>
            <h2>No bills yet</h2>
            <p>Add your first bill. We will fetch it for you and show the Bill ID and Payment ID, so you can pay it.</p>
            <a class="btn" href="{{ route('bills.create') }}">@include('partials.icon', ['name' => 'plus']) Add bill</a>
        </div>
    @else
        <div class="stats">
            <div class="stat"><span class="muted small">Bills</span><b>{{ $bills->count() }}</b></div>
            <div class="stat"><span class="muted small">To pay</span><b>{{ $unpaidCount }}</b></div>
            <div class="stat"><span class="muted small">Total to pay (Rial)</span><b>{{ number_format($unpaidTotal) }}</b></div>
        </div>

        <div class="grid">
            @foreach ($bills as $bill)
                @php($t = $bill->typeInfo())
                <section class="card bill-card c-{{ $t['color'] }}">
                    <div class="head">
                        <span class="badge-ico">@include('partials.icon', ['name' => $t['icon']])</span>
                        <div>
                            <h3><a class="title" href="{{ route('bills.show', $bill) }}">{{ $bill->title }}</a></h3>
                            <div class="muted small">{{ $t['label'] }} &middot; <span class="mono">{{ $bill->identifier }}</span></div>
                        </div>
                    </div>

                    @forelse ($bill->items as $item)
                        @include('partials.item', ['item' => $item, 'bill' => $bill])
                    @empty
                        @if ($bill->last_status === 'error')
                            <span class="pill bad">@include('partials.icon', ['name' => 'alert']) Last fetch failed</span>
                        @elseif ($bill->last_fetched_at)
                            <span class="pill ok">@include('partials.icon', ['name' => 'check']) Nothing to pay</span>
                        @else
                            <span class="pill warn">@include('partials.icon', ['name' => 'clock']) Not fetched yet</span>
                        @endif
                    @endforelse

                    <div class="row actions" style="justify-content:space-between">
                        <span class="muted small">{{ $bill->last_fetched_at ? 'Checked '.$bill->last_fetched_at->diffForHumans() : 'Never checked' }}</span>
                        <form method="post" action="{{ route('bills.fetch', $bill->id) }}">@csrf
                            <button class="btn alt sm" type="submit">@include('partials.icon', ['name' => 'refresh']) Fetch now</button>
                        </form>
                    </div>
                </section>
            @endforeach
        </div>
    @endif
@endsection
