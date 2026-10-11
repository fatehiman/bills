@if (session('status'))
    <div class="flash" role="status">{{ session('status') }}</div>
@endif
@if ($errors->any())
    <div class="flash err" role="alert">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif
