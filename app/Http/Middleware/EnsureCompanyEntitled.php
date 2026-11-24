<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use App\Models\User;
use App\Services\InfoDot\EntitlementClient;
use App\Support\EntitlementRefusal;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The signed-in area honours the company's entitlement (R-50).
 *
 * InfoDot decides whether a company holds Dot.Agents. A person already signed in must stop working when that ends, so
 * each request from a signed-in person asks (through a sixty-second cache) whether the business they have open still
 * holds the platform. A clear "no" answers with the plain refusal page and HTTP 403: nothing is read or written.
 * Anything else, including every kind of failure, lets the request through exactly as before.
 *
 * R-61: when InfoDot has switched Dot.Agents off (maintenance, unavailable) signed-in requests get a 503 with InfoDot's
 * message, ahead of the company check. Nothing is deleted or changed and nobody is signed out. A person's own data
 * rights (privacy.export, privacy.erase) are never blocked.
 *
 * Only HTTP requests from signed-in people are gated. Scheduled runs, queue jobs, webhooks and API-token requests
 * never pass through here, so a company's running agents keep running when its access ends.
 *
 * Runs after `org.context` (which fixes the open business) and is a Livewire persistent middleware, so component
 * updates from these pages are checked too.
 */
class EnsureCompanyEntitled
{
    /** Session key: redeem's `entitled` for the person's personal workspace, remembered at sign-in (absent: not set). */
    public const PERSONAL = 'infodot.personal_entitled';

    /**
     * A person's own data rights are never blocked by a commercial access state: they stay behind sign-in and their
     * own policy, and nothing else is opened by this list.
     */
    private const EXEMPT = ['privacy.export', 'privacy.erase'];

    /** The reason word in the JSON body when InfoDot has switched Dot.Agents off (R-61). */
    public const UNAVAILABLE = 'platform_unavailable';

    public function __construct(private readonly EntitlementClient $entitlements) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Machine-to-machine (a bearer token) and signed-out requests are not this middleware's business.
        if (! $this->entitlements->enabled() || $request->bearerToken() || $request->routeIs(...self::EXEMPT)) {
            return $next($request);
        }

        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $organization = $this->organization($request, $user);

        if (! $organization) {
            return $next($request);
        }

        // A personal workspace, or a business InfoDot does not know: the read endpoint is never asked. What InfoDot
        // said about the person's personal workspace at sign-in decides: exactly false is a refusal (individuals hold
        // the free core only); true, or nothing remembered, carries on.
        if (blank($organization->infodot_team_uuid)) {
            if (! $request->hasSession() || $request->session()->get(self::PERSONAL) !== false) {
                return $next($request);
            }

            return $this->refuse($request, fn () => EntitlementRefusal::response('company_required', null, $this->others($user, $organization)));
        }

        $answer = $this->entitlements->forCompany((string) $organization->infodot_team_uuid);

        // R-61: a platform InfoDot has switched off is off for everyone, whatever the company holds. Checked first.
        if ($answer !== null && $answer['available'] === false) {
            return $this->unavailable($request, $answer['message']);
        }

        if ($answer === null || $answer['entitled'] === true) {
            return $next($request);
        }

        return $this->refuse($request, fn () => EntitlementRefusal::response($answer['status'], $organization->name, $this->others($user, $organization)));
    }

    /** 503 with the message and nothing else: no record is read or changed, and the person is not signed out. */
    private function unavailable(Request $request, ?string $message): Response
    {
        $message ??= 'Dot.Agents is temporarily unavailable.';

        // Livewire throws away any non-redirect response a persistent middleware returns, so a JSON 503 is thrown as an
        // HttpResponseException: the component does not run and nothing else comes back.
        if ($request->expectsJson() || $request->headers->has('X-Livewire') || $request->is('livewire*', 'livewire-*/*')) {
            $json = response()->json(['message' => $message, 'reason' => self::UNAVAILABLE], 503)
                ->header('Retry-After', '60')->header('Cache-Control', 'no-store, private');

            if ($request->headers->has('X-Livewire') || $request->is('livewire*', 'livewire-*/*')) {
                throw new HttpResponseException($json);
            }

            return $json;
        }

        return response()->view('errors.platform-unavailable', ['message' => $message, 'signedIn' => true], 503)
            ->header('Cache-Control', 'no-store, private')->header('Retry-After', '60');
    }

    /** The person's other businesses that are real companies: the ones worth switching to. */
    private function others(User $user, Organization $current)
    {
        return $user->organizations()
            ->where('organizations.id', '!=', $current->id)
            ->whereNotNull('organizations.infodot_team_uuid')
            ->orderBy('organizations.name')
            ->get(['organizations.id', 'organizations.name']);
    }

    /**
     * Livewire throws away any non-redirect response a persistent middleware returns, so a refusal page would let a
     * component update run. On a Livewire update the refusal must abort: nothing runs and no component comes back.
     */
    private function refuse(Request $request, Closure $page): Response
    {
        if ($request->headers->has('X-Livewire') || request()->headers->has('X-Livewire')) {
            abort(403);
        }

        return $page();
    }

    /** The business this request works in: the open one, which the person must belong to. */
    private function organization(Request $request, User $user): ?Organization
    {
        $id = $request->hasSession() ? $request->session()->get('current_organization_id') : null;

        $organization = $id ? $user->organizations()->where('organizations.id', (int) $id)->first() : null;

        return $organization ?? $user->currentOrganization();
    }
}
