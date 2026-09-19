<?php

namespace Tests\Unit\Services\Social\Publishers;

use App\Models\Organization;
use App\Models\SocialAccount;
use App\Models\SocialPage;
use App\Models\SocialPost;
use App\Services\Social\Publishers\XPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class XPublisherTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(string $accessToken = 'x-access-token-789'): SocialPost
    {
        $organization = Organization::factory()->create();

        $account = SocialAccount::factory()->create([
            'organization_id' => $organization->id,
            'platform' => 'twitter',
            'access_token' => $accessToken,
        ]);

        $page = SocialPage::create([
            'uuid' => Str::uuid(),
            'organization_id' => $organization->id,
            'social_account_id' => $account->id,
            'platform_page_id' => 'irrelevant_for_x',
            'name' => 'Test X Account',
            'is_active' => true,
        ]);

        return SocialPost::create([
            'uuid' => Str::uuid(),
            'organization_id' => $organization->id,
            'social_page_id' => $page->id,
            'content' => 'Shipping something new today.',
            'status' => 'approved',
            'approval_status' => 'approved',
        ]);
    }

    public function test_publish_sends_the_expected_request_and_extracts_the_tweet_id(): void
    {
        Http::fake([
            'api.twitter.com/*' => Http::response(['data' => ['id' => '1699999999999999999', 'text' => 'Shipping something new today.']], 201),
        ]);

        $post = $this->makePost('x-access-token-789');

        $result = app(XPublisher::class)->publish($post);

        $this->assertSame('1699999999999999999', $result['platform_post_id']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://api.twitter.com/2/tweets'
                && $request->hasHeader('Authorization', 'Bearer x-access-token-789')
                && $body['text'] === 'Shipping something new today.';
        });
    }

    public function test_publish_throws_and_does_not_fabricate_an_id_on_a_non_2xx_response(): void
    {
        Http::fake([
            'api.twitter.com/*' => Http::response(['title' => 'Unauthorized'], 401),
        ]);

        $post = $this->makePost();

        $this->expectException(RuntimeException::class);

        app(XPublisher::class)->publish($post);
    }
}
