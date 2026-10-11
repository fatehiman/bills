<?php

namespace App\Services;

use App\Support\BillData;
use App\Support\BillTypes;
use App\Support\GatewayException;
use App\Support\ProviderException;
use Illuminate\Support\Facades\Log;

/**
 * Provider independent inquiry. The rest of the app only talks to this class.
 */
class BillGateway
{
    public function __construct(private ProviderRegistry $registry) {}

    /**
     * @param  string|null  $providerKey  Force one provider (no fallback). Null = use the admin mapping with fallback.
     * @return array{provider: string, bills: BillData[], attempts: array}
     *
     * @throws ProviderException with kind INVALID_INPUT when the identifier is wrong
     * @throws GatewayException when no provider could answer
     */
    public function inquire(string $type, string $identifier, ?string $providerKey = null): array
    {
        if (! BillTypes::exists($type)) {
            throw new GatewayException("Unknown bill type '$type'.", 422);
        }
        if (! BillTypes::validIdentifier($type, $identifier)) {
            throw ProviderException::invalidInput('The '.BillTypes::get($type)['identifier_label'].' is not valid.');
        }

        if ($providerKey !== null && $providerKey !== '') {
            $provider = $this->registry->get($providerKey);
            if (! $provider) {
                throw new GatewayException("Unknown provider '$providerKey'.", 422);
            }
            if (! $provider->supports($type)) {
                throw new GatewayException("Provider '$providerKey' does not support bill type '$type'.", 422);
            }
            $chain = [$provider];
        } else {
            $chain = $this->registry->forType($type);
            if (! $chain) {
                throw new GatewayException("No provider is configured for bill type '$type'.", 503);
            }
        }

        $attempts = [];
        foreach ($chain as $provider) {
            try {
                $bills = $provider->inquire($type, $identifier);

                return ['provider' => $provider->key(), 'bills' => $bills, 'attempts' => $attempts];
            } catch (ProviderException $e) {
                if ($e->kind === ProviderException::INVALID_INPUT) {
                    throw $e; // other providers would say the same
                }
                $attempts[] = ['provider' => $provider->key(), 'error' => $e->getMessage()];
                Log::warning('Provider failed, trying next', ['provider' => $provider->key(), 'type' => $type, 'error' => $e->getMessage()]);
            }
        }

        throw new GatewayException('No provider could answer. Please try again later.', 502, $attempts);
    }
}
