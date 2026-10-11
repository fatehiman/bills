<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\BillGateway;
use App\Services\ProviderRegistry;
use App\Support\BillTypes;
use App\Support\GatewayException;
use App\Support\ProviderException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** The API gateway: ask for the payable bills of any bill, without saving anything. */
class GatewayController extends Controller
{
    public function __construct(private BillGateway $gateway, private ProviderRegistry $registry) {}

    public function index(): JsonResponse
    {
        return response()->json(['ok' => true, 'data' => [
            'name' => 'Bills API',
            'version' => 'v1',
            'docs' => 'https://github.com/fatehiman/bills/blob/main/reference-docs/api-usage.md',
        ]]);
    }

    public function types(): JsonResponse
    {
        $out = [];
        foreach (BillTypes::all() as $slug => $t) {
            $out[] = [
                'type' => $slug,
                'label' => $t['label'],
                'identifier_label' => $t['identifier_label'],
                'providers' => array_map(fn ($p) => $p->key(), $this->registry->forType($slug)),
            ];
        }

        return response()->json(['ok' => true, 'data' => $out]);
    }

    public function inquire(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', 'string'],
            'identifier' => ['required', 'string', 'max:60'],
            'provider' => ['nullable', 'string', 'max:40'],
        ]);
        $identifier = BillTypes::normalizeIdentifier($data['identifier']);

        try {
            $r = $this->gateway->inquire($data['type'], $identifier, $data['provider'] ?? null);
        } catch (ProviderException $e) {
            return $this->error('invalid_identifier', $e->getMessage(), 422);
        } catch (GatewayException $e) {
            return $this->error($e->errorCode, $e->getMessage(), $e->status, $e->attempts ? ['attempts' => $e->attempts] : []);
        }

        return response()->json(['ok' => true, 'data' => [
            'type' => $data['type'],
            'identifier' => $identifier,
            'provider' => $r['provider'],
            'has_bill' => count($r['bills']) > 0,
            'bills' => array_map(fn ($b) => $b->toArray(), $r['bills']),
            'failed_providers' => $r['attempts'],
        ]]);
    }

    private function error(string $code, string $message, int $status, array $details = []): JsonResponse
    {
        return response()->json(['ok' => false, 'error' => array_filter(['code' => $code, 'message' => $message, 'details' => $details ?: null])], $status);
    }
}
