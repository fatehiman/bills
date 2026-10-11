<?php

namespace App\BillProviders;

use App\Models\ProviderSetting;
use App\Support\BillData;
use App\Support\ProviderException;

/**
 * Base class for all bill API providers.
 *
 * HOW TO ADD A NEW PROVIDER
 *  1. Copy ZohalProvider.php in this folder to <Name>Provider.php and edit it.
 *  2. That is all. The file name must end with "Provider.php". It is found automatically.
 *  3. In the admin area (Providers page) set the provider settings and choose which bill types it serves.
 */
abstract class BillProvider
{
    /** Short unique key, lowercase letters/digits/dash. Stored in the database. Never change it later. */
    abstract public function key(): string;

    /** Name shown to people. */
    abstract public function name(): string;

    /** Bill type slugs this provider can serve (see App\Support\BillTypes). */
    abstract public function supportedTypes(): array;

    /**
     * Settings the admin must fill in, for example an API token.
     * Format: ['token' => ['label' => 'API token', 'secret' => true]]
     */
    public function settingsFields(): array
    {
        return [];
    }

    /**
     * Ask the provider for the payable bills.
     *
     * @return BillData[] Empty array means "there is no payable bill now".
     *
     * @throws ProviderException
     */
    abstract public function inquire(string $type, string $identifier): array;

    protected function setting(string $name): ?string
    {
        return ProviderSetting::read($this->key(), $name);
    }

    public function supports(string $type): bool
    {
        return in_array($type, $this->supportedTypes(), true);
    }
}
