<?php

namespace App\Services\Social;

use App\Support\SocialPlatformConfig;
use Laravel\Socialite\Contracts\Factory as Socialite;
use Laravel\Socialite\Two\AbstractProvider;

/**
 * Resolves a configured Socialite driver for a social platform, applying
 * the organization's own custom OAuth app credentials (client id/secret/
 * redirect) when one is on file, falling back to the platform-wide default
 * app credentials otherwise. Extracted from SocialOAuthController to keep
 * the controller as a thin HTTP layer.
 */
class SocialOAuthDriverResolver
{
    public function __construct(
        private readonly Socialite $socialite,
        private readonly SocialPublishingService $publishingService,
    ) {}

    public function resolve(string $platform): AbstractProvider
    {
        $driver = SocialPlatformConfig::driverFor($platform);
        $orgId = (int) session('current_organization_id');

        $orgCred = $this->publishingService->findCredential($orgId, $platform);

        if ($orgCred) {
            $callbackUrl = $orgCred->redirect_uri ?? route('social.auth.callback', ['platform' => $platform]);

            config([
                "services.{$driver}.client_id" => $orgCred->client_id,
                "services.{$driver}.client_secret" => $orgCred->client_secret,
                "services.{$driver}.redirect" => $callbackUrl,
            ]);
        }

        return $this->socialite->driver($driver);
    }
}
