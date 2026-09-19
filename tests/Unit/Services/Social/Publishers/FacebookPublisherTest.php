<?php

namespace Tests\Unit\Services\Social\Publishers;

use App\Models\Organization;
use App\Models\SocialAccount;
use App\Models\SocialPage;
use App\Models\SocialPost;
use App\Services\Social\Publishers\FacebookPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class FacebookPublisherTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(string $accessToken = 'fb-access-token-123'): SocialPost
    {
        $organization = Organization::factory()->create();

        $account = SocialAccount::factory()->create([
            'organization_id' => $organization->id,
            'platform' => 'facebook',
            'access_token' => $accessToken,
        ]);

        $page = SocialPage::create([
            'uuid' => Str::uuid(),
            'organization_id' => $organization->id,
            'social_account_id' => $account->id,
            'platform_page_id' => '112233445566',
            'name' => 'Test Facebook Page',
            'is_active' => true,
        ]);

        return SocialPost::create([
            'uuid' => Str::uuid(),
            'organization_id' => $organization->id,
            'social_page_id' => $page->id,
            'content' => 'Hello from the automated publishing pipeline!',
            'status' => 'approved',
            'approval_status' => 'approved',
        ]);
    }

    public function test_publish_sends_the_expected_request_and_extracts_the_platform_post_id(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['id' => '112233445566_998877'], 200),
        ]);

        $post = $this->makePost('fb-access-token-123');

        $result = app(FacebookPublisher::class)->publish($post);

        $this->assertSame('112233445566_998877', $result['platform_post_id']);
        $this->assertSame(['id' => '112233445566_998877'], $result['raw_response']);

        Http::assertSent(function ($request) {
            return $request->method() === 'POST'
                && $request->url() === 'https://graph.facebook.com/v21.0/112233445566/feed'
                && $request['message'] === 'Hello from the automated publishing pipeline!'
                && $request['access_token'] === 'fb-access-token-123';
        });
    }

    public function test_publish_throws_and_does_not_fabricate_an_id_on_a_non_2xx_response(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid OAuth access token.']], 401),
        ]);

        $post = $this->makePost();

        $this->expectException(RuntimeException::class);

        app(FacebookPublisher::class)->publish($post);
    }
}
