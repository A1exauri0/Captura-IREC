<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PinAutorizacion;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) {
            return redirect()->route('admin.analytics_sellos.reportes.lotes_trabajando');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'login'    => 'required|string',
            'password' => 'required|string',
        ]);

        $loginInput = trim($request->input('login'));
        $password = $request->input('password');

        $user = User::where('email', $loginInput)
            ->orWhere('user', $loginInput)
            ->orWhereRaw('LOWER(user) = ?', [strtolower($loginInput)])
            ->first();

        if ($user && Hash::check($password, $user->password)) {
            Auth::login($user, true);
            $request->session()->regenerate();
            return redirect()->intended(route('admin.analytics_sellos.reportes.lotes_trabajando'));
        }

        return back()->withErrors([
            'login' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
        ])->onlyInput('login');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }

    public function generarNuevoPin()
    {
        $pin = PinAutorizacion::regenerarPin();
        return response()->json([
            'success' => true,
            'pin'     => $pin,
        ]);
    }
}
