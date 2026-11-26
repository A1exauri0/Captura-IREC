<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StellumBridgeService
{
    private string $baseUrl;
    private string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) config('services.stellum_bridge.url', ''), '/');
        $this->token   = (string) config('services.stellum_bridge.token', '');
    }

    /**
     * Consulta un bloque de registros en indice_archivos_pdfs usando paginación por cursor (millones de filas).
     */
    public function listarIndice(array $filtros = []): array
    {
        if ($this->baseUrl === '' || !preg_match('#^https?://#i', $this->baseUrl)) {
            return ['ok' => false, 'error' => 'STELLUM_BRIDGE_URL no configurada en .env', 'items' => []];
        }

        try {
            $filtros['bridge_token'] = $this->token;
            $response = Http::withToken($this->token)
                ->timeout(20)
                ->get($this->baseUrl . '/indice-pdfs', $filtros);

            if ($response->successful()) {
                return $response->json() ?: ['ok' => false, 'items' => []];
            }
            $jsonErr = $response->json();
            $detalle = is_array($jsonErr) && !empty($jsonErr['error']) ? ': ' . $jsonErr['error'] : '';
            return ['ok' => false, 'status' => $response->status(), 'error' => 'HTTP ' . $response->status() . $detalle, 'items' => []];
        } catch (\Throwable $e) {
            Log::warning('StellumBridgeService::listarIndice error: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage(), 'items' => []];
        }
    }

    /**
     * Solicita a Stellum un grupo de 2 o más PDFs entregables que compartan exactamente el mismo hash.
     */
    public function buscarGrupoMismoHash(string $tipo = 'sellos', array $excluirHashes = []): array
    {
        if ($this->baseUrl === '' || !preg_match('#^https?://#i', $this->baseUrl)) {
            return ['ok' => false, 'error' => 'STELLUM_BRIDGE_URL no configurada en .env', 'items' => []];
        }

        try {
            $params = [
                'tipo'         => $tipo,
                'bridge_token' => $this->token,
            ];
            if (!empty($excluirHashes)) {
                $params['excluir_hashes'] = implode(',', array_slice($excluirHashes, 0, 50));
            }
            $response = Http::withToken($this->token)
                ->timeout(35)
                ->get($this->baseUrl . '/indice-pdfs/grupo-hash', $params);

            if ($response->successful()) {
                return $response->json() ?: ['ok' => false, 'items' => []];
            }
            return ['ok' => false, 'status' => $response->status(), 'error' => 'HTTP ' . $response->status(), 'items' => []];
        } catch (\Throwable $e) {
            Log::warning('StellumBridgeService::buscarGrupoMismoHash error: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage(), 'items' => []];
        }
    }

    /**
     * Verifica si un nombre de archivo ya existe en los entregables de indice_archivos_pdfs en Stellum.
     */
    public function verificarExistenciaEnEntregables(string $nombreArchivo, ?string $tipo = null, ?int $excluirId = null): array
    {
        if (empty($this->token)) {
            return ['ok' => false, 'existe' => false, 'coincidencia' => null, 'motivo' => 'STELLUM_BRIDGE_TOKEN no configurado'];
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout(10)
                ->post($this->baseUrl . '/indice-pdfs/verificar-existencia', [
                    'nombre'     => $nombreArchivo,
                    'tipo'       => $tipo,
                    'excluir_id' => $excluirId,
                ]);

            if ($response->successful()) {
                return $response->json() ?: ['ok' => false, 'existe' => false, 'coincidencia' => null];
            }
            return ['ok' => false, 'existe' => false, 'coincidencia' => null, 'status' => $response->status()];
        } catch (\Throwable $e) {
            Log::warning('StellumBridgeService::verificarExistencia error: ' . $e->getMessage());
            return ['ok' => false, 'existe' => false, 'coincidencia' => null, 'error' => $e->getMessage()];
        }
    }

    /**
     * Descarga el contenido binario del PDF desde Stellum para servirlo vía proxy (mismo origen, sin exponer token).
     * Envía el token únicamente por header Authorization: Bearer para evitar filtrarlo en logs de URL.
     */
    public function obtenerStreamPdf(int $indicePdfId)
    {
        return Http::withToken($this->token)
            ->timeout(90)
            ->get($this->baseUrl . '/indice-pdfs/' . $indicePdfId . '/stream');
    }

    /**
     * Descarga un PDF desde Stellum en streaming directo a disco (sin cargar el archivo completo en RAM).
     */
    public function descargarPdfAArchivo(int $indicePdfId, string $destinoPath, int $timeoutSeconds = 120): array
    {
        if ($this->baseUrl === '' || !preg_match('#^https?://#i', $this->baseUrl)) {
            return ['ok' => false, 'status' => 502, 'error' => 'STELLUM_BRIDGE_URL no configurada en .env'];
        }

        $url = $this->baseUrl . '/indice-pdfs/' . $indicePdfId . '/stream';

        try {
            $response = Http::withToken($this->token)
                ->timeout($timeoutSeconds)
                ->sink($destinoPath)
                ->get($url);

            if ($response->successful()) {
                return ['ok' => true, 'status' => $response->status(), 'error' => null];
            }

            return [
                'ok'     => false,
                'status' => $response->status(),
                'error'  => 'HTTP ' . $response->status(),
            ];
        } catch (\Illuminate\Http\Client\ConnectionException $e) {
            Log::warning('StellumBridgeService::descargarPdfAArchivo timeout/conexión', [
                'indice_pdf_id' => $indicePdfId,
                'mensaje'       => $e->getMessage(),
            ]);
            return ['ok' => false, 'status' => 504, 'error' => 'Timeout o error de conexión con Stellum'];
        } catch (\Throwable $e) {
            Log::warning('StellumBridgeService::descargarPdfAArchivo error', [
                'indice_pdf_id' => $indicePdfId,
                'mensaje'       => $e->getMessage(),
            ]);
            return ['ok' => false, 'status' => 502, 'error' => $e->getMessage()];
        }
    }

    /**
     * Genera la URL de streaming de un PDF alojado en Stellum por su id de indice_archivos_pdfs.
     */
    public function urlStreamPdf(int $indicePdfId): string
    {
        $url = $this->baseUrl . '/indice-pdfs/' . $indicePdfId . '/stream';
        if ($this->token !== '') {
            $url .= '?bridge_token=' . urlencode($this->token);
        }
        return $url;
    }

    /**
     * Envía un chunk (500-1000 operaciones) hacia Stellum para ejecución física asíncrona en cola.
     */
    public function enviarChunkAplicacion(int $ordenId, string $tipoDocumento, ?string $delegacion, int $totalItems, array $items): array
    {
        try {
            $response = Http::withToken($this->token)
                ->timeout(60)
                ->post($this->baseUrl . '/aplicar-cambios/lote', [
                    'orden_id'       => $ordenId,
                    'tipo_documento' => $tipoDocumento,
                    'delegacion'     => $delegacion,
                    'total_items'    => $totalItems,
                    'items'          => $items,
                ]);

            if ($response->successful()) {
                return $response->json() ?: ['ok' => true];
            }
            return [
                'ok'     => false,
                'status' => $response->status(),
                'error'  => $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::error('StellumBridgeService::enviarChunkAplicacion error: ' . $e->getMessage());
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Consulta el estado de progreso en Stellum para una orden masiva.
     */
    public function consultarEstadoOrden(int $ordenId): array
    {
        try {
            $response = Http::withToken($this->token)
                ->timeout(15)
                ->get($this->baseUrl . '/aplicar-cambios/' . $ordenId . '/estado');

            if ($response->successful()) {
                return $response->json() ?: ['ok' => false];
            }
            return ['ok' => false, 'status' => $response->status()];
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Consulta en Stellum únicamente las PCs y Laptops marcadas como entregadas.
     */
    public function obtenerEquiposEntregados(): array
    {
        if ($this->baseUrl === '' || !preg_match('#^https?://#i', $this->baseUrl)) {
            return [
                'ok'      => false,
                'equipos' => [],
                'error'   => 'STELLUM_BRIDGE_URL no está configurada en .env (ej. https://app.astronmx.cloud/api/captura-bridge). Recuerda ejecutar php artisan config:clear.',
            ];
        }

        try {
            $response = Http::withToken($this->token)
                ->timeout(15)
                ->get($this->baseUrl . '/equipos/entregados', [
                    'bridge_token' => $this->token,
                ]);

            if ($response->successful()) {
                return $response->json() ?: ['ok' => false, 'equipos' => []];
            }

            $jsonErr = $response->json();
            $detalle = is_array($jsonErr) && !empty($jsonErr['error']) ? ': ' . $jsonErr['error'] : '';

            return ['ok' => false, 'status' => $response->status(), 'equipos' => [], 'error' => 'HTTP ' . $response->status() . $detalle];
        } catch (\Throwable $e) {
            Log::warning('StellumBridgeService::obtenerEquiposEntregados error: ' . $e->getMessage());
            return ['ok' => false, 'equipos' => [], 'error' => $e->getMessage()];
        }
    }

    /**
     * Reenvía una petición del módulo de equipos hacia Stellum (Fuente Única de Verdad).
     */
    public function proxyEquipos(string $method, array $query = [], array $body = []): array
    {
        try {
            $query['bridge_token'] = $this->token;
            $url = $this->baseUrl . '/equipos/api?' . http_build_query($query);

            $http = Http::withToken($this->token)->timeout(25);
            $response = strtoupper($method) === 'POST'
                ? $http->post($url, $body)
                : $http->get($url);

            return [
                'status' => $response->status(),
                'body'   => $response->json() ?? ['exito' => false, 'mensaje' => $response->body()],
            ];
        } catch (\Throwable $e) {
            Log::warning('StellumBridgeService::proxyEquipos error: ' . $e->getMessage());
            return [
                'status' => 502,
                'body'   => ['exito' => false, 'mensaje' => 'No se pudo conectar con Stellum: ' . $e->getMessage()],
            ];
        }
    }

    /**
     * Descarga el archivo Excel de inventario de equipos directamente desde Stellum.
     */
    public function descargarExcelEquipos()
    {
        return Http::withToken($this->token)
            ->timeout(45)
            ->get($this->baseUrl . '/equipos/exportar-excel');
    }
}
