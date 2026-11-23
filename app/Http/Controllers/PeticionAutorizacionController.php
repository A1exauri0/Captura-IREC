<?php

namespace App\Http\Controllers;

use App\Events\PeticionAutorizacionEvent;
use App\Models\PeticionAutorizacion;
use App\Services\PdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PeticionAutorizacionController extends Controller
{
    /**
     * Muestra la vista en tiempo real para supervisar, autorizar o rechazar peticiones de capturistas.
     */
    public function index()
    {
        return view('monitoreo.autorizaciones_tiempo_real');
    }

    /**
     * Obtiene el listado de peticiones y KPIs en tiempo real.
     */
    public function listar(Request $request)
    {
        $estado = $request->input('estado');
        $tipo = $request->input('tipo_peticion');

        $query = PeticionAutorizacion::with(['solicitante:id,name,user,turno', 'supervisor:id,name,user'])
            ->orderByRaw("CASE WHEN estado = 'pendiente' THEN 0 ELSE 1 END")
            ->orderBy('id', 'desc');

        if (!empty($estado)) {
            $query->where('estado', $estado);
        }
        if (!empty($tipo)) {
            $query->where('tipo_peticion', $tipo);
        }

        $peticiones = $query->limit(200)->get()->map(function ($p) {
            return [
                'id'                    => $p->id,
                'user_id'               => $p->user_id,
                'capturista'            => $p->solicitante ? $p->solicitante->name : 'Capturista #' . $p->user_id,
                'usuario_login'         => $p->solicitante ? $p->solicitante->user : '-',
                'turno'                 => $p->solicitante ? $p->solicitante->turno : '-',
                'tipo_peticion'         => $p->tipo_peticion,
                'accion_solicitada'     => $p->accion_solicitada,
                'disco'                 => $p->disco ?: 'N/A',
                'lote'                  => $p->lote ?: 'N/A',
                'archivo_nombre'        => $p->archivo_nombre ?: '-',
                'ruta_origen'           => $p->ruta_origen,
                'ruta_destino'          => $p->ruta_destino,
                'paginas'               => $p->paginas,
                'hash'                  => $p->hash,
                'archivo_danado'        => (bool) $p->archivo_danado,
                'motivo'                => $p->motivo ?: 'Solicitud de autorización desde estación de captura',
                'estado'                => $p->estado,
                'supervisor'            => $p->supervisor ? $p->supervisor->name : null,
                'comentario_resolucion' => $p->comentario_resolucion,
                'creado_en'             => $p->created_at ? $p->created_at->format('Y-m-d H:i:s') : '-',
                'resuelto_en'           => $p->resuelto_en ? $p->resuelto_en->format('Y-m-d H:i:s') : null,
            ];
        });

        $kpis = [
            'pendientes'  => PeticionAutorizacion::where('estado', 'pendiente')->count(),
            'autorizadas' => PeticionAutorizacion::where('estado', 'autorizada')->count(),
            'rechazadas'  => PeticionAutorizacion::where('estado', 'rechazada')->count(),
            'total'       => PeticionAutorizacion::count(),
        ];

        return response()->json([
            'ok'         => true,
            'peticiones' => $peticiones,
            'kpis'       => $kpis,
        ]);
    }

    /**
     * Endpoint invocado por el capturista cuando requiere autorización (ej. mover a inconsistencias, liberar lote, etc.).
     */
    public function solicitar(Request $request)
    {
        $usuario = Auth::user();
        $rutaOrigen = $request->input('ruta_origen');
        $rutaDestino = $request->input('ruta_destino');
        $inspeccion = PdfService::inspeccionarPdf($rutaOrigen ?: $rutaDestino);

        $peticion = PeticionAutorizacion::create([
            'user_id'           => $usuario ? $usuario->id : $request->input('user_id'),
            'tipo_peticion'     => $request->input('tipo_peticion', 'Inconsistencia'),
            'accion_solicitada' => $request->input('accion_solicitada', 'Mover a Inconsistencias'),
            'disco'             => $request->input('disco'),
            'lote'              => $request->input('lote'),
            'archivo_nombre'    => $request->input('archivo_nombre'),
            'ruta_origen'       => $rutaOrigen,
            'ruta_destino'      => $rutaDestino,
            'paginas'           => (int) $request->input('paginas', $inspeccion['paginas']),
            'hash'              => $request->input('hash', $inspeccion['hash']),
            'archivo_danado'    => (bool) $request->input('archivo_danado', $inspeccion['archivo_danado']),
            'motivo'            => $request->input('motivo', 'Solicitado por el capturista en estación de trabajo'),
            'estado'            => 'pendiente',
        ]);

        $payload = [
            'id'             => $peticion->id,
            'user_id'        => $peticion->user_id,
            'capturista'     => $usuario ? $usuario->name : 'Capturista',
            'tipo_peticion'  => $peticion->tipo_peticion,
            'archivo_nombre' => $peticion->archivo_nombre,
            'lote'           => $peticion->lote,
            'estado'         => 'pendiente',
        ];

        try {
            broadcast(new PeticionAutorizacionEvent('nueva_peticion', $payload));
        } catch (\Throwable $e) {
            // Continuar aunque el servidor WS local no esté corriendo
        }

        return response()->json([
            'ok'          => true,
            'peticion_id' => $peticion->id,
            'estado'      => $peticion->estado,
            'mensaje'     => 'Petición enviada al panel de supervisión en tiempo real.',
        ]);
    }

    /**
     * Consulta el estado actual de una petición específica (para el capturista que espera respuesta).
     */
    public function estado($id)
    {
        $peticion = PeticionAutorizacion::with('supervisor:id,name')->findOrFail($id);

        return response()->json([
            'ok'                    => true,
            'id'                    => $peticion->id,
            'estado'                => $peticion->estado,
            'supervisor'            => $peticion->supervisor?->name,
            'comentario_resolucion' => $peticion->comentario_resolucion,
            'resuelto_en'           => $peticion->resuelto_en?->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Autoriza o rechaza una petición en tiempo real y notifica por WebSocket al capturista.
     */
    public function resolver(Request $request, $id)
    {
        $request->validate([
            'decision'   => 'required|in:autorizada,rechazada',
            'comentario' => 'nullable|string|max:500',
        ]);

        $peticion = PeticionAutorizacion::findOrFail($id);
        $supervisor = Auth::user();

        $peticion->update([
            'estado'                => $request->input('decision'),
            'autorizado_por'        => $supervisor?->id,
            'comentario_resolucion' => $request->input('comentario'),
            'resuelto_en'           => now(),
        ]);

        $payload = [
            'id'                    => $peticion->id,
            'user_id'               => $peticion->user_id,
            'estado'                => $peticion->estado,
            'supervisor'            => $supervisor?->name ?? 'Supervisor',
            'comentario_resolucion' => $peticion->comentario_resolucion,
        ];

        try {
            broadcast(new PeticionAutorizacionEvent('peticion_resuelta', $payload));
        } catch (\Throwable $e) {
            // Continuar si el servidor WS no responde
        }

        return response()->json([
            'ok'       => true,
            'peticion' => $payload,
            'mensaje'  => $peticion->estado === 'autorizada'
                ? "Petición #{$peticion->id} AUTORIZADA en tiempo real."
                : "Petición #{$peticion->id} RECHAZADA.",
        ]);
    }
}
