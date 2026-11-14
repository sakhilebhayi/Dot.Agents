<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Ecosystem\ProvisionUserFromInfoDot;
use App\Actions\Ecosystem\SyncTeamsFromInfoDot;
use App\Http\Controllers\Auth\Concerns\HandlesEcosystemHandoff;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureCompanyEntitled;
use App\Http\Middleware\ReverifyWithInfoDot;
use App\Services\InfoDot\IdentityClient;
use App\Services\InfoDot\InfoDotUnavailable;
use App\Support\EntitlementRefusal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class EcosystemAuthController extends Controller
{
    use HandlesEcosystemHandoff;

    /** A GET (an old link with the token in the URL) is not honoured: the token is never read. */
    public function legacy(): RedirectResponse
    {
        return redirect()->to('/login');
    }

    /**
     * Sign-in from InfoDot. The token arrives in the POST body of a form InfoDot's launch page posts here, so it
     * is never in a URL (address bar, history, access logs, Referer). A refused sign-in lands on the plain login
     * page: the credential is not shown, echoed, flashed or logged. A GET with ?token= is no longer honoured
     * (the route sends it to the login page), and a token in the query string of a POST is ignored.
     */
    public function handle(Request $request): Response
    {
        if ($request->isMethod('post') && ! $this->postedFromInfoDot($request)) {
            return $this->refused();
        }

        try {
            return app()->call([$this, 'redeem'], ['request' => $request]);
        } catch (HttpExceptionInterface $e) {
            return $this->refused($e->getStatusCode() === 503);
        }
    }

    private function refused(bool $unavailable = false): RedirectResponse
    {
        return redirect('/login', 303)->with('status', $unavailable
            ? 'Sign-in through InfoDot is unavailable right now. Please try again in a moment.'
            : 'We could not sign you in through InfoDot. Please open this app again from InfoDot.');
    }

    public function redeem(Request $request, IdentityClient $infoDot, ProvisionUserFromInfoDot $provision, SyncTeamsFromInfoDot $syncTeams): Response
    {
        $token = $request->post('token');

        abort_unless(is_string($token) && $token !== '', 403);

        // A shared browser must not carry the last person's business into this sign-in.
        $request->session()->forget(['current_organization_id', EnsureCompanyEntitled::PERSONAL]);

        $user = $this->userFromLocalToken($token);

        // Production gives each Dot app its own database, so InfoDot's token is not
        // in ours: ask InfoDot to prove it.
        if (! $user && $infoDot->enabled()) {
            try {
                $identity = $infoDot->redeem($token);
            } catch (InfoDotUnavailable) {
                abort(503, 'Sign-in through InfoDot is unavailable right now. Please try again in a moment.');
            }

            // R-50: InfoDot marks each company with whether it holds this platform. When it says none does, nobody
            // is signed in and nothing is created: the person gets the plain page that says so.
            if ($identity && ($refusal = $this->nothingEntitled($identity)) !== null) {
                return EntitlementRefusal::response($refusal['reason'], $refusal['company']);
            }

            $user = $identity ? $provision($identity) : null;

            if ($user) {
                $syncTeams($user, $identity);
                $this->rememberPersonal($request, $identity);
            }
        }

        abort_if(! $user, 403);

        // Auth::login() also regenerates the session id (no fixation).
        Auth::login($user);
        $request->session()->put(ReverifyWithInfoDot::VERIFIED_AT, now()->getTimestamp());

        return $this->safeRedirect($request->post('redirect')) ?? redirect()->route('dashboard');
    }

    /** What InfoDot said about the person's personal workspace for this platform; nothing is remembered when it said nothing. */
    private function rememberPersonal(Request $request, array $identity): void
    {
        $personal = collect($identity['teams'] ?? [])->first(fn ($t) => is_array($t) && ($t['personal'] ?? false) && is_bool($t['entitled'] ?? null));

        if ($personal) {
            $request->session()->put(EnsureCompanyEntitled::PERSONAL, $personal['entitled']);
        }
    }

    /**
     * The reason and company to name when InfoDot answered and no company in the answer holds this platform. Null
     * when at least one does, or when any company comes without the `entitled` key (an InfoDot from before R-50,
     * where absence is not a refusal).
     *
     * @param  array<string, mixed>  $identity
     * @return array{reason: string, company: ?string}|null
     */
    private function nothingEntitled(array $identity): ?array
    {
        $teams = collect($identity['teams'] ?? [])->filter(fn ($t) => is_array($t))->values();

        if ($teams->isEmpty() || $teams->contains(fn ($t) => ! array_key_exists('entitled', $t) || $t['entitled'] === true)) {
            return null;
        }

        // Speak about the company the person was working in at InfoDot, else their first real company.
        $active = $identity['current_team_uuid'] ?? null;
        $about = $teams->first(fn ($t) => $active !== null && ($t['uuid'] ?? null) === $active && ! ($t['personal'] ?? false))
            ?? $teams->first(fn ($t) => ! ($t['personal'] ?? false))
            ?? $teams->first();

        return [
            'reason' => is_string($about['access'] ?? null) ? $about['access'] : '',
            'company' => ($about['personal'] ?? false) || ! is_string($about['name'] ?? null) ? null : $about['name'],
        ];
    }
}
