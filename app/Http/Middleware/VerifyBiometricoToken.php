<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyBiometricoToken
{
    /**
     * Valida el Bearer token configurado para la estación local de chequeo biométrico.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = trim((string) config('services.biometrico.token', ''));

        if ($configuredToken === '') {
            return response()->json([
                'ok'    => false,
                'error' => 'Servicio biométrico no configurado (BIOMETRICO_API_TOKEN vacío en el servidor).',
            ], 503);
        }

        $providedToken = trim((string) ($request->bearerToken() ?: $request->header('X-Biometrico-Token', '')));

        if ($providedToken === '' || !hash_equals($configuredToken, $providedToken)) {
            return response()->json([
                'ok'    => false,
                'error' => 'No autorizado. Token biométrico inválido o ausente.',
            ], 401);
        }

        return $next($request);
    }
}
