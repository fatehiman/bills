<?php

namespace App\Services;

use App\Models\Bill;
use App\Models\User;
use App\Support\BillTypes;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** Shared logic for creating and updating saved bills (used by the web pages and by the API). */
class BillManager
{
    public const PERIODS = [0 => 'Manual only', 1 => 'Every day', 3 => 'Every 3 days', 7 => 'Every week', 15 => 'Every 15 days', 30 => 'Every month', 60 => 'Every 2 months'];

    public function __construct(private ProviderRegistry $registry) {}

    /** Provider keys that the admin enabled for each bill type: ['water' => ['zohal' => 'Zohal'], ...] */
    public function providerOptions(): array
    {
        $out = [];
        foreach (BillTypes::slugs() as $type) {
            foreach ($this->registry->forType($type) as $p) {
                $out[$type][$p->key()] = $p->name();
            }
        }

        return $out;
    }

    /**
     * @throws ValidationException
     */
    public function create(User $user, array $input): Bill
    {
        $input['identifier'] = BillTypes::normalizeIdentifier((string) ($input['identifier'] ?? ''));

        $v = Validator::make($input, [
            'title' => ['required', 'string', 'max:120'],
            'bill_type' => ['required', Rule::in(BillTypes::slugs())],
            'identifier' => ['required', 'string', 'max:60'],
            'provider' => ['nullable', 'string', 'max:40'],
            'period_days' => ['nullable', 'integer', Rule::in(array_keys(self::PERIODS))],
            'include_mid_term' => ['nullable', 'boolean'],
        ]);
        $v->after(function ($v) use ($user, $input) {
            if ($v->errors()->isNotEmpty()) {
                return;
            }
            $type = $input['bill_type'];
            if (! BillTypes::validIdentifier($type, $input['identifier'])) {
                $v->errors()->add('identifier', 'The '.strtolower(BillTypes::get($type)['identifier_label']).' is not valid.');
            }
            $keys = array_map(fn ($p) => $p->key(), $this->registry->forType($type));
            if (! $keys) {
                $v->errors()->add('bill_type', 'No provider is available for this bill type yet.');
            } elseif (! empty($input['provider']) && ! in_array($input['provider'], $keys, true)) {
                $v->errors()->add('provider', 'This provider does not serve this bill type.');
            }
            if ($user->bills()->where('bill_type', $type)->where('identifier', $input['identifier'])->exists()) {
                $v->errors()->add('identifier', 'You already have this bill.');
            }
        });
        $data = $v->validate();

        $provider = ($data['provider'] ?? null) ?: $this->registry->forType($data['bill_type'])[0]->key();

        $bill = new Bill([
            'title' => $data['title'],
            'bill_type' => $data['bill_type'],
            'identifier' => $data['identifier'],
            'provider' => $provider,
            'period_days' => (int) ($data['period_days'] ?? 30),
            'include_mid_term' => (bool) ($data['include_mid_term'] ?? false),
        ]);
        $bill->user_id = $user->id;
        $bill->scheduleNext();
        $bill->save();

        return $bill;
    }

    /**
     * Update the title, provider, period and mid term option of a bill. Type and identifier cannot change.
     *
     * @throws ValidationException
     */
    public function update(Bill $bill, array $input): Bill
    {
        $v = Validator::make($input, [
            'title' => ['sometimes', 'required', 'string', 'max:120'],
            'provider' => ['sometimes', 'required', 'string', 'max:40'],
            'period_days' => ['sometimes', 'required', 'integer', Rule::in(array_keys(self::PERIODS))],
            'include_mid_term' => ['sometimes', 'boolean'],
        ]);
        $v->after(function ($v) use ($bill, $input) {
            if (! empty($input['provider'])) {
                $keys = array_map(fn ($p) => $p->key(), $this->registry->forType($bill->bill_type));
                if (! in_array($input['provider'], $keys, true) && $input['provider'] !== $bill->provider) {
                    $v->errors()->add('provider', 'This provider does not serve this bill type.');
                }
            }
        });
        $data = $v->validate();

        $bill->fill($data);
        if (array_key_exists('period_days', $data)) {
            $bill->scheduleNext();
        }
        $bill->save();

        return $bill;
    }
}
