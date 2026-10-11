<?php

namespace App\Console\Commands;

use App\Models\ProviderSetting;
use App\Services\ProviderRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bills:provider-set {provider} {name} {value? : Value (asked if empty, use - to read it from stdin)}')]
#[Description('Set a provider setting, for example: bills:provider-set zohal token <value>')]
class SetProviderSetting extends Command
{
    public function handle(ProviderRegistry $registry): int
    {
        $provider = $registry->get((string) $this->argument('provider'));
        if (! $provider) {
            $this->error('Unknown provider. Known: '.implode(', ', array_keys($registry->all())));

            return self::FAILURE;
        }
        $name = (string) $this->argument('name');
        if (! array_key_exists($name, $provider->settingsFields())) {
            $this->error('Unknown setting. Known: '.implode(', ', array_keys($provider->settingsFields())));

            return self::FAILURE;
        }

        $value = $this->argument('value') ?? $this->secret('Value');
        if ($value === '-') {
            $value = trim((string) fgets(STDIN));
        }
        ProviderSetting::write($provider->key(), $name, $value);
        $this->info('Saved.');

        return self::SUCCESS;
    }
}
