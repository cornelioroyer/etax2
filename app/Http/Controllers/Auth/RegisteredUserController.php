<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class RegisteredUserController extends Controller
{
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $isFirstUser = User::query()->doesntExist();

        $user = (new User([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]))->forceFill([
            // El primer usuario del sistema es super_admin; is_admin/is_active
            // están guardados (no son mass-assignable) → se fijan explícito.
            'is_admin' => $isFirstUser,
            'is_active' => true,
        ]);
        $user->save();

        event(new Registered($user));

        // Todo registro nuevo queda con rol "usuario" en la compañía 1
        $user->asegurarAccesoDefault(1);
        session(['compania_activa_id' => 1]);

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
