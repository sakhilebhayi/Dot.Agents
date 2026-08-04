<?php

namespace App\Livewire\Organizations;

use App\Actions\Social\SaveSocialCredentialsAction;
use App\Models\Organization;
use App\Models\OrganizationSocialCredential;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;

class SocialCredentials extends Component
{
    /** Which platform panel is currently open for editing. */
    public ?string $editing = null;

    public string $clientId = '';

    public string $clientSecret = '';

    public string $redirectUri = '';

    /** Success/error flash state per platform. */
    public array $saved = [];

    public static array $platforms = [
        'facebook' => ['label' => 'Facebook',    'icon' => 'F',  'color' => 'bg-blue-600',   'docs' => 'https://developers.facebook.com/apps/'],
        'instagram' => ['label' => 'Instagram',   'icon' => 'I',  'color' => 'bg-pink-600',   'docs' => 'https://developers.facebook.com/apps/'],
        'linkedin' => ['label' => 'LinkedIn',    'icon' => 'in', 'color' => 'bg-sky-700',    'docs' => 'https://www.linkedin.com/developers/apps/'],
        'twitter' => ['label' => 'X (Twitter)', 'icon' => 'X',  'color' => 'bg-gray-900',   'docs' => 'https://developer.twitter.com/en/portal/'],
        'tiktok' => ['label' => 'TikTok',      'icon' => 'T',  'color' => 'bg-black',      'docs' => 'https://developers.tiktok.com/'],
        'youtube' => ['label' => 'YouTube',     'icon' => 'YT', 'color' => 'bg-red-600',    'docs' => 'https://console.cloud.google.com/'],
        'pinterest' => ['label' => 'Pinterest',   'icon' => 'P',  'color' => 'bg-red-700',    'docs' => 'https://developers.pinterest.com/apps/'],
        'patreon' => ['label' => 'Patreon',     'icon' => 'Pa', 'color' => 'bg-orange-600', 'docs' => 'https://www.patreon.com/portal/registration/register-clients'],
        'snapchat' => ['label' => 'Snapchat',    'icon' => 'Sc', 'color' => 'bg-yellow-400', 'docs' => 'https://kit.snapchat.com/'],
        'reddit' => ['label' => 'Reddit',      'icon' => 'R',  'color' => 'bg-orange-500', 'docs' => 'https://www.reddit.com/prefs/apps'],
        'discord' => ['label' => 'Discord',     'icon' => 'D',  'color' => 'bg-indigo-600', 'docs' => 'https://discord.com/developers/applications'],
        'twitch' => ['label' => 'Twitch',      'icon' => 'Tw', 'color' => 'bg-purple-700', 'docs' => 'https://dev.twitch.tv/console/apps'],
    ];

    #[Computed]
    public function organization(): Organization
    {
        return Organization::findOrFail(session('current_organization_id'));
    }

    #[Computed]
    public function credentials(): Collection
    {
        // No explicit organization_id filter needed: OrganizationSocialCredential's
        // HasOrganizationScope trait applies it automatically from the session.
        return OrganizationSocialCredential::get()->keyBy('platform');
    }

    public function openEdit(string $platform): void
    {
        $this->editing = $platform;
        $this->clientId = '';
        $this->clientSecret = '';
        $this->redirectUri = route('social.auth.callback', ['platform' => $platform]);
        $this->resetErrorBag();
    }

    public function cancelEdit(): void
    {
        $this->editing = null;
        $this->clientId = '';
        $this->clientSecret = '';
        $this->redirectUri = '';
        $this->resetErrorBag();
    }

    public function savePlatform(SaveSocialCredentialsAction $action): void
    {
        $this->validate([
            'clientId' => ['required', 'string', 'max:500'],
            'clientSecret' => ['required', 'string', 'max:500'],
            'redirectUri' => ['nullable', 'url', 'max:500'],
        ]);

        $action->execute(
            organization: $this->organization,
            platform: $this->editing,
            credentials: [
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'redirect_uri' => $this->redirectUri ?: null,
            ],
            updatedBy: auth()->id(),
        );

        $this->saved[$this->editing] = true;
        unset($this->credentials); // bust computed cache
        $this->cancelEdit();

        $this->js('setTimeout(() => { $wire.saved = {} }, 3000)');
    }

    public function removePlatform(string $platform, SaveSocialCredentialsAction $action): void
    {
        $action->delete($this->organization, $platform);
        unset($this->credentials);
    }

    public function render()
    {
        return view('livewire.organizations.social-credentials');
    }
}
