<?php

namespace App\Http\Controllers;

use App\Actions\Compliance\EraseUserDataAction;
use App\Actions\Compliance\ExportUserDataAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** A person's own data: a copy to take away, or removal. */
class PrivacyController extends Controller
{
    public function export(Request $request, ExportUserDataAction $action): StreamedResponse
    {
        $data = $action->execute($request->user(), $request->user());

        return response()->streamDownload(fn () => print (json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)), 'my-data.json', ['Content-Type' => 'application/json']);
    }

    public function erase(Request $request, EraseUserDataAction $action): RedirectResponse
    {
        $user = $request->user();

        $request->validate(['confirm_email' => ['required', 'in:'.$user->email]], ['confirm_email.in' => 'Type your email address exactly to confirm.']);

        $action->execute($user, $user);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }
}
