<?php

namespace Tests\Concerns;

use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;

/**
 * Signs people in through InfoDot's redeem endpoint (faked), the way
 * EcosystemAuthController does, so the sync runs exactly as in production.
 */
trait SignsInThroughInfoDot
{
    private const REDEEM_URL = 'https://infodot.test/api/ecosystem/redeem';

    private const ACME = '11111111-1111-4111-8111-111111111111';

    private const GLOBEX = '22222222-2222-4222-8222-222222222222';

    protected function configureInfoDot(): void
    {
        config(['ecosystem.infodot' => [
            'redeem_url' => self::REDEEM_URL, 'platform' => 'agents', 'secret' => 'secret-for-tests', 'timeout' => 2, 'provision' => true,
        ]]);
    }

    /** @param  list<array<string, mixed>>  $teams */
    protected function signIn(string $email, array $teams, ?string $current = null): void
    {
        // A fresh client each time: Http::fake() keeps the FIRST stub for a URL.
        Http::swap(new Factory);
        Http::fake([self::REDEEM_URL => Http::response([
            'user' => ['id' => 1, 'name' => ucfirst(strstr($email, '@', true)), 'email' => $email, 'email_verified' => true],
            'teams' => $teams,
            'current_team_id' => 1,
            'current_team_uuid' => $current,
        ])]);

        auth()->logout();
        $this->get(route('auth.ecosystem', ['token' => 'one-time']))->assertRedirect();
    }

    /** @return array<string, mixed> */
    protected function company(string $uuid, string $name, string $role, bool $personal = false): array
    {
        return ['id' => random_int(1, 999), 'uuid' => $uuid, 'name' => $name, 'personal' => $personal, 'role' => $role];
    }
}
