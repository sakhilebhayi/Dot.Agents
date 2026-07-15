<?php

namespace Tests\Feature\Livewire;

use App\Actions\Organizations\SaveKnowledgeBaseAction;
use App\Livewire\Organizations\KnowledgeManager;
use App\Models\KnowledgeBase;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KnowledgeManagerTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create(['owner_id' => $this->user->id]);
        session(['current_organization_id' => $this->organization->id]);
        Gate::before(fn () => true);
    }

    #[Test]
    public function it_renders_successfully(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(KnowledgeManager::class)
            ->assertStatus(200);
    }

    #[Test]
    public function it_lists_existing_knowledge_bases(): void
    {
        $this->actingAs($this->user);

        KnowledgeBase::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test KB',
            'slug' => 'test-kb-abc123',
            'type' => 'general',
            'access_level' => 'internal',
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        $component = Livewire::actingAs($this->user)
            ->test(KnowledgeManager::class);

        $bases = $component->get('knowledgeBases');
        $this->assertCount(1, $bases);
        $this->assertEquals('Test KB', $bases->first()->name);
    }

    #[Test]
    public function it_selects_a_knowledge_base(): void
    {
        $this->actingAs($this->user);

        $kb = KnowledgeBase::create([
            'organization_id' => $this->organization->id,
            'name' => 'My KB',
            'slug' => 'my-kb-def456',
            'type' => 'general',
            'access_level' => 'internal',
            'is_active' => true,
            'created_by' => $this->user->id,
        ]);

        Livewire::actingAs($this->user)
            ->test(KnowledgeManager::class)
            ->call('selectBase', $kb->id)
            ->assertSet('activeBaseId', $kb->id);
    }

    #[Test]
    public function it_saves_a_new_knowledge_base(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(KnowledgeManager::class)
            ->set('baseName', 'Brand New KB')
            ->set('baseDescription', 'A test knowledge base')
            ->call('saveBase', app(SaveKnowledgeBaseAction::class))
            ->assertHasNoErrors();

        $this->assertDatabaseHas('knowledge_bases', [
            'organization_id' => $this->organization->id,
            'name' => 'Brand New KB',
        ]);
    }

    #[Test]
    public function it_requires_base_name_to_save(): void
    {
        $this->actingAs($this->user);

        Livewire::actingAs($this->user)
            ->test(KnowledgeManager::class)
            ->set('baseName', '')
            ->call('saveBase', app(SaveKnowledgeBaseAction::class))
            ->assertHasErrors(['baseName']);
    }
}
