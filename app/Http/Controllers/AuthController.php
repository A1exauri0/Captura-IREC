<?php

namespace App\Http\Controllers;

use App\Events\LoteActualizadoEvent;
use App\Models\ArchivoSesion;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AuthController extends Controller
{
    public function showLoginForm()
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->hasRole('Captura') && !$user->hasAnyRole(['superadmin', 'administrador'])) {
                return redirect()->route('admin.captura.index');
            }
            if ($user->hasAnyRole(['superadmin', 'administrador'])) {
                return redirect()->route('admin.dashboard.avance');
            }
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
            if (!$user->estatus) {
                return back()->withErrors([
                    'login' => 'Tu cuenta está desactivada. Contacta al administrador.',
                ])->onlyInput('login');
            }

            $esCapturista = $user->hasRole('Captura') && !$user->hasAnyRole(['superadmin', 'administrador']);

            if (!empty($user->remember_token)) {
                $user->forceFill(['remember_token' => null])->save();
            }

            Auth::login($user, false);
            Cookie::queue(Cookie::forget(Auth::guard('web')->getRecallerName()));
            $request->session()->regenerate();
            $request->session()->forget('pc_seleccionada');

            // Los capturistas siempre van directo a la Estación de Captura (donde el modal pide su número de PC)
            if ($esCapturista) {
                return redirect()->route('admin.captura.index');
            }

            if ($user->hasAnyRole(['superadmin', 'administrador'])) {
                return redirect()->intended(route('admin.dashboard.avance'));
            }

            return redirect()->intended(route('admin.analytics_sellos.reportes.lotes_trabajando'));
        }

        return back()->withErrors([
            'login' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
        ])->onlyInput('login');
    }

    public function logout(Request $request)
    {
        $user = Auth::user();

        if ($user) {
            if (Schema::hasTable('archivos_sesiones')) {
                $sesionesActivas = ArchivoSesion::where('user_id', $user->id)
                    ->where('estado', 'en_captura')
                    ->get(['id', 'archivo_nombre']);

                if ($sesionesActivas->isNotEmpty()) {
                    ArchivoSesion::whereIn('id', $sesionesActivas->pluck('id'))->delete();

                    foreach ($sesionesActivas as $sesion) {
                        try {
                            broadcast(new LoteActualizadoEvent('lote_eliminado', [
                                'id'   => $sesion->id,
                                'lote' => $sesion->archivo_nombre,
                            ]));
                        } catch (\Throwable $e) {
                            // No interrumpir el cierre de sesión si WebSockets no responde
                        }
                    }
                }
            }

            if (!empty($user->remember_token)) {
                $user->forceFill(['remember_token' => null])->save();
            }
        }

        Auth::logout();
        Cookie::queue(Cookie::forget(Auth::guard('web')->getRecallerName()));
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
