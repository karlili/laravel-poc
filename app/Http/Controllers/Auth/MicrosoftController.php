<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\EntraSignInException;
use App\Services\Auth\EntraUserResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class MicrosoftController extends Controller
{
    /**
     * Send the user to Microsoft to sign in.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        $driver = Socialite::driver('azure');

        // "openid" makes Microsoft return an ID token, which carries the tenant ID.
        if ($driver instanceof AbstractProvider) {
            $driver->scopes(['openid', 'profile', 'email', 'User.Read']);
        }

        return $driver->redirect();
    }

    /**
     * Handle the response from Microsoft.
     */
    public function callback(Request $request, EntraUserResolver $resolver): RedirectResponse
    {
        if ($request->filled('error')) {
            return $this->failed(__('Microsoft sign-in was cancelled or failed.'));
        }

        try {
            $user = $resolver->resolve(Socialite::driver('azure')->user());
        } catch (EntraSignInException $e) {
            return $this->failed($e->getMessage());
        } catch (Throwable $e) {
            Log::warning('Microsoft sign-in failed', ['exception' => $e]);

            return $this->failed(__('Microsoft sign-in failed. Please try again.'));
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(config('fortify.home'));
    }

    private function failed(string $message): RedirectResponse
    {
        return redirect()->route('login')->withErrors(['email' => $message]);
    }
}
