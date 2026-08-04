<?php

namespace App\Livewire\Security;

use App\Actions\Security\EmergencyKillSwitchAction;
use App\Actions\Security\ResolveSecurityEventAction;
use App\DTOs\Security\ResolveSecurityEventData;
use App\Models\AgentDeployment;
use App\Models\Organization;
use App\Models\SecurityEvent;
use App\Services\Governance\DigitalImmuneSystem;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Lazy;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithPagination;

#[Lazy]
class SecurityCenter extends Component
{
    use WithPagination;

    public string $filterSeverity = '';

    public string $filterType = '';

    public ?array $disReport = null;

    public bool $runningDIS = false;

    /** Confirmation string required before executing org-level kill switch. */
    #[Validate('nullable|string|max:100')]
    public string $killSwitchConfirmation = '';

    #[Computed]
    public function organizationId(): ?int
    {
        return session('current_organization_id');
    }

    #[Computed]
    public function events()
    {
        // No explicit organization_id filter needed: SecurityEvent's
        // HasOrganizationScope trait applies it automatically from the session.
        return SecurityEvent::when($this->filterSeverity, fn ($q) => $q->where('severity', $this->filterSeverity))
            ->when($this->filterType, fn ($q) => $q->where('event_type', $this->filterType))
            ->orderByDesc('created_at')
            ->paginate(15);
    }

    #[Computed]
    public function stats(): array
    {
        // No explicit organization_id filter needed: SecurityEvent's and
        // AgentDeployment's HasOrganizationScope trait applies it automatically.
        $events = SecurityEvent::query();

        return [
            'total_24h' => (clone $events)->where('created_at', '>=', now()->subDay())->count(),
            'critical' => (clone $events)->where('severity', 'critical')->where('status', 'open')->count(),
            'auto_remediated' => (clone $events)->where('auto_remediated', true)->count(),
            'quarantined' => AgentDeployment::where('status', 'suspended')->count(),
        ];
    }

    public function runDISCheck(): void
    {
        $this->runningDIS = true;
        $org = Organization::find($this->organizationId);
        if ($org) {
            $this->disReport = app(DigitalImmuneSystem::class)->runHealthCheck($org->id);
        }
        $this->runningDIS = false;
    }

    public function resolveEvent(int $id): void
    {
        app(ResolveSecurityEventAction::class)->execute(
            new ResolveSecurityEventData($id, auth()->id())
        );
    }

    /** Kill a single agent deployment immediately. */
    public function killDeployment(int $deploymentId): void
    {
        $deployment = AgentDeployment::findOrFail($deploymentId);
        app(EmergencyKillSwitchAction::class)->killDeployment(
            $deployment,
            'Manual kill switch activated from Security Center'
        );
        session()->flash('status', "Agent '{$deployment->name}' has been suspended.");
    }

    /**
     * Kill all active workflows for the current org.
     * Requires the confirmation string to match 'HALT WORKFLOWS'.
     */
    public function killAllWorkflows(): void
    {
        if ($this->killSwitchConfirmation !== 'HALT WORKFLOWS') {
            $this->addError('killSwitchConfirmation', 'Type HALT WORKFLOWS to confirm.');

            return;
        }

        $org = Organization::find($this->organizationId);
        abort_if(! $org, 403);

        $count = app(EmergencyKillSwitchAction::class)->killAllWorkflows(
            $org,
            'Emergency halt via Security Center'
        );

        $this->killSwitchConfirmation = '';
        session()->flash('status', "All workflows halted. {$count} running executions aborted.");
    }

    public function render()
    {
        return view('livewire.security.security-center');
    }
}
