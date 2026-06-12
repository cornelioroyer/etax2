<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleLoginController extends Controller
{
    public function redirect(): RedirectResponse
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return redirect()->route('login')
                ->withErrors(['email' => 'El acceso con Google aún no está configurado.']);
        }

        return Socialite::driver('google')
            ->scopes(['openid', 'profile', 'email'])
            ->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable $exception) {
            Log::warning('Google login failed.', ['message' => $exception->getMessage()]);

            return redirect()->route('login')
                ->withErrors(['email' => 'No se pudo validar la cuenta de Google. Intenta nuevamente.']);
        }

        $email = strtolower((string) $googleUser->getEmail());

        if ($email === '') {
            return redirect()->route('login')
                ->withErrors(['email' => 'La cuenta de Google no devolvió un correo válido.']);
        }

        $user = User::whereRaw('lower(email) = ?', [$email])->first();

        if (! $user) {
            $user = User::create([
                'name' => $googleUser->getName() ?: $email,
                'email' => $email,
                'password' => Hash::make(Str::random(64)),
                'is_admin' => false,
                'is_active' => true,
            ]);

            session(['compania_activa_id' => 1]);
        }

        if (! $user->is_active) {
            return redirect()->route('login')
                ->withErrors(['email' => 'Este usuario está inactivo.']);
        }

        // Todo el que entra queda como minimo con rol "usuario" en la compañía 1
        $user->asegurarAccesoDefault(1);
        $user->forceFill(['ultimo_login' => now()])->save();

        Auth::login($user, true);
        request()->session()->regenerate();

        return redirect()->intended(route('dashboard', absolute: false));
    }
}
