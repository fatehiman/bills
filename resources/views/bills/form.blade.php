@php
    $editing = (bool) $bill;
    $types = \App\Support\BillTypes::all();
    $typeData = collect($types)->map(fn ($t) => ['label' => $t['identifier_label'], 'hint' => $t['identifier_hint']]);
    $currentType = old('bill_type', $bill?->bill_type ?? array_key_first($types));
@endphp
@extends('layouts.app')
@section('title', $editing ? 'Edit bill' : 'Add bill')
@section('content')
    <div class="page-head"><h1>{{ $editing ? 'Edit bill' : 'Add bill' }}</h1></div>

    <div class="card" style="max-width:640px">
        <form method="post" action="{{ $editing ? route('bills.update', $bill->id) : route('bills.store') }}">
            @csrf
            @if ($editing) @method('PUT') @endif

            <div class="field">
                <label for="title">Name</label>
                <input type="text" id="title" name="title" maxlength="120" value="{{ old('title', $bill?->title) }}" placeholder="Example: Home electricity" required>
            </div>

            <div class="field">
                <label for="bill_type">Bill type</label>
                @if ($editing)
                    <input type="text" value="{{ $types[$bill->bill_type]['label'] ?? $bill->bill_type }}" disabled>
                    <input type="hidden" id="bill_type" value="{{ $bill->bill_type }}">
                @else
                    <select id="bill_type" name="bill_type">
                        @foreach ($types as $slug => $t)
                            <option value="{{ $slug }}" @selected($currentType === $slug)>{{ $t['label'] }}</option>
                        @endforeach
                    </select>
                @endif
            </div>

            <div class="field">
                <label for="identifier" id="identifier_label">{{ $types[$currentType]['identifier_label'] ?? 'Identifier' }}</label>
                @if ($editing)
                    <input type="text" id="identifier" value="{{ $bill->identifier }}" disabled>
                @else
                    <input type="text" id="identifier" name="identifier" inputmode="numeric" maxlength="60" value="{{ old('identifier') }}" required>
                @endif
                <div class="hint" id="identifier_hint">{{ $types[$currentType]['identifier_hint'] ?? '' }}</div>
            </div>

            <div class="two">
                <div class="field">
                    <label for="provider">Bill API provider</label>
                    <select id="provider" name="provider" data-current="{{ old('provider', $bill?->provider) }}" required></select>
                    <div class="hint">The service that fetches this bill.</div>
                </div>
                <div class="field">
                    <label for="period_days">Auto fetch</label>
                    <select id="period_days" name="period_days">
                        @foreach (\App\Services\BillManager::PERIODS as $days => $text)
                            <option value="{{ $days }}" @selected((int) old('period_days', $bill?->period_days ?? 30) === $days)>{{ $text }}</option>
                        @endforeach
                    </select>
                    <div class="hint">How often we check for a new bill.</div>
                </div>
            </div>

            <div class="actions">
                <button class="btn" type="submit">@include('partials.icon', ['name' => 'check']) {{ $editing ? 'Save' : 'Add and fetch now' }}</button>
                <a class="btn alt" href="{{ $editing ? route('bills.show', $bill->id) : route('dashboard') }}">Cancel</a>
            </div>
        </form>
    </div>

    <script type="application/json" id="provider-data">@json($providerOptions ?: new stdClass)</script>
    <script type="application/json" id="type-data">@json($typeData)</script>
@endsection
