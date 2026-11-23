<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\StellumBridgeService;
use Illuminate\Http\Request;

class InventarioEquiposController extends Controller
{
    /**
     * Muestra únicamente el listado de equipos marcados como ENTREGADOS en Stellum
     * y permite asignarlos a los usuarios de Captura-IREC.
     */
    public function index(StellumBridgeService $bridge)
    {
        $usuarios = User::with('turnoRelacion')
            ->orderBy('name')
            ->get(['id', 'name', 'user', 'turno', 'turno_id', 'equipo_no_serie', 'equipo_tipo', 'equipo_tabla', 'equipo_id_stellum', 'equipo_marca', 'equipo_ubicacion']);

        $respStellum = $bridge->obtenerEquiposEntregados();
        $equiposEntregados = collect($respStellum['equipos'] ?? []);
        $conexionStellumOk = (bool) ($respStellum['ok'] ?? false);
        $errorStellum = $respStellum['error'] ?? null;

        // Mapa rápido: no_serie => usuario asignado en Captura-IREC
        $mapaAsignados = $usuarios
            ->filter(fn ($u) => !empty($u->equipo_no_serie))
            ->keyBy('equipo_no_serie');

        return view('configuracion.inventario_equipos', compact(
            'usuarios',
            'equiposEntregados',
            'conexionStellumOk',
            'errorStellum',
            'mapaAsignados'
        ));
    }

    /**
     * Proxy transparente hacia Stellum (/api/captura-bridge/equipos/api).
     * Permite leer y escribir exactamente las mismas tablas de Stellum en tiempo real.
     */
    public function apiProxy(Request $request, StellumBridgeService $bridge)
    {
        $resultado = $bridge->proxyEquipos(
            $request->method(),
            $request->query(),
            $request->isMethod('post') ? $request->all() : []
        );

        return response()->json($resultado['body'], $resultado['status']);
    }

    /**
     * Descarga el Excel multi-hoja directamente desde Stellum.
     */
    public function exportarExcel(StellumBridgeService $bridge)
    {
        try {
            $resp = $bridge->descargarExcelEquipos();
            if (!$resp->successful()) {
                return back()->with('error', 'No se pudo descargar el Excel desde Stellum (HTTP ' . $resp->status() . ').');
            }

            $filename = 'Inventario_Equipos_Stellum_' . date('Ymd_His') . '.xlsx';
            return response($resp->body(), 200, [
                'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
        } catch (\Throwable $e) {
            return back()->with('error', 'Error al conectar con Stellum para exportar Excel: ' . $e->getMessage());
        }
    }

    /**
     * Asigna un equipo de Stellum a un usuario de Captura-IREC y actualiza `prestado_a` en Stellum
     * para mantener ambos sistemas sincronizados al instante.
     */
    public function asignarUsuario(Request $request, StellumBridgeService $bridge)
    {
        $data = $request->validate([
            'user_id'           => 'required|exists:users,id',
            'equipo_no_serie'   => 'required|string|max:100',
            'equipo_tipo'       => 'nullable|string|max:30',
            'equipo_tabla'      => 'nullable|string|max:30',
            'equipo_id_stellum' => 'nullable|integer',
            'equipo_marca'      => 'nullable|string|max:100',
            'equipo_ubicacion'  => 'nullable|string|max:100',
        ]);

        // Liberar ese mismo no_serie si estaba asignado a otro usuario en Captura-IREC
        User::where('equipo_no_serie', $data['equipo_no_serie'])
            ->where('id', '!=', $data['user_id'])
            ->update([
                'equipo_no_serie'   => null,
                'equipo_tipo'       => null,
                'equipo_tabla'      => null,
                'equipo_id_stellum' => null,
                'equipo_marca'      => null,
                'equipo_ubicacion'  => null,
            ]);

        $usuario = User::findOrFail($data['user_id']);
        $usuario->update([
            'equipo_no_serie'   => trim($data['equipo_no_serie']),
            'equipo_tipo'       => $data['equipo_tipo'] ?: 'PC',
            'equipo_tabla'      => $data['equipo_tabla'] ?: 'computadoras',
            'equipo_id_stellum' => $data['equipo_id_stellum'] ?? null,
            'equipo_marca'      => $data['equipo_marca'] ?? null,
            'equipo_ubicacion'  => $data['equipo_ubicacion'] ?? null,
        ]);

        // Sincronizar también en Stellum (campo prestado_a) si tenemos id + tabla
        if (!empty($data['equipo_id_stellum']) && !empty($data['equipo_tabla'])) {
            $bridge->proxyEquipos('POST', ['accion' => 'actualizar_prestamo'], [
                'accion'       => 'actualizar_prestamo',
                'id'           => (int) $data['equipo_id_stellum'],
                'tabla_origen' => $data['equipo_tabla'],
                'prestado_a'   => $usuario->name,
            ]);
        }

        return back()->with('success', "Equipo {$usuario->equipo_no_serie} asignado a {$usuario->name} y sincronizado con Stellum.");
    }

    /**
     * Quita el equipo asignado a un usuario y limpia el préstamo en Stellum.
     */
    public function desasignarUsuario(int $userId, StellumBridgeService $bridge)
    {
        $usuario = User::findOrFail($userId);

        if ($usuario->equipo_id_stellum && $usuario->equipo_tabla) {
            $bridge->proxyEquipos('POST', ['accion' => 'actualizar_prestamo'], [
                'accion'       => 'actualizar_prestamo',
                'id'           => (int) $usuario->equipo_id_stellum,
                'tabla_origen' => $usuario->equipo_tabla,
                'prestado_a'   => '',
            ]);
        }

        $serie = $usuario->equipo_no_serie;
        $usuario->update([
            'equipo_no_serie'   => null,
            'equipo_tipo'       => null,
            'equipo_tabla'      => null,
            'equipo_id_stellum' => null,
            'equipo_marca'      => null,
            'equipo_ubicacion'  => null,
        ]);

        return back()->with('success', "Se liberó el equipo {$serie} del usuario {$usuario->name}.");
    }
}
