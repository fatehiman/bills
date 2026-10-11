@extends('layouts.app')
@section('title', 'Providers')
@section('content')
    <div class="page-head"><h1>Bill API providers</h1></div>

    <div class="card" style="margin-bottom:20px">
        <h2>Which provider serves which bill type</h2>
        <p class="muted">Tick the providers for each bill type. When an API caller does not choose a provider, we try them in order of <b>Order</b> (1 first). If the first one cannot answer, we use the next one.</p>
        <form method="post" action="{{ route('admin.providers.mapping') }}">@csrf
            <div class="tablewrap">
                <table>
                    <thead><tr><th>Bill type</th><th>Providers (use / order)</th></tr></thead>
                    <tbody>
                    @foreach ($types as $slug => $t)
                        <tr class="c-{{ $t['color'] }}">
                            <td><span class="actions"><span class="badge-ico" style="width:36px;height:36px">@include('partials.icon', ['name' => $t['icon']])</span> <b>{{ $t['label'] }}</b></span></td>
                            <td>
                                @php($any = false)
                                @foreach ($providers as $p)
                                    @if ($p->supports($slug))
                                        @php($any = true)
                                        @php($row = $map->get($slug.'|'.$p->key()))
                                        <div class="actions" style="margin-bottom:6px">
                                            <label class="check" style="min-width:140px">
                                                <input type="checkbox" name="map[{{ $slug }}][{{ $p->key() }}][enabled]" value="1" @checked($row?->enabled)>
                                                {{ $p->name() }}
                                            </label>
                                            <span class="muted small">Order</span>
                                            <input type="number" min="1" max="99" name="map[{{ $slug }}][{{ $p->key() }}][priority]" value="{{ $row?->priority ?? 10 }}">
                                        </div>
                                    @endif
                                @endforeach
                                @unless ($any) <span class="muted">No provider supports this type.</span> @endunless
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="actions" style="margin-top:14px"><button class="btn" type="submit">@include('partials.icon', ['name' => 'check']) Save mapping</button></div>
        </form>
    </div>

    <h2>Provider settings</h2>
    <div class="grid">
        @foreach ($providers as $p)
            <div class="card">
                <h3>@include('partials.icon', ['name' => 'plug']) {{ $p->name() }} <span class="muted small mono">({{ $p->key() }})</span></h3>
                <p class="muted small">Serves: {{ implode(', ', array_map(fn ($s) => $types[$s]['label'] ?? $s, $p->supportedTypes())) }}</p>
                @if ($p->settingsFields())
                    <form method="post" action="{{ route('admin.providers.settings', $p->key()) }}">@csrf
                        @foreach ($p->settingsFields() as $name => $field)
                            @php($isSet = \App\Models\ProviderSetting::isSet($p->key(), $name))
                            <div class="field">
                                <label for="s_{{ $p->key() }}_{{ $name }}">{{ $field['label'] }}
                                    <span class="pill {{ $isSet ? 'ok' : 'warn' }}">{{ $isSet ? 'set' : 'not set' }}</span></label>
                                <input type="{{ ($field['secret'] ?? false) ? 'password' : 'text' }}" id="s_{{ $p->key() }}_{{ $name }}"
                                       name="settings[{{ $name }}]" autocomplete="off"
                                       placeholder="{{ ($field['secret'] ?? false) && $isSet ? 'Leave empty to keep the saved value' : '' }}">
                            </div>
                        @endforeach
                        <button class="btn" type="submit">Save settings</button>
                    </form>
                @else
                    <p class="muted">This provider has no settings.</p>
                @endif
            </div>
        @endforeach
    </div>
@endsection
