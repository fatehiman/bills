{{-- A number with a copy button. Needs $label and $value --}}
<div class="num">
    <div class="grow">
        <small>{{ $label }}</small>
        <div class="v mono">{{ $value }}</div>
    </div>
    <button type="button" class="btn alt sm" data-copy="{{ $value }}" title="Copy {{ $label }}">@include('partials.icon', ['name' => 'copy']) <span class="t">Copy</span></button>
</div>
