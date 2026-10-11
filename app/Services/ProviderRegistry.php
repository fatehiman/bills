<?php

namespace App\Services;

use App\BillProviders\BillProvider;
use App\Models\ProviderBillType;
use ReflectionClass;

/**
 * Finds every provider class in app/BillProviders (files named *Provider.php).
 * Adding a provider = adding one PHP file there. Nothing else to register.
 */
class ProviderRegistry
{
    /** @var array<string, BillProvider>|null */
    private ?array $providers = null;

    /** @return array<string, BillProvider> keyed by provider key */
    public function all(): array
    {
        if ($this->providers === null) {
            $this->providers = [];
            foreach (glob(app_path('BillProviders/*Provider.php')) ?: [] as $file) {
                $class = 'App\\BillProviders\\'.basename($file, '.php');
                if (! class_exists($class) || ! is_subclass_of($class, BillProvider::class)) {
                    continue;
                }
                if ((new ReflectionClass($class))->isAbstract()) {
                    continue;
                }
                $provider = new $class;
                $this->providers[$provider->key()] = $provider;
            }
            ksort($this->providers);
        }

        return $this->providers;
    }

    public function get(string $key): ?BillProvider
    {
        return $this->all()[$key] ?? null;
    }

    /**
     * Providers that the admin enabled for a bill type, best first.
     *
     * @return BillProvider[]
     */
    public function forType(string $type): array
    {
        $rows = ProviderBillType::where('bill_type', $type)->where('enabled', true)
            ->orderBy('priority')->orderBy('id')->get();

        $list = [];
        foreach ($rows as $row) {
            $provider = $this->get($row->provider);
            if ($provider && $provider->supports($type)) {
                $list[] = $provider;
            }
        }

        return $list;
    }
}
