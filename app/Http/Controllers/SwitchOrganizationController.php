<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Opens another business the person already belongs to (R-50). Membership is the only thing checked here; whether the
 * business holds Dot.Agents is decided by the entitlement middleware on the page they land on.
 */
class SwitchOrganizationController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $id = $request->validate(['organization_id' => ['required', 'integer']])['organization_id'];

        abort_unless($request->user()->organizations()->where('organizations.id', $id)->exists(), 403);

        $request->session()->put('current_organization_id', (int) $id);

        return redirect()->route('dashboard');
    }
}
