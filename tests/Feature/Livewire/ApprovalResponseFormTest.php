<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Forms\ApprovalResponseForm;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ApprovalResponseForm is a Livewire Form object (extends Livewire\Form).
 * It cannot be standalone-instantiated. These tests verify its logic
 * by invoking methods on a freshly resolved instance via the container.
 */
class ApprovalResponseFormTest extends TestCase
{
    use RefreshDatabase;

    private function makeForm(): ApprovalResponseForm
    {
        // Livewire\Form requires ComponentRegistry + propertyName injected by the framework.
        // We can resolve it via the container by binding a minimal stub registry.
        /** @var \Livewire\ComponentRegistry $registry */
        $registry = $this->createMock(\Livewire\ComponentRegistry::class);

        return new ApprovalResponseForm($registry, 'responseForm');
    }

    #[Test]
    public function it_defaults_to_empty_decision(): void
    {
        $form = $this->makeForm();

        $this->assertSame('', $form->decision);
        $this->assertNull($form->notes);
        $this->assertNull($form->conditions);
        $this->assertNull($form->rejection_reason);
    }

    #[Test]
    public function to_array_returns_all_fields(): void
    {
        $form = $this->makeForm();
        $form->decision = 'approved';
        $form->notes = 'Looks good';

        $array = $form->toArray();

        $this->assertArrayHasKey('decision', $array);
        $this->assertArrayHasKey('notes', $array);
        $this->assertArrayHasKey('conditions', $array);
        $this->assertArrayHasKey('rejection_reason', $array);
        $this->assertSame('approved', $array['decision']);
        $this->assertSame('Looks good', $array['notes']);
    }

    #[Test]
    public function is_approval_returns_true_when_approved(): void
    {
        $form = $this->makeForm();
        $form->decision = 'approved';

        $this->assertTrue($form->isApproval());
    }

    #[Test]
    public function is_approval_returns_false_when_rejected(): void
    {
        $form = $this->makeForm();
        $form->decision = 'rejected';

        $this->assertFalse($form->isApproval());
    }
}

