<?php

namespace App\Livewire\Organizations;

use App\Actions\Organizations\RevokeApiTokenAction;
use App\Services\Governance\AuditService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * ApiKeyManager
 *
 * Organization-level API key management.
 * Lists, creates, and revokes Sanctum API tokens scoped to the current org.
 */
class ApiKeyManager extends Component
{
    public string $newKeyName = '';

    public ?string $plainTextToken = null;

    // ── Computed ─────────────────────────────────────────────────────────────

    #[Computed]
    public function tokens()
    {
        return Auth::user()
            ->tokens()
            ->where('name', 'like', 'org-%')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'abilities' => $token->abilities,
                'last_used_at' => $token->last_used_at?->diffForHumans(),
                'created_at' => $token->created_at->toDateString(),
            ]);
    }

    // ── Actions ───────────────────────────────────────────────────────────────

    public function createToken(): void
    {
        $this->validate([
            'newKeyName' => ['required', 'string', 'min:3', 'max:60'],
        ]);

        $orgId = session('current_organization_id');
        abort_if(! $orgId, 403, 'No active organization context.');

        // Prefix with org ID for scoping visibility
        $tokenName = "org-{$orgId}:{$this->newKeyName}";

        $newToken = Auth::user()->createToken(
            $tokenName,
            ['read', 'write'],
        );

        $this->plainTextToken = $newToken->plainTextToken;
        $this->newKeyName = '';

        app(AuditService::class)->logUserAction(
            event: 'api_key.created',
            description: "API key created: {$tokenName}",
        );

        $this->dispatch('token-created');
    }

    public function revokeToken(int $tokenId): void
    {
        $token = Auth::user()->tokens()->find($tokenId);

        if (! $token) {
            return;
        }

        app(RevokeApiTokenAction::class)->execute($token);

        if ($this->plainTextToken !== null) {
            $this->plainTextToken = null;
        }

        unset($this->tokens);
    }

    public function dismissToken(): void
    {
        $this->plainTextToken = null;
    }

    // ── Render ────────────────────────────────────────────────────────────────

    public function render()
    {
        return view('livewire.organizations.api-key-manager');
    }
}
