<?php

namespace App\Http\Controllers;

use App\Services\Governance\AuditService;
use App\Services\Organizations\PersonInvitations;
use App\Support\OrgRoles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class InvitationController extends Controller
{
    public function __construct(private readonly PersonInvitations $invitations) {}

    /** Opening the link changes nothing, because mail scanners open links too. */
    public function show(Request $request, string $token): View
    {
        $invitation = $this->invitations->find($token);

        if ($invitation !== null && ! $request->user()) {
            $request->session()->put('url.intended', $request->url());
        }

        return view('invitations.show', [
            'invitation' => $invitation,
            'token' => $token,
            'roleLabel' => $invitation ? (OrgRoles::LABELS[$invitation->role] ?? 'Member') : null,
            'matches' => $invitation && $request->user() && $this->invitations->isFor($invitation, $request->user()),
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->invitations->find($token);
        abort_if($invitation === null, 404);

        $this->invitations->accept($invitation, $request->user());
        $request->session()->put('current_organization_id', $invitation->organization_id);

        app(AuditService::class)->logUserAction(
            event: 'member.joined',
            description: 'Joined the business by invitation',
            data: ['role' => $invitation->role],
            subject: $invitation->organization,
        );

        return redirect()->route('dashboard');
    }
}
