<?php

namespace App\Support;

use Illuminate\Http\Response;

/**
 * The plain page a person gets when their company does not hold Dot.Agents (R-50): at sign-in, when InfoDot says no
 * company of theirs is entitled, and during a session, when the current company's access has ended. Worded from
 * the reason InfoDot gave (a redeem `access` reason or a read-endpoint `status`); it shows nothing from the business.
 * Nothing is stopped or deleted by it: agents, schedules and runs carry on as they were.
 */
class EntitlementRefusal
{
    /**
     * @param  iterable<int, object>  $otherCompanies  the person's other businesses here, to switch to (id, name)
     */
    public static function response(string $reason, ?string $company, iterable $otherCompanies = []): Response
    {
        $infodot = rtrim((string) config('ecosystem.platforms.infodot.url', 'https://infodot.co.za'), '/');

        return response()
            ->view('entitlement.refused', [
                ...self::wording($reason, $company),
                'platformsUrl' => $infodot.'/platforms/'.config('ecosystem.infodot.platform', 'agents'),
                'otherCompanies' => collect($otherCompanies)->values(),
            ], 403)
            ->header('Cache-Control', 'no-store, private');
    }

    /**
     * @return array{state: string, title: string, body: string}
     */
    public static function wording(string $reason, ?string $company): array
    {
        $for = filled($company) ? $company : 'your company';

        return match ($reason) {
            'suspended' => [
                'state' => 'stop',
                'title' => "Dot.Agents is suspended for {$for}",
                'body' => 'Nobody in the company can open it until the suspension is lifted. Agents, schedules and runs are not stopped or deleted. Platforms in InfoDot shows what is needed.',
            ],
            'payment_required' => [
                'state' => 'hold',
                'title' => "Dot.Agents needs a payment for {$for}",
                'body' => 'It opens again once the payment is settled. Agents, schedules and runs are not stopped or deleted. An owner or administrator can settle it from Platforms in InfoDot.',
            ],
            'subscription_expired', 'trial_ended', 'expired', 'cancelled' => [
                'state' => 'stop',
                'title' => "Dot.Agents has ended for {$for}",
                'body' => 'The company no longer holds this platform. Agents, schedules and runs are not stopped or deleted. An owner or administrator can take it up again from Platforms in InfoDot.',
            ],
            'activation_pending' => [
                'state' => 'hold',
                'title' => "Dot.Agents is waiting to be switched on for {$for}",
                'body' => 'The request is in and has not been completed. Platforms in InfoDot shows where it stands.',
            ],
            'not_permitted_for_you' => [
                'state' => 'stop',
                'title' => "{$for} has not given you Dot.Agents",
                'body' => 'The company holds this platform and chooses who uses it. An owner or administrator can add you from Platforms in InfoDot.',
            ],
            'no_personal_access', 'company_required' => [
                'state' => 'stop',
                'title' => 'Dot.Agents needs a company that has switched it on',
                'body' => 'A personal workspace does not hold this platform. Add or join a company in InfoDot and switch Dot.Agents on for it from Platforms. Nothing in this workspace has been removed.',
            ],
            'temporarily_unavailable', 'not_launched' => [
                'state' => 'hold',
                'title' => 'Dot.Agents is not available right now',
                'body' => 'InfoDot is not handing anyone over to this platform at the moment. Platforms in InfoDot shows when it is back.',
            ],
            default => [
                'state' => 'stop',
                'title' => "Dot.Agents is not switched on for {$for}",
                'body' => 'An owner or administrator can switch it on from Platforms in InfoDot. Agents, schedules and runs are not stopped or deleted.',
            ],
        };
    }
}
