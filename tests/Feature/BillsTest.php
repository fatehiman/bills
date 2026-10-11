<?php

namespace Tests\Feature;

use App\BillProviders\BillProvider;
use App\Models\Bill;
use App\Models\ProviderBillType;
use App\Models\ProviderSetting;
use App\Models\User;
use App\Services\ProviderRegistry;
use App\Support\BillData;
use App\Support\ProviderException;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BillsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $name = 'alice', bool $admin = false): User
    {
        $u = new User(['username' => $name, 'password' => 'password123']);
        $u->is_admin = $admin;
        $u->save();

        return $u;
    }

    private function setupZohal(): void
    {
        $this->seed(DatabaseSeeder::class);
        ProviderSetting::write('zohal', 'token', 'test-token');
    }

    public function test_home_is_under_construction_and_login_is_hidden(): void
    {
        $this->get('/')->assertOk()->assertSee('under construction')->assertDontSee('mainadmin');
        $this->get('/dashboard')->assertRedirect('/mainadmin');
    }

    public function test_login_and_logout(): void
    {
        $this->user('mainadmin', true);
        $this->post('/mainadmin', ['username' => 'mainadmin', 'password' => 'wrong'])->assertSessionHasErrors('username');
        $this->post('/mainadmin', ['username' => 'mainadmin', 'password' => 'password123'])->assertRedirect('/dashboard');
        $this->get('/dashboard')->assertOk()->assertSee('My bills');
        $this->post('/logout')->assertRedirect('/');
    }

    public function test_admin_pages_are_admin_only(): void
    {
        $this->actingAs($this->user('bob'))->get('/admin/providers')->assertForbidden();
        $this->actingAs($this->user('root', true))->get('/admin/providers')->assertOk()->assertSee('Zohal');
    }

    public function test_api_needs_token_and_user_has_none_by_default(): void
    {
        $u = $this->user();
        $this->assertNull($u->api_token_hash);
        $this->getJson('/api/v1/bills')->assertStatus(401)->assertJsonPath('error.code', 'unauthenticated');

        $token = $u->rotateApiToken();
        $this->withToken($token)->getJson('/api/v1/bills')->assertOk()->assertJsonPath('ok', true);

        $new = $u->rotateApiToken(); // rotate: old token stops working
        $this->withToken($token)->getJson('/api/v1/bills')->assertStatus(401);
        $this->withToken($new)->getJson('/api/v1/bills')->assertOk();
    }

    public function test_gateway_inquiry_with_zohal_mobile_two_terms(): void
    {
        $this->setupZohal();
        Http::fake(['service.zohal.io/*' => Http::response(['result' => 1, 'response_body' => [
            'data' => [
                'final_term' => ['amount' => 200000, 'bill_id' => '333', 'payment_id' => '444'],
                'mid_term' => ['amount' => 100000, 'bill_id' => '111', 'payment_id' => '222'],
            ], 'message' => 'ok', 'error_code' => null,
        ]])]);
        $token = $this->user()->rotateApiToken();

        $this->withToken($token)->postJson('/api/v1/inquiry', ['type' => 'mci', 'identifier' => '09121234567'])
            ->assertOk()
            ->assertJsonPath('data.provider', 'zohal')
            ->assertJsonPath('data.bills.0.bill_no', '333')
            ->assertJsonPath('data.bills.0.payment_no', '444')
            ->assertJsonPath('data.bills.1.term', 'mid');

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/bill/mci') && $r['mobile'] === '09121234567' && $r->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_gateway_electricity_and_no_bill(): void
    {
        $this->setupZohal();
        Http::fake(['service.zohal.io/*' => Http::response(['result' => 1, 'response_body' => [
            'data' => ['bill_id' => '9521668804120', 'payment_id' => '0', 'amount' => 0, 'full_name' => 'X'], 'message' => 'ok', 'error_code' => null,
        ]])]);
        $token = $this->user()->rotateApiToken();

        $this->withToken($token)->postJson('/api/v1/inquiry', ['type' => 'electricity', 'identifier' => '9521668804120'])
            ->assertOk()->assertJsonPath('data.has_bill', false);
    }

    public function test_gateway_rejects_invalid_identifier_without_calling_provider(): void
    {
        $this->setupZohal();
        Http::fake();
        $token = $this->user()->rotateApiToken();

        $this->withToken($token)->postJson('/api/v1/inquiry', ['type' => 'mci', 'identifier' => '123'])
            ->assertStatus(422)->assertJsonPath('error.code', 'invalid_identifier');
        Http::assertNothingSent();
    }

    public function test_provider_fallback_uses_second_provider_in_priority_order(): void
    {
        $first = new class extends BillProvider
        {
            public function key(): string { return 'first'; }
            public function name(): string { return 'First'; }
            public function supportedTypes(): array { return ['water']; }
            public function inquire(string $type, string $identifier): array { throw ProviderException::unavailable('down'); }
        };
        $second = new class extends BillProvider
        {
            public function key(): string { return 'second'; }
            public function name(): string { return 'Second'; }
            public function supportedTypes(): array { return ['water']; }
            public function inquire(string $type, string $identifier): array { return [new BillData('1234567', '7654321', 5000)]; }
        };
        $registry = new class([$first, $second]) extends ProviderRegistry
        {
            public function __construct(private array $list) {}

            public function all(): array { return collect($this->list)->keyBy->key()->all(); }

            public function get(string $key): ?BillProvider { return $this->all()[$key] ?? null; }
        };
        $this->app->instance(ProviderRegistry::class, $registry);
        ProviderBillType::create(['provider' => 'first', 'bill_type' => 'water', 'priority' => 1, 'enabled' => true]);
        ProviderBillType::create(['provider' => 'second', 'bill_type' => 'water', 'priority' => 2, 'enabled' => true]);

        $token = $this->user()->rotateApiToken();
        $this->withToken($token)->postJson('/api/v1/inquiry', ['type' => 'water', 'identifier' => '1234567'])
            ->assertOk()
            ->assertJsonPath('data.provider', 'second')
            ->assertJsonPath('data.failed_providers.0.provider', 'first');

        // Forcing a provider means no fallback.
        $this->withToken($token)->postJson('/api/v1/inquiry', ['type' => 'water', 'identifier' => '1234567', 'provider' => 'first'])
            ->assertStatus(424);

        // Disabled mapping is not used.
        ProviderBillType::where('provider', 'second')->update(['enabled' => false]);
        $this->withToken($token)->postJson('/api/v1/inquiry', ['type' => 'water', 'identifier' => '1234567'])->assertStatus(424);
    }

    public function test_saved_bills_are_isolated_between_users(): void
    {
        $this->setupZohal();
        Http::fake(['service.zohal.io/*' => Http::response(['result' => 1, 'response_body' => [
            'data' => ['bill_id' => '1234567', 'payment_id' => '7654321', 'amount' => 90000], 'message' => 'ok', 'error_code' => null,
        ]])]);

        $alice = $this->user('alice');
        $bob = $this->user('bob');
        $ta = $alice->rotateApiToken();
        $tb = $bob->rotateApiToken();

        $id = $this->withToken($ta)->postJson('/api/v1/bills', ['title' => 'Home water', 'bill_type' => 'water', 'identifier' => '1234567'])
            ->assertCreated()
            ->assertJsonPath('data.provider', 'zohal')
            ->assertJsonPath('data.unpaid_items.0.payment_no', '7654321')
            ->json('data.id');

        $this->withToken($tb)->getJson("/api/v1/bills/$id")->assertNotFound();
        $this->withToken($tb)->deleteJson("/api/v1/bills/$id")->assertNotFound();
        $this->withToken($tb)->postJson("/api/v1/bills/$id/fetch")->assertNotFound();
        $this->withToken($tb)->getJson('/api/v1/bills')->assertJsonCount(0, 'data');

        $this->actingAs($bob)->get("/bills/$id")->assertNotFound();
        $this->actingAs($bob)->delete("/bills/$id")->assertNotFound();
        $this->assertSame(1, Bill::count());

        // Same identifier is fine for another user.
        $this->withToken($tb)->postJson('/api/v1/bills', ['title' => 'Mine', 'bill_type' => 'water', 'identifier' => '1234567'])->assertCreated();
        // But not twice for the same user.
        $this->withToken($ta)->postJson('/api/v1/bills', ['title' => 'Again', 'bill_type' => 'water', 'identifier' => '1234567'])->assertStatus(422);
    }

    public function test_web_add_bill_and_scheduled_fetch(): void
    {
        $this->setupZohal();
        Http::fake(['service.zohal.io/*' => Http::response(['result' => 1, 'response_body' => [
            'data' => ['bill_id' => '1234567', 'payment_id' => '7654321', 'amount' => 90000], 'message' => 'ok', 'error_code' => null,
        ]])]);
        $u = $this->user();

        $this->actingAs($u)->post('/bills', ['title' => 'Water', 'bill_type' => 'water', 'identifier' => '1234567', 'provider' => 'zohal', 'period_days' => 7])
            ->assertRedirect();
        $bill = Bill::first();
        $this->assertSame(1, $bill->items()->count());
        $this->actingAs($u)->get('/dashboard')->assertOk()->assertSee('7654321');

        // Not due yet: nothing happens. Due: it fetches again.
        $this->artisan('bills:fetch-due')->expectsOutput('Nothing to fetch.');
        $bill->update(['next_fetch_at' => now()->subMinute()]);
        $this->artisan('bills:fetch-due')->assertSuccessful();
        $this->assertTrue($bill->refresh()->next_fetch_at->isFuture());
        $this->assertSame(1, $bill->items()->count()); // same bill is not duplicated
    }

    public function test_all_pages_render(): void
    {
        $this->setupZohal();
        Http::fake(['service.zohal.io/*' => Http::response(['result' => 1, 'response_body' => [
            'data' => ['bill_id' => '1234567', 'payment_id' => '7654321', 'amount' => 90000, 'full_name' => 'Test'], 'message' => 'ok', 'error_code' => null,
        ]])]);
        $admin = $this->user('root', true);
        $this->actingAs($admin)->post('/bills', ['title' => 'Water', 'bill_type' => 'water', 'identifier' => '۱۲۳۴۵۶۷', 'provider' => 'zohal', 'period_days' => 7]);
        $bill = Bill::firstOrFail();
        $this->assertSame('1234567', $bill->identifier); // Persian digits are converted

        foreach (['/dashboard', '/bills/create', "/bills/{$bill->id}", "/bills/{$bill->id}/edit", '/account', '/admin/providers', '/admin/users'] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
        $this->actingAs($admin)->post("/bills/{$bill->id}/items/{$bill->items()->first()->id}/paid")->assertRedirect();
        $this->assertNotNull($bill->items()->first()->paid_at);
        $this->actingAs($admin)->post('/account/token')->assertRedirect('/account')->assertSessionHas('new_token');
    }

    public function test_admin_mapping_can_be_saved(): void
    {
        $this->setupZohal();
        $admin = $this->user('root', true);
        $this->actingAs($admin)->post('/admin/providers/mapping', ['map' => ['water' => ['zohal' => ['enabled' => '1', 'priority' => 3]]]])->assertRedirect();
        $this->assertTrue(ProviderBillType::where('bill_type', 'water')->first()->enabled);
        $this->assertFalse(ProviderBillType::where('bill_type', 'gas')->first()->enabled);
        $this->assertSame(3, ProviderBillType::where('bill_type', 'water')->first()->priority);
    }
}
