<?php

namespace Tests\Unit\Services\Social\Publishers;

use App\Models\Organization;
use App\Models\SocialAccount;
use App\Models\SocialPage;
use App\Models\SocialPost;
use App\Services\Social\Publishers\LinkedInPublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;
use Tests\TestCase;

class LinkedInPublisherTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(string $accessToken = 'li-access-token-456'): SocialPost
    {
        $organization = Organization::factory()->create();

        $account = SocialAccount::factory()->create([
            'organization_id' => $organization->id,
            'platform' => 'linkedin',
            'access_token' => $accessToken,
        ]);

        $page = SocialPage::create([
            'uuid' => Str::uuid(),
            'organization_id' => $organization->id,
            'social_account_id' => $account->id,
            'platform_page_id' => '5551234',
            'name' => 'Test LinkedIn Page',
            'is_active' => true,
        ]);

        return SocialPost::create([
            'uuid' => Str::uuid(),
            'organization_id' => $organization->id,
            'social_page_id' => $page->id,
            'content' => 'Announcing our new product line.',
            'status' => 'approved',
            'approval_status' => 'approved',
        ]);
    }

    public function test_publish_sends_the_expected_request_and_extracts_the_id_from_the_body(): void
    {
        Http::fake([
            'api.linkedin.com/*' => Http::response(['id' => 'urn:li:share:6899123456789'], 201),
        ]);

        $post = $this->makePost('li-access-token-456');

        $result = app(LinkedInPublisher::class)->publish($post);

        $this->assertSame('urn:li:share:6899123456789', $result['platform_post_id']);

        Http::assertSent(function ($request) {
            $body = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://api.linkedin.com/v2/ugcPosts'
                && $request->hasHeader('Authorization', 'Bearer li-access-token-456')
                && $request->hasHeader('X-Restli-Protocol-Version', '2.0.0')
                && $body['author'] === 'urn:li:organization:5551234'
                && $body['lifecycleState'] === 'PUBLISHED'
                && $body['specificContent']['com.linkedin.ugc.ShareContent']['shareCommentary']['text'] === 'Announcing our new product line.'
                && $body['visibility']['com.linkedin.ugc.MemberNetworkVisibility'] === 'PUBLIC';
        });
    }

    public function test_publish_falls_back_to_the_restli_id_header_when_the_body_has_no_id(): void
    {
        Http::fake([
            'api.linkedin.com/*' => Http::response([], 201, ['x-restli-id' => 'urn:li:share:7001112223334']),
        ]);

        $post = $this->makePost();

        $result = app(LinkedInPublisher::class)->publish($post);

        $this->assertSame('urn:li:share:7001112223334', $result['platform_post_id']);
    }

    public function test_publish_throws_and_does_not_fabricate_an_id_on_a_non_2xx_response(): void
    {
        Http::fake([
            'api.linkedin.com/*' => Http::response(['message' => 'Unauthorized'], 401),
        ]);

        $post = $this->makePost();

        $this->expectException(RuntimeException::class);

        app(LinkedInPublisher::class)->publish($post);
    }
}
