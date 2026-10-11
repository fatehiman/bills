<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ProviderBillType;
use App\Models\ProviderSetting;
use App\Services\ProviderRegistry;
use App\Support\BillTypes;
use Illuminate\Http\Request;

class ProviderController extends Controller
{
    public function __construct(private ProviderRegistry $registry) {}

    public function index()
    {
        $map = ProviderBillType::all()->keyBy(fn ($r) => $r->bill_type.'|'.$r->provider);

        return view('admin.providers', [
            'providers' => $this->registry->all(),
            'types' => BillTypes::all(),
            'map' => $map,
        ]);
    }

    /** Save the provider settings (for example API tokens). A blank secret keeps the old value. */
    public function saveSettings(Request $request, string $provider)
    {
        $p = $this->registry->get($provider) ?? abort(404);
        foreach ($p->settingsFields() as $name => $field) {
            $value = $request->input("settings.$name");
            if (($field['secret'] ?? false) && ($value === null || $value === '')) {
                continue;
            }
            ProviderSetting::write($p->key(), $name, $value);
        }

        return back()->with('status', $p->name().' settings saved.');
    }

    /** Save which provider serves which bill type, and the order (priority) for fallback. */
    public function saveMapping(Request $request)
    {
        $input = (array) $request->input('map', []);

        foreach ($this->registry->all() as $p) {
            foreach ($p->supportedTypes() as $type) {
                if (! BillTypes::exists($type)) {
                    continue;
                }
                $row = (array) ($input[$type][$p->key()] ?? []);
                $priority = max(1, min(99, (int) ($row['priority'] ?? 10)));
                ProviderBillType::updateOrCreate(
                    ['provider' => $p->key(), 'bill_type' => $type],
                    ['priority' => $priority, 'enabled' => ! empty($row['enabled'])],
                );
            }
        }

        return back()->with('status', 'Provider mapping saved.');
    }
}
