{{-- One payable bill (BillItem). Needs $item and $bill --}}
<div class="item {{ $item->paid_at ? 'paid' : '' }}">
    <div class="row">
        <div>
            <span class="amount">{{ number_format($item->amount) }} Rial</span>
            <span class="muted small">(about {{ number_format($item->amount / 10) }} Toman)</span>
        </div>
        <div class="actions">
            @if ($item->term)
                <span class="pill">{{ $item->term === 'final' ? 'Final term' : 'Mid term' }}</span>
            @endif
            @if ($item->paid_at)
                <span class="pill ok">Paid</span>
            @endif
        </div>
    </div>
    <div class="nums">
        @include('partials.num', ['label' => 'Bill ID', 'value' => $item->bill_no])
        @include('partials.num', ['label' => 'Payment ID', 'value' => $item->payment_no])
    </div>
    @if (! empty($item->extra))
        <details>
            <summary>More details</summary>
            <dl class="extra">
                @foreach ($item->extra as $k => $v)
                    <dt>{{ ucfirst(str_replace('_', ' ', $k)) }}</dt>
                    <dd>{{ is_scalar($v) ? $v : json_encode($v, JSON_UNESCAPED_UNICODE) }}</dd>
                @endforeach
            </dl>
        </details>
    @endif
    <div class="row">
        <span class="muted small">Fetched {{ $item->fetched_at?->diffForHumans() }}</span>
        <form method="post" action="{{ route('bills.paid', [$bill->id, $item->id]) }}">@csrf
            <button class="btn sm {{ $item->paid_at ? 'alt' : 'green' }}" type="submit">
                @include('partials.icon', ['name' => 'check']) {{ $item->paid_at ? 'Mark as not paid' : 'Mark as paid' }}
            </button>
        </form>
    </div>
</div>
