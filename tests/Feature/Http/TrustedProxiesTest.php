<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\Activity;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Env;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

final class TrustedProxiesTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        unset($_SERVER['LARAVEL_CLOUD'], $_ENV['LARAVEL_CLOUD']);
        Env::getRepository()->clear('TRUSTED_PROXIES');

        parent::tearDown();
    }

    /** @return array<mixed> */
    private function parsedProxies(): array
    {
        /** @var array{proxies: array<mixed>} $config */
        $config = require config_path('trustedproxy.php');

        return $config['proxies'];
    }

    private function cloud(): void
    {
        $_SERVER['LARAVEL_CLOUD'] = '1';
        $_ENV['LARAVEL_CLOUD'] = '1';
    }

    private function member(): User
    {
        $user = User::factory()->create();
        $tenant = Tenant::factory()->forOwner($user)->create();
        TenantMembership::create(['user_id' => $user->id, 'tenant_id' => $tenant->id, 'is_active' => true, 'joined_at' => now()]);

        return $user;
    }

    /** @param array<string, string> $headers */
    private function viaProxy(string $remote, array $headers = []): static
    {
        $server = ['REMOTE_ADDR' => $remote];

        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        return $this->withServerVariables($server);
    }

    public function test_unset_proxies_trust_nobody_even_on_cloud(): void
    {
        $this->cloud();
        $user = $this->member();

        $this->viaProxy('10.0.0.5', ['X-Forwarded-For' => '6.6.6.6'])
            ->post('/login', ['email' => $user->email, 'password' => 'wrong']);

        $row = Activity::query()->where('description', 'login_failed')->sole();
        $this->assertSame('10.0.0.5', data_get($row->properties, 'ip'));
    }

    public function test_config_default_is_an_empty_list(): void
    {
        $this->assertSame([], config('trustedproxy.proxies'));
    }

    public function test_rotating_spoofed_addresses_do_not_escape_the_limit(): void
    {
        $this->cloud();
        config(['trustedproxy.proxies' => ['10.0.0.0/8']]);
        $user = $this->member();

        for ($i = 1; $i <= 10; $i++) {
            $this->viaProxy('10.0.0.5', ['X-Forwarded-For' => "7.7.7.{$i}, 8.8.4.4"])
                ->post('/login', ['email' => "user{$i}@example.com", 'password' => 'wrong']);
        }

        $this->viaProxy('10.0.0.5', ['X-Forwarded-For' => '7.7.7.99, 8.8.4.4'])
            ->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, Activity::query()->where('description', 'login_lockout')->count());
    }

    public function test_unset_proxies_on_cloud_do_not_let_a_rotating_leftmost_address_escape_the_limit(): void
    {
        $this->cloud();
        $user = $this->member();

        for ($i = 1; $i <= 10; $i++) {
            $this->viaProxy('10.0.0.5', ['X-Forwarded-For' => "7.7.7.{$i}"])
                ->post('/login', ['email' => "user{$i}@example.com", 'password' => 'wrong']);
        }

        $this->viaProxy('10.0.0.5', ['X-Forwarded-For' => '7.7.7.99'])
            ->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');

        $this->assertSame(1, Activity::query()->where('description', 'login_lockout')->count());
    }

    public function test_env_value_is_split_trimmed_and_stripped_of_empties(): void
    {
        Env::getRepository()->set('TRUSTED_PROXIES', ' 10.0.0.0/8 , ,PRIVATE_SUBNETS,,172.16.0.0/12 ');

        $this->assertSame(['10.0.0.0/8', 'PRIVATE_SUBNETS', '172.16.0.0/12'], $this->parsedProxies());
    }

    public function test_blank_env_value_yields_an_empty_list(): void
    {
        Env::getRepository()->set('TRUSTED_PROXIES', ' , ');

        $this->assertSame([], $this->parsedProxies());
    }

    public function test_separate_real_clients_behind_one_proxy_get_separate_buckets(): void
    {
        config(['trustedproxy.proxies' => ['10.0.0.0/8']]);
        $user = $this->member();

        for ($i = 1; $i <= 10; $i++) {
            $this->viaProxy('10.0.0.5', ['X-Forwarded-For' => '8.8.4.4'])
                ->post('/login', ['email' => "user{$i}@example.com", 'password' => 'wrong']);
        }

        $this->viaProxy('10.0.0.5', ['X-Forwarded-For' => '8.8.8.8'])
            ->post('/login', ['email' => $user->email, 'password' => 'wrong']);

        $this->assertSame(0, Activity::query()->where('description', 'login_lockout')->count());
        $ips = Activity::query()->where('description', 'login_failed')->get()
            ->map(fn (Activity $activity): mixed => data_get($activity->properties, 'ip'));

        $this->assertContains('8.8.8.8', $ips->all());
    }

    public function test_forwarded_for_from_an_untrusted_peer_is_ignored(): void
    {
        config(['trustedproxy.proxies' => ['10.0.0.0/8']]);
        $user = $this->member();

        $this->viaProxy('9.9.9.9', ['X-Forwarded-For' => '6.6.6.6'])
            ->post('/login', ['email' => $user->email, 'password' => 'wrong']);

        $row = Activity::query()->where('description', 'login_failed')->sole();
        $this->assertSame('9.9.9.9', data_get($row->properties, 'ip'));
    }

    public function test_forwarded_host_cannot_poison_the_reset_link(): void
    {
        $this->cloud();
        config(['trustedproxy.proxies' => ['10.0.0.0/8']]);
        Notification::fake();
        $user = $this->member();

        $this->viaProxy('10.0.0.5', [
            'X-Forwarded-For' => '8.8.4.4',
            'X-Forwarded-Host' => 'evil.example',
            'X-Forwarded-Proto' => 'https',
        ])->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $notification) use ($user): bool {
            $mail = $notification->toMail($user);
            $url = $mail instanceof MailMessage ? (string) $mail->actionUrl : '';

            $this->assertStringNotContainsString('evil.example', $url);
            $this->assertStringStartsWith('https://', $url);

            return true;
        });
    }

    public function test_header_logging_writes_the_resolved_ip_when_enabled(): void
    {
        config(['trustedproxy.proxies' => ['10.0.0.0/8'], 'trustedproxy.log_headers' => true]);
        Log::shouldReceive('info')
            ->withArgs(fn (string $message, array $context = []): bool => $message === 'proxy.headers'
                && $context['resolved_ip'] === '8.8.4.4'
                && $context['remote_addr'] === '10.0.0.5')
            ->once();

        $this->viaProxy('10.0.0.5', ['X-Forwarded-For' => '8.8.4.4'])->get('/up');
    }

    public function test_header_logging_is_silent_when_disabled(): void
    {
        config(['trustedproxy.log_headers' => false]);
        Log::shouldReceive('info')->never();

        $this->get('/up');
    }
}
