<?php

namespace Tests\Feature\Jobs;

use App\Jobs\PublishSocialPostJob;
use App\Models\Organization;
use App\Models\SocialAccount;
use App\Models\SocialPage;
use App\Models\SocialPost;
use App\Services\Governance\AuditService;
use App\Services\Social\SocialPublishingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class PublishSocialPostJobTest extends TestCase
{
    use RefreshDatabase;

    private function makePost(): SocialPost
    {
        $organization = Organization::factory()->create();

        $account = SocialAccount::factory()->create([
            'organization_id' => $organization->id,
            'platform' => 'facebook',
            'access_token' => 'fb-access-token-abc',
        ]);

        $page = SocialPage::create([
            'uuid' => Str::uuid(),
            'organization_id' => $organization->id,
            'social_account_id' => $account->id,
            'platform_page_id' => '998877665544',
            'name' => 'Test Page',
            'is_active' => true,
        ]);

        return SocialPost::create([
            'uuid' => Str::uuid(),
            'organization_id' => $organization->id,
            'social_page_id' => $page->id,
            'content' => 'Job-driven publish test.',
            'status' => 'scheduled',
            'approval_status' => 'approved',
        ]);
    }

    public function test_handle_publishes_the_post_and_persists_the_real_platform_post_id_on_success(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['id' => '998877665544_112233'], 200),
        ]);

        $post = $this->makePost();

        (new PublishSocialPostJob($post))->handle(
            app(SocialPublishingService::class),
            app(AuditService::class),
        );

        $post->refresh();

        $this->assertSame('published', $post->status);
        $this->assertSame('998877665544_112233', $post->platform_post_id);
        $this->assertSame(['id' => '998877665544_112233'], $post->platform_response);
        $this->assertNotNull($post->published_at);
    }

    public function test_handle_marks_the_post_failed_and_rethrows_when_the_platform_call_fails(): void
    {
        Http::fake([
            'graph.facebook.com/*' => Http::response(['error' => ['message' => 'Invalid token']], 401),
        ]);

        $post = $this->makePost();

        $job = new PublishSocialPostJob($post);

        try {
            $job->handle(app(SocialPublishingService::class), app(AuditService::class));
            $this->fail('Expected the job to rethrow the publisher exception.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Facebook publish failed', $e->getMessage());
        }

        $post->refresh();

        $this->assertSame('failed', $post->status);
        $this->assertNull($post->platform_post_id);
    }

    public function test_handle_does_not_call_the_platform_when_the_post_is_not_approved(): void
    {
        Http::fake();

        $post = $this->makePost();
        $post->update(['approval_status' => 'pending']);

        (new PublishSocialPostJob($post))->handle(
            app(SocialPublishingService::class),
            app(AuditService::class),
        );

        $post->refresh();

        $this->assertNotEquals('published', $post->status);
        Http::assertNothingSent();
    }
}
