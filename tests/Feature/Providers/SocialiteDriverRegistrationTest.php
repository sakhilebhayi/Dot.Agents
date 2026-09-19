<?php

namespace Tests\Feature\Providers;

use Illuminate\Support\Facades\Config;
use Laravel\Socialite\Contracts\Factory as Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SocialiteDriverRegistrationTest extends TestCase
{
    #[Test]
    public function test_instagram_driver_resolves_without_error(): void
    {
        Config::set('services.instagram.client_id', 'fake-client-id');
        Config::set('services.instagram.client_secret', 'fake-client-secret');
        Config::set('services.instagram.redirect', '/social/auth/instagram/callback');

        $driver = app(Socialite::class)->driver('instagram');

        $this->assertInstanceOf(AbstractProvider::class, $driver);
    }

    #[Test]
    public function test_tiktok_driver_resolves_without_error(): void
    {
        Config::set('services.tiktok.client_id', 'fake-client-id');
        Config::set('services.tiktok.client_secret', 'fake-client-secret');
        Config::set('services.tiktok.redirect', '/social/auth/tiktok/callback');

        $driver = app(Socialite::class)->driver('tiktok');

        $this->assertInstanceOf(AbstractProvider::class, $driver);
    }

    #[Test]
    public function test_unregistered_driver_still_throws_invalid_argument_exception(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        app(Socialite::class)->driver('not-a-real-platform');
    }
}
