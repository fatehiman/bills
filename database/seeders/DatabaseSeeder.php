<?php

namespace Database\Seeders;

use App\Models\ProviderBillType;
use App\Services\ProviderRegistry;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Safe to run many times. A provider that has no mapping rows yet gets all its bill types enabled.
     * The admin can change this on the Providers page. No users or passwords are created here.
     */
    public function run(): void
    {
        foreach (app(ProviderRegistry::class)->all() as $provider) {
            if (ProviderBillType::where('provider', $provider->key())->exists()) {
                continue;
            }
            foreach ($provider->supportedTypes() as $type) {
                ProviderBillType::create([
                    'provider' => $provider->key(),
                    'bill_type' => $type,
                    'priority' => 10,
                    'enabled' => true,
                ]);
            }
        }
    }
}
