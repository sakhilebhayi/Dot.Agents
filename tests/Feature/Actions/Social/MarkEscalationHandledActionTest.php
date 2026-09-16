<?php

namespace Tests\Feature\Actions\Social;

use App\Actions\Social\MarkEscalationHandledAction;
use App\DTOs\Social\MarkEscalationHandledData;
use App\Models\Organization;
use App\Models\SocialSentimentScore;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MarkEscalationHandledActionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id]);
        $this->user->organizations()->attach($this->organization->id, ['role' => 'owner']);
        $this->actingAs($this->user);
    }

    #[Test]
    public function test_marks_escalation_as_handled(): void
    {
        $score = SocialSentimentScore::create([
            'organization_id' => $this->organization->id,
            'platform' => 'facebook',
            'subject_type' => 'account',
            'sentiment' => 'negative',
            'score' => -0.8,
            'confidence' => 0.9,
            'requires_escalation' => true,
            'escalation_handled' => false,
            'scored_at' => now(),
        ]);

        $data = MarkEscalationHandledData::from($this->organization->id, $score->id);
        app(MarkEscalationHandledAction::class)->execute($data);

        $this->assertTrue((bool) $score->fresh()->escalation_handled);
    }
}
