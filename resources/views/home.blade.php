@extends('layouts.guest')
@section('title', 'Bills - coming soon')
@section('content')
    <div class="hero">
        <div class="mark">@include('partials.icon', ['name' => 'receipt'])</div>
        <h1>Bills</h1>
        <p class="muted">This site is under construction. Please come back soon.</p>
        <div class="dots" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
    </div>
@endsection
