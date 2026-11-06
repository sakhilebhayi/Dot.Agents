<?php

namespace Tests\Feature\Security;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The forgery check is skipped under test, so these switch the environment to production to see what a provider sees. */
class WebhookForgeryTest extends TestCase
{
    use RefreshDatabase;

    private function asProduction(callable $request)
    {
        $this->app['env'] = 'production';

        try {
            return $request();
        } finally {
            $this->app['env'] = 'testing';
        }
    }

    public function test_a_signed_facebook_webhook_reaches_its_controller(): void
    {
        config(['services.facebook.client_secret' => 'app-secret']);
        $body = (string) json_encode(['entry' => []]);

        $response = $this->asProduction(fn () => $this->call('POST', '/social/webhooks/facebook', [], [], [], [
            'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'app-secret'),
            'CONTENT_TYPE' => 'application/json',
        ], $body));

        $this->assertNotSame(419, $response->status());
    }

    public function test_a_stripe_webhook_reaches_its_controller(): void
    {
        $response = $this->asProduction(fn () => $this->call('POST', '/webhooks/stripe', [], [], [], [
            'HTTP_STRIPE_SIGNATURE' => 't=1,v1=x',
            'CONTENT_TYPE' => 'application/json',
        ], '{}'));

        $this->assertNotSame(419, $response->status());
    }

    public function test_an_ordinary_form_post_still_needs_a_token(): void
    {
        $response = $this->asProduction(fn () => $this->call('POST', '/login', ['email' => 'a@b.test', 'password' => 'x']));

        $this->assertSame(419, $response->status());
    }
}
