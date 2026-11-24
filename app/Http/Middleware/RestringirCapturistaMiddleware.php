<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestringirCapturistaMiddleware
{
    /**
     * Bloquea el acceso de usuarios con rol exclusivo de Captura a cualquier ruta
     * o endpoint fuera de la Estación de Captura (/captura/*).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->hasRole('Captura') && !$user->hasAnyRole(['superadmin', 'administrador'])) {
            // Para peticiones API/AJAX o mutaciones (POST/PUT/PATCH/DELETE), responder 403 estricto
            if ($request->expectsJson() || $request->ajax() || !$request->isMethod('GET')) {
                return response()->json([
                    'ok'    => false,
                    'error' => 'Acceso denegado. Tu perfil de Capturista solo tiene permiso para la Estación de Captura.',
                ], 403);
            }

            // Para navegación GET directa a URLs administrativas, redirigir a la Estación de Captura
            return redirect()->route('admin.captura.index');
        }

        return $next($request);
    }
}
