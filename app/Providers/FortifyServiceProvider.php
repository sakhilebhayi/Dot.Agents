<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Registered after every provider boots: Jetstream sets its own login view in its boot, and the last one wins.
        $this->app->booted(function () {
            $front = fn (string $view, string $path) => function () use ($view, $path) {
                $infodot = (string) config('ecosystem.platforms.infodot.url', '');

                return $infodot !== '' && config('ecosystem.sso_only')
                    ? redirect()->away(rtrim($infodot, '/').$path)
                    : view($view);
            };

            Fortify::loginView($front('auth.login', '/login'));
            Fortify::requestPasswordResetLinkView($front('auth.forgot-password', '/forgot-password'));
            Fortify::resetPasswordView(function () {
                $infodot = (string) config('ecosystem.platforms.infodot.url', '');

                return $infodot !== '' && config('ecosystem.sso_only')
                    ? redirect()->away(rtrim($infodot, '/').'/forgot-password')
                    : view('auth.reset-password', ['request' => request()]);
            });
        });

        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });

        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('passkeys', function (Request $request) {
            $credentialId = $request->input('credential.id');

            return Limit::perMinute(10)->by(
                ($credentialId ?: $request->session()->getId()).'|'.$request->ip()
            );
        });
    }
}
