<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use App\Models\Anio;
use App\Models\Delegaciones;
use App\Models\LoteCaptura;
use App\Models\User;

class NotariasController extends Controller
{
    /**
     * Muestra la vista de captura con los catálogos inyectados.
     */
    public function index()
    {
        $anios = Anio::where('estatus', 1)->pluck('nombre')->toArray();
        if (empty($anios)) {
            $anios = ['2018', '2019', '2020', '2021', '2022', '2023', '2024', '2025', '2026'];
        }

        $delegaciones = Delegaciones::where('estatus', 1)->get()->map(function ($del) {
            $clave = str_pad($del->clave ?? $del->id, 2, '0', STR_PAD_LEFT);
            return [
                'id' => $del->id,
                'clave' => $clave,
                'nombre' => $del->nombre,
            ];
        })->toArray();

        return view('operacion.estacion_captura', compact('anios', 'delegaciones'));
    }

    // =========================================================
    // BÚSQUEDA AUTOMÁTICA DE LOTE
    // =========================================================

    /**
     * Busca el primer lote libre en el disco y delegación indicados.
     * Registra el lote como 'en_proceso' en la BD para bloquearlo.
     * Estructura esperada: {disco}/Respaldo_Original/{delegacion_nombre}/{Lote N}/*.pdf
     */
    public function buscarLote(Request $request)
    {
        $disco = $request->query('disco');
        $delegacionId = $request->query('delegacion_id');

        // Validar que el disco sea uno de los configurados
        if (!in_array($disco, ['irec', 'cats', 'rciv'])) {
            return response()->json(['ok' => false, 'mensaje' => 'Disco no válido.']);
        }

        $delegacion = Delegaciones::find($delegacionId);
        if (!$delegacion) {
            return response()->json(['ok' => false, 'mensaje' => 'Delegación no encontrada.']);
        }

        $storage = Storage::disk($disco);
        $rutaBase = $this->resolverCarpetaDelegacion($storage, 'Respaldo_Original', $delegacion->nombre);

        if (!$rutaBase) {
            return response()->json(['ok' => false, 'mensaje' => "No se encontró la carpeta de la delegación en {$disco}."]);
        }

        // Usar ruta física directa para velocidad en red
        $rutaFisicaBase = $storage->path($rutaBase);

        // Verificar si el usuario ya tiene un lote en proceso en este disco y delegación
        $loteExistente = LoteCaptura::where('disco', $disco)
            ->where('delegacion_id', $delegacionId)
            ->where('estado', 'en_proceso')
            ->where('user_id', Auth::id())
            ->first();

        if ($loteExistente) {
            $carpeta = $loteExistente->ruta_relativa;
            $rutaFisicaCarpeta = $storage->path($carpeta);
            if (is_dir($rutaFisicaCarpeta)) {
                $pdfs = glob($rutaFisicaCarpeta . DIRECTORY_SEPARATOR . '*.pdf');
                // Normalizar a rutas relativas (con separador /)
                if (empty($pdfs)) {
                    $loteExistente->update([
                        'estado' => 'completado',
                        'completado_en' => now()
                    ]);
                } else {
                    $raizDisco = $storage->path('');
                    $listaPdfs = array_values(array_map(function ($f) use ($raizDisco) {
                        $relativa = str_replace('\\', '/', ltrim(str_replace($raizDisco, '', $f), '\\/'));
                        return [
                            'ruta' => $relativa,
                            'nombre' => pathinfo($f, PATHINFO_FILENAME),
                        ];
                    }, $pdfs));

                    return response()->json([
                        'ok' => true,
                        'lote_id' => $loteExistente->id,
                        'lote' => $loteExistente->nombre_lote,
                        'ruta_relativa' => $carpeta,
                        'archivos' => $listaPdfs,
                        'total' => count($listaPdfs),
                    ]);
                }
            }
        }

        // Obtener todas las carpetas de lotes con scandir directo (rápido en red)
        $entradas = @scandir($rutaFisicaBase);
        if ($entradas === false) {
            return response()->json(['ok' => false, 'mensaje' => 'No se pudo leer la carpeta de la delegación.']);
        }

        $carpetas = [];
        foreach ($entradas as $entrada) {
            if ($entrada === '.' || $entrada === '..')
                continue;
            $rutaCompleta = $rutaFisicaBase . DIRECTORY_SEPARATOR . $entrada;
            if (is_dir($rutaCompleta)) {
                $carpetas[] = $entrada;
            }
        }

        // Ordenamiento natural (Lote 1, Lote 2... Lote 10)
        usort($carpetas, function ($a, $b) {
            $numA = $this->extraerNumeroLote($a);
            $numB = $this->extraerNumeroLote($b);
            return $numA - $numB;
        });

        $raizDisco = $storage->path('');

        foreach ($carpetas as $nombreLote) {
            // Solo aceptar carpetas que empiecen con "Lote" (ej. "Lote 130")
            if (!preg_match('/^Lote\s/i', $nombreLote)) {
                continue;
            }

            // Ignorar carpetas terminadas (nombre termina en " OK")
            if (preg_match('/\sOK$/i', $nombreLote)) {
                continue;
            }

            // Ruta relativa para la BD (con /)
            $rutaRelativa = $rutaBase . '/' . $nombreLote;

            // Verificar si el lote ya está en la BD como en_proceso o completado
            $existe = LoteCaptura::where('disco', $disco)
                ->where('ruta_relativa', $rutaRelativa)
                ->whereIn('estado', ['en_proceso', 'completado'])
                ->exists();

            if ($existe) {
                continue;
            }

            // Verificar que tenga PDFs (glob directo, muy rápido)
            $rutaFisicaLote = $rutaFisicaBase . DIRECTORY_SEPARATOR . $nombreLote;
            $pdfs = glob($rutaFisicaLote . DIRECTORY_SEPARATOR . '*.pdf');

            if (empty($pdfs)) {
                continue;
            }

            // Tomar el lote: registrar en BD
            $loteCaptura = LoteCaptura::create([
                'disco' => $disco,
                'delegacion_id' => $delegacionId,
                'nombre_lote' => $nombreLote,
                'ruta_relativa' => $rutaRelativa,
                'estado' => 'en_proceso',
                'user_id' => Auth::id(),
                'tomado_en' => now(),
            ]);

            // Retornar la lista de PDFs con rutas relativas
            $listaPdfs = array_values(array_map(function ($f) use ($raizDisco) {
                $relativa = str_replace('\\', '/', ltrim(str_replace($raizDisco, '', $f), '\\/'));
                return [
                    'ruta' => $relativa,
                    'nombre' => pathinfo($f, PATHINFO_FILENAME),
                ];
            }, $pdfs));

            return response()->json([
                'ok' => true,
                'lote_id' => $loteCaptura->id,
                'lote' => $nombreLote,
                'ruta_relativa' => $rutaRelativa,
                'archivos' => $listaPdfs,
                'total' => count($listaPdfs),
            ]);
        }

        return response()->json(['ok' => false, 'mensaje' => 'No hay lotes disponibles en esta delegación.']);
    }

    /**
     * Extrae el número de un nombre de lote (ej. "Lote 25" → 25).
     */
    private function extraerNumeroLote(string $nombre): int
    {
        $limpio = preg_replace('/[^0-9]/', '', strtolower(str_replace('lote', '', $nombre)));
        return $limpio !== '' ? (int) $limpio : 999999;
    }

    /**
     * Resuelve la ruta relativa de la delegación dentro de Respaldo_Original,
     * manejando discrepancias de acentos (ej. "TUXTLA GUTIÉRREZ" en BD vs "Tuxtla Gutierrez" en disco)
     * y mayúsculas/minúsculas en el disco de red.
     */
    private function resolverCarpetaDelegacion($storage, string $subcarpetaBase, string $nombreDelegacion): ?string
    {
        $rutaDirecta = $subcarpetaBase . '/' . $nombreDelegacion;
        if (is_dir($storage->path($rutaDirecta))) {
            return $rutaDirecta;
        }

        $rutaFisicaBase = $storage->path($subcarpetaBase);
        if (!is_dir($rutaFisicaBase)) {
            return null;
        }

        $entradas = @scandir($rutaFisicaBase);
        if ($entradas === false) {
            return null;
        }

        $normBuscado = $this->normalizarTextoSinAcentos($nombreDelegacion);

        foreach ($entradas as $entrada) {
            if ($entrada === '.' || $entrada === '..') {
                continue;
            }
            if ($this->normalizarTextoSinAcentos($entrada) === $normBuscado) {
                return $subcarpetaBase . '/' . $entrada;
            }
        }

        return null;
    }

    private function normalizarTextoSinAcentos(string $str): string
    {
        $sinAcentos = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str);
        return strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $sinAcentos ?: $str));
    }

    // =========================================================
    // SERVIR PDF AL IFRAME
    // =========================================================

    /**
     * Sirve un PDF desde el disco de red al iframe del navegador.
     */
    public function verPdf(Request $request)
    {
        $disco = $request->query('disco');
        $ruta = $request->query('ruta');

        if (!in_array($disco, ['irec', 'cats', 'rciv'])) {
            abort(400, 'Disco no válido.');
        }

        $storage = Storage::disk($disco);

        // Obtener la ruta física completa del disco configurado vía .env
        $rutaFisica = $storage->path($ruta);

        if (!file_exists($rutaFisica)) {
            $partes = explode('/', str_replace('\\', '/', $ruta));
            if (count($partes) >= 2) {
                $subBase = $partes[0];
                $nombreDel = $partes[1];
                $resuelto = $this->resolverCarpetaDelegacion($storage, $subBase, $nombreDel);
                if ($resuelto) {
                    $partes[0] = dirname($resuelto) === '.' ? $subBase : dirname($resuelto);
                    $partes[1] = basename($resuelto);
                    $rutaCorregida = implode('/', $partes);
                    $rutaFisica = $storage->path($rutaCorregida);
                }
            }
        }

        if (!file_exists($rutaFisica)) {
            abort(404, 'Archivo no encontrado: ' . $ruta);
        }

        // Servir directamente por streaming (no carga todo en memoria como Storage::get)
        return response()->file($rutaFisica, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ]);
    }

    // =========================================================
    // RENOMBRAR ARCHIVO
    // =========================================================

    /**
     * Renombra un PDF en el disco.
     */
    public function renombrar(Request $request)
    {
        $disco = $request->input('disco');
        $rutaOriginal = $request->input('ruta_original');
        $nuevoNombre = $request->input('nuevo_nombre');

        $storage = Storage::disk($disco);
        $rutaOriginalFisica = $storage->path($rutaOriginal);
        $carpetaFisica = dirname($rutaOriginalFisica);
        $rutaNuevaFisica = $carpetaFisica . DIRECTORY_SEPARATOR . $nuevoNombre . '.pdf';

        if (!file_exists($rutaOriginalFisica)) {
            return response()->json(['ok' => false, 'mensaje' => 'El archivo original no existe.']);
        }

        // Si ya existe un archivo con ese nombre en la misma carpeta, moverlo a Eliminados
        if (file_exists($rutaNuevaFisica) && strtolower($rutaOriginalFisica) !== strtolower($rutaNuevaFisica)) {
            $rutaEliminadosFisica = $storage->path('Eliminados');
            if (!is_dir($rutaEliminadosFisica)) {
                @mkdir($rutaEliminadosFisica, 0777, true);
            }
            $rutaDestinoEliminado = $rutaEliminadosFisica . DIRECTORY_SEPARATOR . $nuevoNombre . '.pdf';
            if (file_exists($rutaDestinoEliminado)) {
                @unlink($rutaDestinoEliminado);
            }
            @rename($rutaNuevaFisica, $rutaDestinoEliminado);
        }

        // Renombrar con función nativa
        if (strtolower($rutaOriginalFisica) !== strtolower($rutaNuevaFisica)) {
            if (!@rename($rutaOriginalFisica, $rutaNuevaFisica)) {
                return response()->json(['ok' => false, 'mensaje' => 'Error al mover o renombrar el archivo en el servidor.']);
            }
        }

        $raizDisco = $storage->path('');
        $rutaNuevaRelativa = str_replace('\\', '/', ltrim(str_replace($raizDisco, '', $rutaNuevaFisica), '\\/'));

        $totalPaginas = 0;

        return response()->json([
            'ok' => true,
            'ruta_nueva' => $rutaNuevaRelativa,
            'paginas' => $totalPaginas,
            'mensaje' => 'Archivo renombrado correctamente.',
        ]);
    }

    // =========================================================
    // VALIDAR (mover a Calidad — nombre ya correcto)
    // =========================================================

    /**
     * Mueve un PDF a la carpeta de Calidad.
     * Estructura destino: Calidad/{delegacion}/{año}/{lote}/{archivo}.pdf
     */
    public function validar(Request $request)
    {
        $disco = $request->input('disco');
        $rutaArchivo = $request->input('ruta_archivo');
        $delegacionId = $request->input('delegacion_id');
        $nombreLote = $request->input('nombre_lote');

        $storage = Storage::disk($disco);
        $delegacion = Delegaciones::find($delegacionId);

        $rutaArchivoFisica = $storage->path($rutaArchivo);

        if (!file_exists($rutaArchivoFisica)) {
            return response()->json(['ok' => false, 'mensaje' => 'El archivo no existe.']);
        }

        $nombreArchivo = basename($rutaArchivo);
        $nombreSinExt = pathinfo($nombreArchivo, PATHINFO_FILENAME);

        // Detectar año del nombre del archivo para organizar en subcarpetas
        $anioDetectado = $this->detectarAnioDelNombre($nombreSinExt);

        // Construir ruta destino física
        $rutaDestinoRelativa = 'Calidad/' . $delegacion->nombre;
        if ($anioDetectado) {
            $rutaDestinoRelativa .= '/' . $anioDetectado;
        }
        $rutaDestinoRelativa .= '/' . $nombreLote;

        $rutaDestinoFisica = $storage->path($rutaDestinoRelativa);
        if (!is_dir($rutaDestinoFisica)) {
            @mkdir($rutaDestinoFisica, 0777, true);
        }

        $archivoDestinoFisico = $rutaDestinoFisica . DIRECTORY_SEPARATOR . $nombreArchivo;

        // Si ya existe en destino, mover el existente a Eliminados
        if (file_exists($archivoDestinoFisico)) {
            $rutaEliminadosFisica = $storage->path('Eliminados');
            if (!is_dir($rutaEliminadosFisica)) {
                @mkdir($rutaEliminadosFisica, 0777, true);
            }
            $rutaDestinoEliminado = $rutaEliminadosFisica . DIRECTORY_SEPARATOR . $nombreArchivo;
            if (file_exists($rutaDestinoEliminado)) {
                @unlink($rutaDestinoEliminado);
            }
            @rename($archivoDestinoFisico, $rutaDestinoEliminado);
        }

        // Mover archivo a Calidad usando rename() nativo
        if (!@rename($rutaArchivoFisica, $archivoDestinoFisico)) {
            return response()->json(['ok' => false, 'mensaje' => 'Error al mover el archivo a Calidad.']);
        }

        // Contar páginas solo al validar (aquí sí se necesita)
        $archivoDestinoRelativo = $rutaDestinoRelativa . '/' . $nombreArchivo;
        $totalPaginas = $this->contarPaginasPdf($disco, $archivoDestinoRelativo);

        return response()->json([
            'ok' => true,
            'paginas' => $totalPaginas,
            'mensaje' => 'Archivo movido a Calidad correctamente.',
        ]);
    }

    /**
     * Detecta el año del nombre del archivo (segunda parte separada por _).
     * Retorna el año como string o null si no es un año válido.
     */
    private function detectarAnioDelNombre(string $nombre): ?string
    {
        $partes = explode('_', $nombre);
        if (count($partes) >= 2) {
            $anio = $partes[1];
            if (preg_match('/^\d{4}$/', $anio) && (int) $anio >= 2000 && (int) $anio <= 2100) {
                return $anio;
            }
        }
        return null;
    }

    // =========================================================
    // DUPLICAR (copiar a Calidad con nuevo nombre)
    // =========================================================

    /**
     * Copia un PDF a la carpeta de Calidad con un nombre diferente.
     * El original permanece en el lote para poder ser renombrado después.
     */
    public function duplicar(Request $request)
    {
        $disco = $request->input('disco');
        $rutaOriginal = $request->input('ruta_original');
        $nuevoNombre = $request->input('nuevo_nombre');
        $delegacionId = $request->input('delegacion_id');
        $nombreLote = $request->input('nombre_lote');

        $storage = Storage::disk($disco);
        $delegacion = Delegaciones::find($delegacionId);

        $rutaOriginalFisica = $storage->path($rutaOriginal);

        if (!file_exists($rutaOriginalFisica)) {
            return response()->json(['ok' => false, 'mensaje' => 'El archivo original no existe.']);
        }

        $anioDetectado = $this->detectarAnioDelNombre($nuevoNombre);

        $rutaDestinoRelativa = 'Calidad/' . $delegacion->nombre;
        if ($anioDetectado) {
            $rutaDestinoRelativa .= '/' . $anioDetectado;
        }
        $rutaDestinoRelativa .= '/' . $nombreLote;

        $rutaDestinoFisica = $storage->path($rutaDestinoRelativa);
        if (!is_dir($rutaDestinoFisica)) {
            @mkdir($rutaDestinoFisica, 0777, true);
        }

        $archivoDestinoFisico = $rutaDestinoFisica . DIRECTORY_SEPARATOR . $nuevoNombre . '.pdf';

        // Si ya existe en destino, es un duplicado confirmado
        if (file_exists($archivoDestinoFisico)) {
            $archivoDestinoRelativo = $rutaDestinoRelativa . '/' . $nuevoNombre . '.pdf';
            $totalPaginas = $this->contarPaginasPdf($disco, $archivoDestinoRelativo);
            return response()->json([
                'ok' => true,
                'duplicado_existente' => true,
                'paginas' => $totalPaginas,
                'mensaje' => 'El folio ya existe en Calidad.',
            ]);
        }

        // Copiar archivo usando copy() nativo (rápido)
        if (!@copy($rutaOriginalFisica, $archivoDestinoFisico)) {
            return response()->json(['ok' => false, 'mensaje' => 'Error al duplicar el archivo.']);
        }

        $archivoDestinoRelativo = $rutaDestinoRelativa . '/' . $nuevoNombre . '.pdf';
        $totalPaginas = $this->contarPaginasPdf($disco, $archivoDestinoRelativo);

        return response()->json([
            'ok' => true,
            'paginas' => $totalPaginas,
            'mensaje' => 'Archivo duplicado correctamente.',
        ]);
    }

    // =========================================================
    // INCONSISTENCIA (mover a Inconsistencias)
    // =========================================================

    /**
     * Mueve un archivo a la carpeta raíz de Inconsistencias.
     */
    public function inconsistencia(Request $request)
    {
        $disco = $request->input('disco');
        $rutaArchivo = $request->input('ruta_archivo');

        $storage = Storage::disk($disco);
        $rutaArchivoFisica = $storage->path($rutaArchivo);

        if (!file_exists($rutaArchivoFisica)) {
            return response()->json(['ok' => false, 'mensaje' => 'El archivo no existe.']);
        }

        $nombreArchivo = basename($rutaArchivo);

        $rutaInconsistenciasFisica = $storage->path('Inconsistencias');

        // Organizar por notaria y volumen de origen
        $rutaVolumen = dirname($rutaArchivo);
        $volumen = basename($rutaVolumen);
        $notaria = basename(dirname($rutaVolumen));

        if ($volumen && stripos($volumen, 'volumen') !== false && $notaria) {
            $rutaInconsistenciasFisica = $rutaInconsistenciasFisica . DIRECTORY_SEPARATOR . $notaria . DIRECTORY_SEPARATOR . $volumen;
        }

        if (!is_dir($rutaInconsistenciasFisica)) {
            @mkdir($rutaInconsistenciasFisica, 0777, true);
        }

        $destinoFisico = $rutaInconsistenciasFisica . DIRECTORY_SEPARATOR . $nombreArchivo;

        // Si ya existe, agregar timestamp para evitar colisión
        if (file_exists($destinoFisico)) {
            $sinExt = pathinfo($nombreArchivo, PATHINFO_FILENAME);
            $destinoFisico = $rutaInconsistenciasFisica . DIRECTORY_SEPARATOR . $sinExt . '_' . time() . '.pdf';
        }

        if (!@rename($rutaArchivoFisica, $destinoFisico)) {
            return response()->json(['ok' => false, 'mensaje' => 'Error al mover el archivo a Inconsistencias.']);
        }

        $raizDisco = $storage->path('');
        $destinoRelativo = str_replace('\\', '/', ltrim(str_replace($raizDisco, '', $destinoFisico), '\\/'));
        $totalPaginas = $this->contarPaginasPdf($disco, $destinoRelativo);

        return response()->json([
            'ok' => true,
            'paginas' => $totalPaginas,
            'mensaje' => 'Archivo movido a Inconsistencias.'
        ]);
    }

    // =========================================================
    // MOVER A SELLOS (Inconsistencias/Sellos/Lote N)
    // =========================================================

    /**
     * Mueve un archivo a Inconsistencias/Sellos, distribuyendo
     * en subcarpetas de máximo 400 archivos (igual que VB.NET).
     */
    public function moverSellos(Request $request)
    {
        $disco = $request->input('disco');
        $rutaArchivo = $request->input('ruta_archivo');

        $storage = Storage::disk($disco);
        $rutaArchivoFisica = $storage->path($rutaArchivo);

        if (!file_exists($rutaArchivoFisica)) {
            return response()->json(['ok' => false, 'mensaje' => 'El archivo no existe.']);
        }

        // Buscar o crear lote de Sellos con menos de 400 archivos usando rutas físicas
        $rutaBaseSellosFisica = $storage->path('Inconsistencias/Sellos');
        if (!is_dir($rutaBaseSellosFisica)) {
            @mkdir($rutaBaseSellosFisica, 0777, true);
        }

        $numeroLote = 1;
        $rutaLoteSellosFisica = '';

        while ($numeroLote <= 10000) {
            $rutaLoteSellosFisica = $rutaBaseSellosFisica . DIRECTORY_SEPARATOR . 'Lote ' . $numeroLote;
            if (!is_dir($rutaLoteSellosFisica)) {
                @mkdir($rutaLoteSellosFisica, 0777, true);
                break; // Carpeta nueva creada, la usamos
            }
            // Contar archivos usando glob rápido
            $archivosEnLote = glob($rutaLoteSellosFisica . DIRECTORY_SEPARATOR . '*');
            if (count($archivosEnLote) < 400) {
                break; // Tiene espacio
            }
            $numeroLote++;
        }

        $nombreArchivo = basename($rutaArchivo);
        $destinoFisico = $rutaLoteSellosFisica . DIRECTORY_SEPARATOR . $nombreArchivo;

        if (!@rename($rutaArchivoFisica, $destinoFisico)) {
            return response()->json(['ok' => false, 'mensaje' => 'Error al mover el archivo a Sellos.']);
        }

        $raizDisco = $storage->path('');
        $destinoRelativo = str_replace('\\', '/', ltrim(str_replace($raizDisco, '', $destinoFisico), '\\/'));
        $totalPaginas = $this->contarPaginasPdf($disco, $destinoRelativo);

        return response()->json([
            'ok' => true,
            'paginas' => $totalPaginas,
            'mensaje' => 'Archivo movido a Sellos.'
        ]);
    }

    // =========================================================
    // ROTAR PÁGINA
    // =========================================================

    /**
     * Rota una página específica de un PDF usando la librería FPDI/TCPDF.
     * (Requiere paquete setasign/fpdi instalado.)
     */
    /**
     * Rota una página específica de un PDF usando la librería FPDI.
     */
    public function rotar(Request $request)
    {
        $disco = $request->input('disco');
        $rutaArchivo = $request->input('ruta_archivo');
        $pagina = (int) $request->input('pagina', 1);
        $grados = (int) $request->input('grados', 90);

        $rutaArchivo = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rutaArchivo);

        // \Log::info("Parámetros de rotar:", ['disco' => $disco, 'ruta' => $rutaArchivo, 'pagina' => $pagina, 'grados' => $grados]);

        if (!in_array($disco, ['irec', 'cats', 'rciv'])) {
            \Log::warning("Disco no válido en rotar: " . $disco);
            return response()->json(['ok' => false, 'mensaje' => 'Disco no válido.']);
        }

        $storage = Storage::disk($disco);
        $rutaArchivoFisica = $storage->path($rutaArchivo);
        $existe = file_exists($rutaArchivoFisica);
        // \Log::info("Existe archivo físico?: " . ($existe ? 'SI' : 'NO') . " - ruta: " . $rutaArchivoFisica);

        if (!$existe) {
            return response()->json(['ok' => false, 'mensaje' => 'El archivo no existe.']);
        }

        try {
            $pdfContenido = $storage->get($rutaArchivo);

            // Crear archivos temporales en el directorio temporal del sistema
            $tempOri = tempnam(sys_get_temp_dir(), 'pdf_ori_');
            $tempDest = tempnam(sys_get_temp_dir(), 'pdf_dest_');

            file_put_contents($tempOri, $pdfContenido);

            $pdf = new RotatableFpdi();
            $cantPaginas = $pdf->setSourceFile($tempOri);

            for ($i = 1; $i <= $cantPaginas; $i++) {
                $tpl = $pdf->importPage($i);
                $size = $pdf->getTemplateSize($tpl);

                $wOrig = $size['width'];
                $hOrig = $size['height'];

                if ($i === $pagina) {
                    $anguloRotacion = ($grados + 360) % 360;
                    if ($anguloRotacion === 90 || $anguloRotacion === 270) {
                        $wNueva = $hOrig;
                        $hNueva = $wOrig;
                        $pdf->AddPage($wNueva > $hNueva ? 'L' : 'P', [$wNueva, $hNueva]);
                        $pdf->Rotate($anguloRotacion, $wNueva / 2, $hNueva / 2);
                        $pdf->useTemplate($tpl, ($wNueva - $wOrig) / 2, ($hNueva - $hOrig) / 2, $wOrig, $hOrig);
                        $pdf->Rotate(0);
                    } else if ($anguloRotacion === 180) {
                        $pdf->AddPage($wOrig > $hOrig ? 'L' : 'P', [$wOrig, $hOrig]);
                        $pdf->Rotate(180, $wOrig / 2, $hOrig / 2);
                        $pdf->useTemplate($tpl, 0, 0, $wOrig, $hOrig);
                        $pdf->Rotate(0);
                    } else {
                        $pdf->AddPage($wOrig > $hOrig ? 'L' : 'P', [$wOrig, $hOrig]);
                        $pdf->useTemplate($tpl, 0, 0, $wOrig, $hOrig);
                    }
                } else {
                    $pdf->AddPage($wOrig > $hOrig ? 'L' : 'P', [$wOrig, $hOrig]);
                    $pdf->useTemplate($tpl, 0, 0, $wOrig, $hOrig);
                }
            }

            $pdf->Output($tempDest, 'F');

            // Guardar de vuelta al disco
            $storage->put($rutaArchivo, file_get_contents($tempDest));

            return response()->json([
                'ok' => true,
                'mensaje' => "Página $pagina rotada $grados grados correctamente."
            ]);
        } catch (\Exception $e) {
            \Log::error("Error al rotar PDF: " . $e->getMessage(), ['exception' => $e]);
            return response()->json([
                'ok' => false,
                'mensaje' => 'Error al rotar el PDF: ' . $e->getMessage()
            ]);
        } finally {
            if (isset($tempOri) && file_exists($tempOri)) {
                @unlink($tempOri);
            }
            if (isset($tempDest) && file_exists($tempDest)) {
                @unlink($tempDest);
            }
        }
    }

    // =========================================================
    // LIBERAR LOTE
    // =========================================================

    /**
     * Libera un lote marcándolo como disponible (elimina el registro).
     */
    public function liberarLote(Request $request)
    {
        $loteId = $request->input('lote_id');
        $lote = LoteCaptura::find($loteId);

        if ($lote && $lote->user_id === Auth::id()) {
            $lote->delete();
            return response()->json(['ok' => true]);
        }

        return response()->json(['ok' => false, 'mensaje' => 'No se puede liberar el lote.']);
    }

    // =========================================================
    // AUDITORÍA (desde la web)
    // =========================================================

    /**
     * Registra una auditoría de captura desde el frontend web.
     */
    public function auditoria(Request $request)
    {
        $usuario = Auth::user();

        $delegacionId = $request->input('delegacion_id');
        if ($delegacionId) {
            $delegacion = Delegaciones::find($delegacionId);
            if (!$delegacion) {
                $delegacionId = null;
            }
        }

        $directorio = $request->input('disco');
        if ($delegacionId && $directorio) {
            $directorio = trim($directorio) . ' ' . $delegacionId;
        }

        DB::table('auditoria_notarias')->insert([
            'fecha_hora' => now(),
            'turno' => $request->input('turno', $usuario ? $usuario->turno : null),
            'user_id' => $usuario ? $usuario->id : null,
            'categoria' => 'Captura',
            'pc' => $request->input('pc', 'Web'),
            'directorio' => $directorio,
            'volumen' => $request->input('nombre_lote'),
            'accion' => $request->input('accion'),
            'archivo_original' => $request->input('archivo_original'),
            'archivo_nuevo' => $request->input('archivo_nuevo'),
            'ruta' => $request->input('ruta'),
            'detalles' => $request->input('detalles'),
            'paginas' => (int) $request->input('paginas', 0),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['ok' => true]);
    }

    // =========================================================
    // API LEGADA (registrar desde VB.NET)
    // =========================================================

    /**
     * Registra auditorías de captura provenientes de la aplicación VB.NET.
     */
    public function registrar(Request $request)
    {
        $datos = $request->all();
        // \Log::info("Auditoría recibida desde la aplicación de escritorio:", ['datos' => $datos]);

        $registrosAGuardar = [];

        if (isset($datos['Registros']) && is_array($datos['Registros'])) {
            $registrosAGuardar = $datos['Registros'];
        } elseif (isset($datos[0]) && is_array($datos[0])) {
            $registrosAGuardar = $datos;
        } else {
            $registrosAGuardar[] = $datos;
        }

        $insertados = 0;
        $fechaActual = now();
        $errores = [];

        foreach ($registrosAGuardar as $index => $originalReg) {
            if (empty($originalReg) || !is_array($originalReg)) {
                continue;
            }

            try {
                $reg = array_change_key_case($originalReg, CASE_LOWER);
                $fechaHora = $reg['fechahora'] ?? $reg['fecha_hora'] ?? null;

                if (!$fechaHora) {
                    $errores[] = "Registro {$index}: Falta el campo de fecha y hora.";
                    \Log::warning("Registro de auditoría sin fecha_hora:", ['registro' => $originalReg]);
                    continue;
                }

                // Prevenir que registros con fecha del futuro (relojes desajustados) entren a la BD
                $timestampRecibido = strtotime($fechaHora);
                if ($timestampRecibido !== false && $timestampRecibido > time() + 300) {
                    $fechaHora = now()->toDateTimeString();
                }

                $paginasVal = 0;
                if (isset($reg['paginas'])) {
                    $paginasVal = (int) $reg['paginas'];
                } elseif (isset($reg['pagina'])) {
                    $paginasVal = (int) $reg['pagina'];
                }

                // Resolver user_id
                $userIdDirecto = $reg['userid'] ?? $reg['user_id'] ?? null;
                $userVal = $reg['usuario'] ?? null;
                $nombreVal = $reg['nombrecompleto'] ?? $reg['nombre_completo'] ?? null;
                $userId = null;

                if ($userIdDirecto && (int) $userIdDirecto > 0) {
                    $userId = (int) $userIdDirecto;
                } elseif ($userVal || $nombreVal) {
                    $userQuery = User::query();
                    if ($userVal) {
                        $userQuery->where('user', $userVal);
                    }
                    if ($nombreVal) {
                        $userQuery->orWhere('name', $nombreVal);
                    }
                    $userObj = $userQuery->first();
                    if ($userObj) {
                        $userId = $userObj->id;
                    }
                }

                // Resolver delegacion_id
                $delIdDirecto = $reg['delegacionidnumerico'] ?? $reg['delegacion_id_numerico'] ?? null;
                $delIdInput = $reg['delegacionid'] ?? $reg['delegacion_id'] ?? null;
                $delNombreInput = $reg['delegacionnombre'] ?? $reg['delegacion_nombre'] ?? null;
                $delegacionId = null;

                if ($delIdDirecto && (int) $delIdDirecto > 0) {
                    $delegacionId = (int) $delIdDirecto;
                } elseif ($delIdInput || $delNombreInput) {
                    $delQuery = Delegaciones::query();
                    if ($delIdInput) {
                        if (is_numeric($delIdInput)) {
                            $delQuery->where('id', $delIdInput)->orWhere('clave', $delIdInput);
                        } else {
                            $delQuery->where('clave', $delIdInput);
                        }
                    }
                    if ($delNombreInput) {
                        $delQuery->orWhere('nombre', $delNombreInput);
                    }
                    $delObj = $delQuery->first();
                    if ($delObj) {
                        $delegacionId = $delObj->id;
                    }
                }

                if ($userId && $userId > 0) {
                    $this->asegurarUsuarioExiste($userId, [
                        'user' => $userVal,
                        'name' => $nombreVal
                    ]);
                }

                // En Notarías no se utilizan delegaciones, se deja null
                $delegacionId = null;

                // Prevenir duplicados: verificar si ya existe un registro idéntico
                $accionVal = $reg['accion'] ?? null;
                $archivoOriginalVal = $reg['archivooriginal'] ?? $reg['archivo_original'] ?? null;

                $duplicado = DB::table('auditoria_notarias')
                    ->where('fecha_hora', $fechaHora)
                    ->where('accion', $accionVal)
                    ->where('archivo_original', $archivoOriginalVal)
                    ->where(function ($q) use ($userId, $reg) {
                        if ($userId) {
                            $q->where('user_id', $userId);
                        } else {
                            $q->where('pc', $reg['pc'] ?? null);
                        }
                    })
                    ->exists();

                if ($duplicado) {
                    continue; // Ya existe, saltar sin error
                }

                $directorio = $reg['directorio'] ?? null;
                if ($delegacionId && $directorio) {
                    $directorio = trim($directorio) . ' ' . $delegacionId;
                }

                $rutaVal = $reg['ruta'] ?? null;
                $rutaOrigenVal = $reg['ruta_origen'] ?? $reg['rutaorigen'] ?? $rutaVal;
                $rutaDestinoVal = $reg['ruta_destino'] ?? $reg['rutadestino'] ?? $rutaVal;
                $inspeccion = \App\Services\PdfService::inspeccionarPdf($rutaDestinoVal ?: $rutaOrigenVal);

                DB::table('auditoria_notarias')->insert([
                    'fecha_hora' => $fechaHora,
                    'turno' => $reg['turno'] ?? null,
                    'user_id' => $userId,
                    'categoria' => $reg['categoria'] ?? null,
                    'pc' => $reg['pc'] ?? null,
                    'directorio' => $directorio,
                    'volumen' => $reg['lote'] ?? null,
                    'lote' => $reg['lote'] ?? null,
                    'accion' => $accionVal,
                    'archivo_original' => $archivoOriginalVal,
                    'archivo_nuevo' => $reg['archivonuevo'] ?? $reg['archivo_nuevo'] ?? null,
                    'ruta' => $rutaVal,
                    'ruta_origen' => $rutaOrigenVal,
                    'ruta_destino' => $rutaDestinoVal,
                    'hash' => $reg['hash'] ?? $inspeccion['hash'],
                    'archivo_danado' => (bool) ($reg['archivo_danado'] ?? $inspeccion['archivo_danado']),
                    'detalles' => $reg['detalles'] ?? null,
                    'paginas' => $paginasVal ?: $inspeccion['paginas'],
                    'tiempo_captura' => $reg['tiempo_captura'] ?? $reg['tiempocaptura'] ?? null,
                    'created_at' => $fechaActual,
                    'updated_at' => $fechaActual,
                ]);
                $insertados++;
            } catch (\Exception $e) {
                $errores[] = "Registro {$index}: Error de base de datos: " . $e->getMessage();
                \Log::error("Error al insertar auditoría en la base de datos:", ['registro' => $originalReg, 'error' => $e->getMessage()]);
            }
        }

        if ($insertados === 0) {
            \Log::warning("No se insertó ninguna auditoría en el endpoint registrar.", ['errores' => $errores]);
            return response()->json([
                'ok' => false,
                'mensaje' => "No se pudo registrar ninguna auditoría.",
                'errores' => $errores,
            ], 400);
        }

        // Actualizar updated_at de la sesión activa de notarías del usuario para marcar actividad reciente
        if ($insertados > 0 && isset($userId)) {
            try {
                \App\Models\LoteCaptura::where('user_id', $userId)
                    ->where('estado', 'en_proceso')
                    ->update(['updated_at' => now()]);
            } catch (\Exception $e) {
                // Falla silenciosa si no hay sesión activa
            }
        }

        // \Log::info("Auditorías registradas exitosamente en el endpoint:", ['cantidad' => $insertados]);
        return response()->json([
            'ok' => true,
            'mensaje' => "Se registraron exitosamente $insertados auditorías.",
            'procesados' => $insertados,
            'errores' => $errores,
        ]);
    }

    /**
     * Cuenta el número total de páginas de un archivo PDF en el disco.
     */
    private function contarPaginasPdf($disco, $ruta)
    {
        try {
            $storage = Storage::disk($disco);
            if (!$storage->exists($ruta)) {
                return 0;
            }
            $pdfContenido = $storage->get($ruta);
            // Crear archivo temporal en el directorio temporal del sistema
            $tempFile = tempnam(sys_get_temp_dir(), 'pdf_count_');
            file_put_contents($tempFile, $pdfContenido);

            $pdf = new RotatableFpdi();
            $cantPaginas = $pdf->setSourceFile($tempFile);

            return (int) $cantPaginas;
        } catch (\Exception $e) {
            \Log::error("Error al contar páginas del PDF: " . $e->getMessage());
            return 0;
        } finally {
            if (isset($tempFile) && file_exists($tempFile)) {
                @unlink($tempFile);
            }
        }
    }

    // =========================================================
    // SESIONES ACTIVAS (API para VB.NET — reemplaza archivo .lock)
    // =========================================================

    /**
     * Registra o actualiza la sesión activa de un usuario desde la app de escritorio.
     * Se usa como heartbeat para saber quién está trabajando.
     */
    /**
     * Asegura que el usuario con el ID especificado existe en la base de datos.
     * Si no existe, lo crea automáticamente para evitar errores de integridad.
     */
    private function asegurarUsuarioExiste($userId, $datosComplementarios = [])
    {
        if (!$userId || (int) $userId <= 0) {
            return;
        }

        $userExists = User::where('id', $userId)->exists();
        if (!$userExists) {
            $username = trim(strtolower($datosComplementarios['user'] ?? ('usuario_' . $userId)));
            $nombre = trim($datosComplementarios['name'] ?? ('Usuario Autocreado ' . $userId));

            if (User::where('user', $username)->exists()) {
                $username = $username . '_' . time();
            }

            $email = $username . '@gmail.com';
            if (User::where('email', $email)->exists()) {
                $email = $username . '_' . time() . '@gmail.com';
            }

            User::forceCreate([
                'id' => $userId,
                'name' => $nombre,
                'user' => $username,
                'email' => $email,
                'password' => bcrypt('password123'),
                'estatus' => 1,
            ]);
        }
    }

    /**
     * Asegura que la delegación con el ID especificado existe en la base de datos.
     * Si no existe, la crea automáticamente.
     */
    private function asegurarDelegacionExiste($delegacionId, $datosComplementarios = [])
    {
        if (!$delegacionId || (int) $delegacionId <= 0) {
            return;
        }

        $delExists = Delegaciones::where('id', $delegacionId)->exists();
        if (!$delExists) {
            $nombre = $datosComplementarios['nombre'] ?? ('Delegación ' . $delegacionId);
            $clave = $datosComplementarios['clave'] ?? ('DEL_' . $delegacionId);

            if (Delegaciones::where('clave', $clave)->exists()) {
                $clave = $clave . '_' . time();
            }

            Delegaciones::forceCreate([
                'id' => $delegacionId,
                'nombre' => $nombre,
                'clave' => $clave,
                'estatus' => 1,
            ]);
        }
    }

    /**
     * Registra o actualiza la sesión activa de un usuario desde la app de escritorio.
     * Se usa como heartbeat para saber quién está trabajando.
     */
    public function registrarSesion(Request $request)
    {
        $userId = $request->input('user_id');
        $pc = $request->input('pc');
        $lote = $request->input('lote');
        $directorio = $request->input('directorio');
        $liberar = $request->input('liberar', false);

        if (!$userId) {
            return response()->json(['ok' => false, 'mensaje' => 'Se requiere user_id.'], 400);
        }

        if ($liberar) {
            LoteCaptura::where('user_id', $userId)->where('estado', 'en_proceso')->delete();
            return response()->json([
                'ok' => true,
                'mensaje' => 'Sesión liberada correctamente.',
            ]);
        }

        $rutaRelativa = $request->input('ruta_relativa');
        $rutaFinal = !empty($rutaRelativa) ? $rutaRelativa : $pc;

        // Asegurar que exista el usuario
        $this->asegurarUsuarioExiste($userId);

        // Transacción atómica con verificación de colisión
        $resultado = DB::transaction(function () use ($userId, $rutaFinal, $lote, $pc, $directorio) {
            // 1. Liberar automáticamente sesiones inactivas de este volumen (más de 30 minutos sin actualizar)
            if ($rutaFinal) {
                LoteCaptura::where('ruta_relativa', $rutaFinal)
                    ->where('estado', 'en_proceso')
                    ->where('updated_at', '<', now()->subMinutes(30))
                    ->delete();

                // 2. Verificar si OTRO usuario ya tiene este volumen reservado en la misma notaría
                $ocupante = LoteCaptura::where('ruta_relativa', $rutaFinal)
                    ->where('estado', 'en_proceso')
                    ->where('user_id', '!=', $userId)
                    ->lockForUpdate()
                    ->first();
            }

            if (isset($ocupante) && $ocupante) {
                $nombreOcupante = '';
                $usuarioOcupante = User::find($ocupante->user_id);
                if ($usuarioOcupante) {
                    $nombreOcupante = $usuarioOcupante->name ?? $usuarioOcupante->user ?? 'Usuario #' . $ocupante->user_id;
                }
                return [
                    'ok' => false,
                    'mensaje' => "El volumen '{$lote}' ya está siendo trabajado por {$nombreOcupante}.",
                    'ocupado_por' => $nombreOcupante,
                    'ocupado_por_id' => $ocupante->user_id,
                ];
            }

            // 2. Limpiar sesiones previas del mismo usuario en notarías
            LoteCaptura::where('user_id', $userId)
                ->where('estado', 'en_proceso')
                ->delete();

            // Extraer nombre de la notaría de la ruta_relativa (ej. "NOTARIA 76/VOLUMEN 5" -> "NOTARIA 76")
            $notariaNombre = null;
            if ($rutaFinal) {
                $partes = explode('/', str_replace('\\', '/', $rutaFinal));
                $notariaNombre = $partes[0] ?? null;
            }

            // 3. Crear la nueva sesión inmediatamente
            $sesion = LoteCaptura::create([
                'user_id' => $userId,
                'notaria_nombre' => $notariaNombre,
                'volumen' => $lote ?? '',
                'ruta_relativa' => $rutaFinal ?? '',
                'disco' => $directorio ?? 'NOTARÍAS',
                'pc' => $pc ?? '',
                'estado' => 'en_proceso',
                'tomado_en' => now(),
            ]);

            return [
                'ok' => true,
                'sesion_id' => $sesion->id,
                'mensaje' => 'Sesión registrada correctamente.',
            ];
        });

        $statusCode = $resultado['ok'] ? 200 : 409; // 409 = Conflicto
        return response()->json($resultado, $statusCode);
    }

    /**
     * Obtiene la lista de usuarios con sesiones activas (en_proceso).
     * Reemplaza la dependencia del archivo .lock en la red.
     */
    public function obtenerSesiones(Request $request)
    {
        $sesiones = LoteCaptura::where('estado', 'en_proceso')
            ->with(['usuario:id,name,user'])
            ->get()
            ->map(function ($sesion) {
                // Extraer solo dígitos del nombre de la notaría para delegacion_id (ej. "NOTARIA 76" -> "76")
                $notariaId = preg_replace('/[^0-9]/', '', $sesion->notaria_nombre ?? '');

                return [
                    'id' => $sesion->id,
                    'user_id' => $sesion->user_id,
                    'usuario' => $sesion->usuario ? $sesion->usuario->name : null,
                    'nombre_usuario' => $sesion->usuario ? $sesion->usuario->user : null,
                    'delegacion_id' => !empty($notariaId) ? $notariaId : null,
                    'delegacion' => $sesion->notaria_nombre,
                    'directorio' => $sesion->disco ?? 'NOTARÍAS',
                    'lote' => $sesion->volumen,
                    'pc' => $sesion->ruta_relativa,
                    'tomado_en' => $sesion->tomado_en ? $sesion->tomado_en->toDateTimeString() : null,
                ];
            });

        return response()->json([
            'ok' => true,
            'sesiones' => $sesiones,
        ]);
    }
}

if (!class_exists('App\Http\Controllers\RotatableFpdi')) {
    /**
     * Clase auxiliar para permitir la rotación de páginas en plantillas PDF importadas con FPDI.
     */
    class RotatableFpdi extends \setasign\Fpdi\Fpdi
    {
        public $angle = 0;

        public function Rotate($angle, $x = -1, $y = -1)
        {
            if ($x == -1)
                $x = $this->x;
            if ($y == -1)
                $y = $this->y;
            if ($this->angle != 0)
                $this->_out('Q');
            $this->angle = $angle;
            if ($angle != 0) {
                $angle *= M_PI / 180;
                $c = cos($angle);
                $s = sin($angle);
                $cx = $x * $this->k;
                $cy = ($this->h - $y) * $this->k;
                $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
            }
        }

        protected function _endpage()
        {
            if ($this->angle != 0) {
                $this->angle = 0;
                $this->_out('Q');
            }
            parent::_endpage();
        }
    }
}
