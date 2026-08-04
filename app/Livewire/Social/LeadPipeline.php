<?php

namespace App\Livewire\Social;

use App\Actions\Social\QualifyLeadAction;
use App\Models\SocialLead;
use Livewire\Attributes\Computed;
use Livewire\Component;

class LeadPipeline extends Component
{
    public string $stage = 'all';

    public string $intentLevel = 'all';

    public string $platform = 'all';

    public string $sortBy = 'lead_score';

    #[Computed]
    public function orgId(): int
    {
        return (int) session('current_organization_id');
    }

    #[Computed]
    public function leads()
    {
        // No explicit organization_id filter needed: SocialLead's
        // HasOrganizationScope trait applies it automatically from the session.
        $query = SocialLead::with(['socialConversation', 'agentDeployment'])
            ->orderByDesc($this->sortBy);

        if ($this->stage !== 'all') {
            $query->where('stage', $this->stage);
        }
        if ($this->intentLevel !== 'all') {
            $query->where('intent_level', $this->intentLevel);
        }
        if ($this->platform !== 'all') {
            $query->where('platform', $this->platform);
        }

        return $query->paginate(25);
    }

    #[Computed]
    public function pipeline(): array
    {
        $stages = ['awareness', 'interest', 'consideration', 'intent', 'evaluation', 'purchase'];

        // No explicit organization_id filter needed: SocialLead's
        // HasOrganizationScope trait applies it automatically from the session.
        return collect($stages)->mapWithKeys(fn ($stage) => [
            $stage => SocialLead::where('stage', $stage)
                ->count(),
        ])->toArray();
    }

    #[Computed]
    public function hotLeadCount(): int
    {
        // No explicit organization_id filter needed: SocialLead's
        // HasOrganizationScope trait applies it automatically from the session.
        return SocialLead::where(fn ($q) => $q->where('priority', 'hot')->orWhere('lead_score', '>=', 80))
            ->whereIn('status', ['new', 'contacted', 'qualified'])
            ->count();
    }

    public function qualify(int $leadId): void
    {
        $lead = SocialLead::where('organization_id', $this->orgId)->findOrFail($leadId);

        app(QualifyLeadAction::class)->execute($lead, auth()->id());

        $this->dispatch('lead-qualified');
    }

    public function render()
    {
        return view('livewire.social.lead-pipeline');
    }
}
